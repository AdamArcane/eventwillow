#!/usr/bin/env python3
"""Prepare reviewable EventSchedule syncs without committing or merging into main."""
import argparse
import datetime
import json
from pathlib import Path
import shlex
import shutil
import subprocess
import sys

MANIFEST = 'config/sitemap_lastmod.php'
FOCUSED_TESTS = [
    'tests/Feature/EventWillowBrandingTranslationsTest.php',
    'tests/Feature/EventWillowBrandingOverridesTest.php',
    'tests/Feature/EventWillowPresentationTest.php',
    'tests/Feature/TranslationOverrideTest.php',
    'tests/Feature/ThemeVariantGatingTest.php',
    'app/Modules/EventWillowPublicApi/tests/SiteApiTest.php',
]


class SyncError(Exception):
    pass


class Sync:
    def __init__(self, root, args):
        self.root = Path(root)
        self.args = args
        self.directory = Path(self.git('rev-parse', '--git-path', 'eventwillow-sync'))
        if not self.directory.is_absolute():
            self.directory = self.root / self.directory
        self.state_file = self.directory / 'state.json'
        self.report_file = self.directory / 'pull-request.md'

    def run(self, command, allowed=(0,)):
        result = subprocess.run(command, cwd=self.root, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        if result.returncode not in allowed:
            raise SyncError(result.stderr.strip() or result.stdout.strip() or f'Command failed: {shlex.join(command)}')
        return result

    def git(self, *args, allowed=(0,)):
        return self.run(['git', *args], allowed=allowed).stdout.strip()

    def active_merge(self):
        return self.run(['git', 'rev-parse', '--verify', 'MERGE_HEAD'], allowed=(0, 128)).returncode == 0

    def branch(self):
        branch = self.git('branch', '--show-current')
        if not branch:
            raise SyncError('Switch to a branch first; detached HEAD is not supported.')
        return branch

    def require_clean(self):
        if self.active_merge() or self.git('status', '--porcelain'):
            raise SyncError('The checkout must be clean, with no merge in progress. Commit or stash your work first.')

    def setup(self):
        self.git('config', '--local', 'rerere.enabled', 'true')
        self.git('config', '--local', 'rerere.autoupdate', 'false')
        print('Conflict reuse enabled for this checkout. Reused resolutions remain unstaged for review.')

    def save(self, state):
        self.directory.mkdir(parents=True, exist_ok=True)
        self.state_file.write_text(json.dumps(state, indent=2) + '\n')
        self.report_file.write_text(self.report(state))

    def load(self):
        try:
            state = json.loads(self.state_file.read_text())
        except (OSError, ValueError) as error:
            raise SyncError('No usable sync state. Start with preview or prepare.') from error
        if state.get('branch') != self.branch():
            raise SyncError('The saved sync belongs to another branch. Switch back to it before continuing.')
        return state

    def summary(self):
        if not self.args.no_fetch:
            for remote in ['origin', 'upstream']:
                print(f'Fetching {remote}...', flush=True)
                self.git('-c', 'core.sshCommand=ssh -o BatchMode=yes -o ConnectTimeout=20', 'fetch', '--no-tags', remote)
        base = self.git('rev-parse', '--verify', self.args.base + '^{commit}')
        upstream = self.git('rev-parse', '--verify', 'upstream/main^{commit}')
        commits = self.git('log', '--reverse', '--format=%h %s', f'{base}..{upstream}').splitlines()
        return {'base': self.args.base, 'base_sha': base, 'upstream_sha': upstream, 'commits': commits, 'checks': []}

    @staticmethod
    def report(state):
        lines = [
            f"Sync EventSchedule through `{state['upstream_sha'][:12]}` for development testing before merging into main.",
            '', f"Starting point: `{state['base']}` (`{state['base_sha'][:12]}`).",
            '', 'Upstream commits missing from the starting point:',
        ]
        lines += [f'- {commit}' for commit in state['commits']] or ['- None.']
        lines += ['', 'Validation:']
        lines += [f"- {item['name']}: {item['status']}" for item in state['checks']] or ['- Not run yet.']
        lines += ['', 'These are focused checks, not a full-suite result. Interactive browser testing and review remain required. Keep the PR in draft until testing is complete. Main was not modified.', '']
        return '\n'.join(lines)

    def preview(self):
        state = self.summary()
        print(self.report(state))
        print('Preview only: no branch, working files, or pull request changed.')

    def prepare(self):
        self.require_clean()
        if self.git('config', '--bool', 'rerere.enabled', allowed=(0, 1)) != 'true':
            raise SyncError('Run setup first to enable conflict reuse in this checkout.')
        if self.git('config', '--bool', 'rerere.autoupdate', allowed=(0, 1)) != 'false':
            raise SyncError('Run setup first so reused resolutions stay unstaged for review.')
        state = self.summary()
        if not state['commits']:
            print('The starting point already includes upstream/main. Nothing to prepare.')
            return
        today = datetime.datetime.now().astimezone().date().isoformat()
        branch = self.args.branch or f"upstream-sync/{today}-{state['upstream_sha'][:8]}"
        self.git('check-ref-format', '--branch', branch)
        if branch in ('main', self.args.base.removeprefix('origin/')):
            raise SyncError('Use a new sync branch; main and the starting branch are protected.')
        exists = self.run(['git', 'show-ref', '--verify', '--quiet', f'refs/heads/{branch}'], allowed=(0, 1)).returncode == 0
        if exists:
            raise SyncError(f'{branch} already exists. It will not be reset or overwritten.')
        required = ['tools/upstream-sync.py', *FOCUSED_TESTS]
        for path in required:
            exists = self.run(['git', 'cat-file', '-e', f"{state['base_sha']}:{path}"], allowed=(0, 1, 128)).returncode == 0
            if not exists:
                raise SyncError(f'The starting point lacks {path}. Choose a base that includes the sync improvements; no branch was created.')
        if not self.git('merge-base', state['base_sha'], state['upstream_sha'], allowed=(0, 1)):
            raise SyncError('The starting point and upstream have no shared history; no branch was created.')
        self.git('switch', '-c', branch, state['base_sha'])
        state['branch'] = branch
        state['phase'] = 'merging'
        self.save(state)
        self.git('merge', '--no-commit', '--no-ff', state['upstream_sha'], allowed=(0, 1))
        if not self.active_merge():
            raise SyncError('The merge did not start. Inspect Git status; no branch was reset.')
        self.finish(state, merging=True)

    def conflicts(self):
        return self.git('diff', '--name-only', '--diff-filter=U').splitlines()

    def runtime(self):
        if self.args.runtime == 'ddev' or (self.args.runtime == 'auto' and (self.root / '.ddev/config.yaml').exists() and shutil.which('ddev')):
            if not shutil.which('ddev'):
                raise SyncError('DDEV is not installed. Select --runtime local if appropriate.')
            return ['ddev', 'exec']
        return []

    def check_command(self, state, name, command):
        self.directory.mkdir(parents=True, exist_ok=True)
        logfile = self.directory / (name.replace(' ', '-') + '.log')
        print(f'Running {name}; log: {logfile}', flush=True)
        with logfile.open('w') as output:
            result = subprocess.run(command, cwd=self.root, stdout=output, stderr=subprocess.STDOUT)
        state['checks'].append({'name': name, 'status': 'passed' if result.returncode == 0 else 'failed'})
        self.save(state)
        if result.returncode:
            raise SyncError(f'{name} failed. Review {logfile}; nothing was committed or pushed.')

    def finish(self, state, merging):
        state['checks'] = []
        state.pop('checked_tree', None)
        self.save(state)
        conflicts = self.conflicts()
        business = [path for path in conflicts if path != MANIFEST]
        if business:
            print('Review and resolve these files, then git add each reviewed resolution:')
            for path in conflicts:
                print(f'  {path}')
            raise SyncError('Unresolved conflicts. Run continue after reviewing and staging the resolutions.')
        runtime = self.runtime()
        if runtime:
            self.run(['ddev', 'mutagen', 'sync'])
        if conflicts:
            # Only this generated file is selected automatically. Rebuild it below.
            self.git('checkout', '--ours', '--', MANIFEST)
        self.check_command(state, 'sitemap generation', runtime + ['env', 'IS_NEXUS=true', 'php', 'artisan', 'sitemap:lastmod'])
        if merging:
            self.git('add', '--', MANIFEST)
        elif self.git('status', '--porcelain', '--', MANIFEST):
            raise SyncError('Sitemap dates changed after the commit. Review and commit the generated manifest, then run check again.')
        if self.conflicts():
            raise SyncError('Some resolutions remain unstaged. Review and git add them before continuing.')
        if self.git('diff', '--name-only'):
            raise SyncError('Unstaged tracked edits remain. Review and stage them before checks.')
        if merging:
            self.git('rerere')
        missing = [path for path in FOCUSED_TESTS if not (self.root / path).is_file()]
        if missing:
            raise SyncError('Focused tests are missing: ' + ', '.join(missing) + '. Choose a base that includes the sync improvements.')
        input_tree = self.git('write-tree')
        self.check_command(state, 'frontend build', runtime + ['npm', 'run', 'build'])
        self.check_command(state, 'focused PHP tests', runtime + ['php', '-d', 'opcache.enable_cli=1', '-d', 'opcache.memory_consumption=512', '-d', 'opcache.interned_strings_buffer=64', '-d', 'opcache.max_accelerated_files=30000', 'vendor/bin/phpunit', *FOCUSED_TESTS])
        if self.git('diff', '--name-only'):
            raise SyncError('Checks changed tracked files. Review those changes and run checks again.')
        if self.git('write-tree') != input_tree:
            raise SyncError('The tracked tree changed during checks. Review those changes and run checks again.')
        state['checked_tree'] = input_tree
        state['phase'] = 'checked'
        self.save(state)
        print(f'Focused checks passed. PR notes: {self.report_file}')
        if merging:
            print('Review the diff and commit the merge, then run check again to verify post-commit sitemap dates.')
        print('No commit, push, or merge into main was performed.')

    def resume(self):
        state = self.load()
        if not self.active_merge() or state.get('phase') not in ('merging', 'checked'):
            raise SyncError('No merge started by this tool is active. Use check for a committed branch.')
        self.finish(state, merging=True)

    def check(self):
        self.require_clean()
        state = self.summary() if not self.state_file.exists() else self.load()
        state['branch'] = self.branch()
        self.finish(state, merging=False)

    def draft_pr(self):
        self.require_clean()
        state = self.load()
        branch = self.branch()
        if branch == 'main' or state.get('phase') != 'checked' or state.get('checked_tree') != self.git('rev-parse', 'HEAD^{tree}'):
            raise SyncError('The committed tree must match successful checks on a non-main branch. Run check first.')
        if not state['checks'] or any(item['status'] != 'passed' for item in state['checks']):
            raise SyncError('All focused checks must pass before publishing a draft.')
        for ref in [state['base_sha'], state['upstream_sha']]:
            if self.run(['git', 'merge-base', '--is-ancestor', ref, 'HEAD'], allowed=(0, 1)).returncode:
                raise SyncError('This branch does not include the recorded starting point and upstream snapshot.')
        if not shutil.which('gh'):
            raise SyncError('GitHub CLI is required for draft-pr.')
        self.run(['gh', 'auth', 'status'])
        existing = json.loads(self.run(['gh', 'pr', 'list', '--head', branch, '--base', 'main', '--state', 'open', '--json', 'url,isDraft']).stdout)
        if existing and not existing[0]['isDraft']:
            raise SyncError('The existing PR is not a draft; it will not be changed automatically.')
        self.git('push', 'origin', f'HEAD:refs/heads/{branch}')
        if existing:
            print(self.run(['gh', 'pr', 'edit', existing[0]['url'], '--body-file', str(self.report_file)]).stdout.strip())
        else:
            print(self.run(['gh', 'pr', 'create', '--base', 'main', '--head', branch, '--draft', '--title', 'Sync EventSchedule upstream for development testing', '--body-file', str(self.report_file)]).stdout.strip())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', nargs='?', default='preview', choices=['setup', 'preview', 'prepare', 'continue', 'check', 'draft-pr'])
    parser.add_argument('--base', default='origin/main', help='Starting ref; defaults to refreshed origin/main.')
    parser.add_argument('--branch', help='New branch name for prepare.')
    parser.add_argument('--runtime', default='auto', choices=['auto', 'ddev', 'local'])
    parser.add_argument('--no-fetch', action='store_true', help='Use existing remote refs for preview/prepare/check.')
    args = parser.parse_args()
    try:
        root = subprocess.run(['git', 'rev-parse', '--show-toplevel'], text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout.strip()
        sync = Sync(root, args)
        {'setup': sync.setup, 'preview': sync.preview, 'prepare': sync.prepare, 'continue': sync.resume, 'check': sync.check, 'draft-pr': sync.draft_pr}[args.action]()
    except (SyncError, OSError, subprocess.CalledProcessError) as error:
        print(f'Stopped: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
