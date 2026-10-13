# Upstream EventSchedule syncs

Run `python3 tools/upstream-sync.py` from this checkout. Its default action is preview: fetch origin and upstream, then show the commits missing from `origin/main`. It does not switch branches, merge files, commit, or publish anything.

## Configure this checkout

```sh
python3 tools/upstream-sync.py setup
```

This enables Git's `rerere` conflict-resolution memory locally and disables automatic staging. When the same conflict returns, Git can reuse a previously recorded resolution in the working file. Review it and stage it yourself. Resolutions are learned during future merges; enabling this does not retrospectively capture old merges. Settings and resolution records belong to the checkout and are not distributed by a normal clone.

## Prepare a sync

```sh
python3 tools/upstream-sync.py preview
python3 tools/upstream-sync.py prepare
```

Preparation requires a clean checkout, configured conflict reuse, a shared history with upstream, and a starting ref that contains this tool and the focused tests. It creates a new `upstream-sync/<date>-<upstream SHA>` branch from refreshed `origin/main` and starts an uncommitted merge. Existing branches are never reset. Main is never checked out or modified by the command.

Use `--branch upstream-sync/my-name` to choose a new branch name, `--base <ref>` for another starting point, and `--no-fetch` to use existing remote refs. Until the sync-improvement commits have been merged into main, use `--base sync-improvements/translation-layer` so the starting point includes this tool, the branding module, and its tests.

```sh
python3 tools/upstream-sync.py prepare --base sync-improvements/translation-layer
```

## Resolve and validate

The command stops when ordinary files conflict. Reused resolutions remain unmerged in the index until you review and stage them too.

```sh
# Review and edit each conflicted file, then stage the reviewed resolution.
git add <reviewed-file>
python3 tools/upstream-sync.py continue
```

Once ordinary conflicts are resolved, the tool rebuilds `config/sitemap_lastmod.php`. If only that generated file conflicts, it uses the starting branch's copy temporarily, regenerates it through Laravel, and stages the result. No other conflict is selected automatically.

The checks are sitemap generation, `npm run build` (including Vue binding checks), and focused PHP tests covering branding translations, administrator overrides, logos/theme defaults, existing translation behavior, theme boundaries, and the EventWillow public API. PHP OPcache settings are provided for the test process. The existing test bootstrap protects the dedicated test database; the command never migrates the development database or installs dependencies.

DDEV is selected automatically when available for a configured DDEV checkout; it synchronizes files first. Use `--runtime ddev` or `--runtime local` to choose explicitly. Composer and npm dependencies must already be installed. Failed checks stop the workflow and point to logs under `.git/eventwillow-sync/`.

After checks pass, review the diff and commit the merge yourself. Then verify the committed state:

```sh
git diff --cached
# After reviewing the changes:
git commit -m 'Merge EventSchedule upstream for development testing'
python3 tools/upstream-sync.py check
```

Post-commit sitemap dates can differ because Git follows the upstream parent for unchanged upstream views. If `check` reports a changed manifest, review and commit those generated dates, then rerun `check`. Any later source changes also require checks again.

## Optional draft pull request

```sh
python3 tools/upstream-sync.py draft-pr
```

This explicit action pushes the current branch to origin and creates or updates a draft PR into main using the prepared notes. It requires a clean, committed, non-main branch whose tree matches successful checks and includes the recorded starting point and upstream snapshot. It refuses to modify an existing non-draft PR. GitHub CLI authentication and push access, including permission to update upstream workflow files, are required. No force push or PR merge is performed.

These checks do not establish a green full-suite baseline or confirm interactive browser behavior. Review the update and test it in development before merging into main. Diagnosing existing failures and fixing CI coverage remains a separate step.

## Verify the tool itself

```sh
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s tools -p 'test_upstream_sync.py'
```

These tests use disposable local Git repositories, including paths with spaces. They do not access the network, application database, GitHub, or this checkout's branches.
