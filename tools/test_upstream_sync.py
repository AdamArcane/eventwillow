"""Exercise sync safety in disposable local Git repositories; no network or app DB."""
import argparse
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('upstream_sync', Path(__file__).with_name('upstream-sync.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class SyncWorkflowTest(unittest.TestCase):
    def setUp(self):
        self.scratch = tempfile.TemporaryDirectory(prefix='eventwillow sync test ')
        self.root = Path(self.scratch.name)
        self.git('init', '-b', 'main')
        self.git('config', 'commit.gpgsign', 'false')
        self.git('config', 'user.name', 'Sync Test')
        self.git('config', 'user.email', 'sync@example.test')
        (self.root / 'shared.txt').write_text('base\n')
        self.git('add', '.')
        self.git('commit', '-m', 'base')
        self.base = self.git('rev-parse', 'HEAD')
        self.git('update-ref', 'refs/remotes/origin/main', self.base)
        self.git('switch', '-c', 'upstream-fixture')
        (self.root / 'incoming.txt').write_text('upstream\n')
        self.git('add', '.')
        self.git('commit', '-m', 'incoming')
        self.upstream = self.git('rev-parse', 'HEAD')
        self.git('update-ref', 'refs/remotes/upstream/main', self.upstream)
        self.git('switch', 'main')
        self.args = argparse.Namespace(base='origin/main', no_fetch=True, branch='sync-test', runtime='local')
        self.sync = module.Sync(self.root, self.args)
        self.sync.setup()
        for path in ['tools/upstream-sync.py', *module.FOCUSED_TESTS]:
            target = self.root / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text('fixture\n')
        target = self.root / module.MANIFEST
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text('manifest\n')
        self.git('add', '.')
        self.git('commit', '-m', 'fork test infrastructure')
        self.base = self.git('rev-parse', 'HEAD')
        self.git('update-ref', 'refs/remotes/origin/main', self.base)

    def tearDown(self):
        self.scratch.cleanup()

    def git(self, *args):
        return subprocess.run(['git', *args], cwd=self.root, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout.strip()

    @staticmethod
    def passing_check(state, name, command):
        state['checks'].append({'name': name, 'status': 'passed'})

    def prepare(self):
        with patch.object(self.sync, 'check_command', side_effect=self.passing_check):
            self.sync.prepare()

    def conflict(self):
        self.git('switch', 'upstream-fixture')
        (self.root / 'shared.txt').write_text('upstream choice\n')
        self.git('add', '.')
        self.git('commit', '-m', 'upstream conflict')
        self.git('update-ref', 'refs/remotes/upstream/main', self.git('rev-parse', 'HEAD'))
        self.git('switch', 'main')
        (self.root / 'shared.txt').write_text('fork choice\n')
        self.git('add', '.')
        self.git('commit', '-m', 'fork conflict')
        self.base = self.git('rev-parse', 'HEAD')
        self.git('update-ref', 'refs/remotes/origin/main', self.base)

    def test_setup_keeps_reused_resolutions_unstaged(self):
        self.assertEqual('true', self.git('config', '--bool', 'rerere.enabled'))
        self.assertEqual('false', self.git('config', '--bool', 'rerere.autoupdate'))

    def test_preview_preserves_branch_and_working_files(self):
        with patch('builtins.print'):
            self.sync.preview()
        self.assertEqual('main', self.git('branch', '--show-current'))
        self.assertEqual('', self.git('status', '--porcelain'))
        self.assertFalse(self.sync.state_file.exists())

    def test_dirty_checkout_is_refused_without_switching(self):
        (self.root / 'shared.txt').write_text('my work\n')
        with self.assertRaisesRegex(module.SyncError, 'clean'):
            self.prepare()
        self.assertEqual('main', self.git('branch', '--show-current'))
        self.assertEqual('my work\n', (self.root / 'shared.txt').read_text())

    def test_main_cannot_be_a_sync_target(self):
        self.args.branch = 'main'
        with self.assertRaisesRegex(module.SyncError, 'protected'):
            self.prepare()
        self.assertEqual(self.base, self.git('rev-parse', 'main'))

    def test_an_existing_branch_is_not_overwritten(self):
        self.git('branch', 'sync-test')
        with self.assertRaisesRegex(module.SyncError, 'already exists'):
            self.prepare()
        self.assertEqual(self.base, self.git('rev-parse', 'sync-test'))

    def test_clean_merge_is_staged_but_not_committed_and_main_is_unchanged(self):
        self.prepare()
        self.assertEqual('sync-test', self.git('branch', '--show-current'))
        self.assertEqual(self.base, self.git('rev-parse', 'HEAD'))
        self.assertEqual(self.base, self.git('rev-parse', 'main'))
        self.assertTrue(self.sync.active_merge())
        self.assertEqual('checked', self.sync.load()['phase'])
        self.assertIn('incoming.txt', self.git('diff', '--cached', '--name-only'))

    def test_conflicts_stop_before_checks_and_can_continue_after_review(self):
        self.conflict()
        with patch.object(self.sync, 'check_command') as checks:
            with self.assertRaisesRegex(module.SyncError, 'Unresolved conflicts'):
                self.sync.prepare()
            checks.assert_not_called()
        self.assertIn('shared.txt', self.sync.conflicts())
        (self.root / 'shared.txt').write_text('reviewed choice\n')
        self.git('add', 'shared.txt')
        with patch.object(self.sync, 'check_command', side_effect=self.passing_check):
            self.sync.resume()
        self.assertEqual('checked', self.sync.load()['phase'])
        self.assertEqual(self.base, self.git('rev-parse', 'main'))

    def test_only_the_generated_manifest_is_resolved_automatically(self):
        self.git('switch', 'upstream-fixture')
        (self.root / module.MANIFEST).parent.mkdir(parents=True, exist_ok=True)
        (self.root / module.MANIFEST).write_text('upstream manifest\n')
        self.git('add', '.')
        self.git('commit', '-m', 'upstream manifest')
        self.git('update-ref', 'refs/remotes/upstream/main', self.git('rev-parse', 'HEAD'))
        self.git('switch', 'main')
        self.prepare()
        self.assertFalse(self.sync.conflicts())
        self.assertEqual('manifest\n', (self.root / module.MANIFEST).read_text())
        self.assertEqual('sitemap generation', self.sync.load()['checks'][0]['name'])

    def test_continue_requires_the_tool_own_active_merge(self):
        with self.assertRaisesRegex(module.SyncError, 'No usable sync state'):
            self.sync.resume()

    def test_failed_checks_do_not_record_a_successful_tree(self):
        def fail(state, name, command):
            state['checks'].append({'name': name, 'status': 'failed'})
            self.sync.save(state)
            raise module.SyncError('fixture build failure')
        with patch.object(self.sync, 'check_command', side_effect=fail):
            with self.assertRaisesRegex(module.SyncError, 'fixture build failure'):
                self.sync.prepare()
        self.assertNotIn('checked_tree', self.sync.load())
        self.assertEqual(self.base, self.git('rev-parse', 'main'))

    def test_missing_tests_are_not_silently_skipped(self):
        target = self.root / module.FOCUSED_TESTS[0]
        target.unlink()
        self.git('add', '.')
        self.git('commit', '-m', 'remove test fixture')
        self.git('update-ref', 'refs/remotes/origin/main', self.git('rev-parse', 'HEAD'))
        with self.assertRaisesRegex(module.SyncError, 'starting point lacks'):
            self.prepare()

    def test_draft_publication_refuses_changes_since_validation_before_pushing(self):
        self.prepare()
        self.git('commit', '-m', 'reviewed merge')
        (self.root / 'shared.txt').write_text('untested change\n')
        self.git('add', '.')
        self.git('commit', '-m', 'untested change')
        with self.assertRaisesRegex(module.SyncError, 'match successful checks'):
            self.sync.draft_pr()

    def test_recorded_resolutions_are_reused_but_still_need_review_and_staging(self):
        self.conflict()
        with self.assertRaises(module.SyncError):
            self.prepare()
        (self.root / 'shared.txt').write_text('reviewed choice\n')
        self.git('add', 'shared.txt')
        with patch.object(self.sync, 'check_command', side_effect=self.passing_check):
            self.sync.resume()
        self.git('commit', '-m', 'reviewed merge')
        self.git('switch', 'main')
        self.args.branch = 'another-sync'
        with self.assertRaisesRegex(module.SyncError, 'Unresolved conflicts'):
            self.prepare()
        self.assertEqual('reviewed choice\n', (self.root / 'shared.txt').read_text())
        self.assertIn('shared.txt', self.sync.conflicts())
        self.assertEqual(self.base, self.git('rev-parse', 'main'))

    def test_post_commit_manifest_changes_require_review_before_validation(self):
        self.prepare()
        self.git('commit', '-m', 'reviewed merge')
        def changed_manifest(state, name, command):
            (self.root / module.MANIFEST).write_text('corrected dates\n')
            self.passing_check(state, name, command)
        with patch.object(self.sync, 'check_command', side_effect=changed_manifest):
            with self.assertRaisesRegex(module.SyncError, 'Sitemap dates changed'):
                self.sync.check()
        self.assertNotIn('checked_tree', self.sync.load())

    def test_changes_staged_during_checks_are_not_marked_validated(self):
        def altered_tree(state, name, command):
            self.passing_check(state, name, command)
            if name == 'frontend build':
                (self.root / 'shared.txt').write_text('changed during checks\n')
                self.git('add', 'shared.txt')
        with patch.object(self.sync, 'check_command', side_effect=altered_tree):
            with self.assertRaisesRegex(module.SyncError, 'tree changed during checks'):
                self.sync.prepare()
        self.assertNotIn('checked_tree', self.sync.load())

    def test_report_distinguishes_focused_checks_from_full_suite(self):
        self.prepare()
        report = self.sync.report_file.read_text()
        self.assertIn('not a full-suite result', report)
        self.assertIn('Interactive browser testing', report)
        self.assertIn('incoming', report)


if __name__ == '__main__':
    unittest.main()
