# Upstream EventSchedule syncs

Run `php artisan upstream:sync` through the application’s normal console environment. In this DDEV checkout, use `ddev artisan upstream:sync`. Its default action is preview: fetch origin and upstream, then show the commits missing from `origin/main`. It does not switch branches, merge files, commit, or publish anything.

## Configure this checkout

```sh
php artisan upstream:sync setup
```

This enables Git's `rerere` conflict-resolution memory locally and disables automatic staging. When the same conflict returns, Git can reuse a previously recorded resolution in the working file. Review it and stage it yourself. Resolutions are learned during future merges; enabling this does not retrospectively capture old merges. Settings and resolution records belong to the checkout and are not distributed by a normal clone.

## Prepare a sync

```sh
php artisan upstream:sync preview
php artisan upstream:sync prepare
```

Preparation requires a clean checkout, configured conflict reuse, a shared history with upstream, and a starting ref that contains the EventWillow sync module and the focused tests. It creates a new `upstream-sync/<date>-<upstream SHA>` branch from refreshed `origin/main` and starts an uncommitted merge. Existing branches are never reset. Main is never checked out or modified by the command.

Use `--branch upstream-sync/my-name` to choose a new branch name, `--base <ref>` for another starting point, and `--no-fetch` to use existing remote refs. Until the sync-improvement commits have been merged into main, use `--base sync-improvements/translation-layer` so the starting point includes the sync module, the branding module, and its tests.

```sh
php artisan upstream:sync prepare --base sync-improvements/translation-layer
```

## Resolve and validate

The command stops when ordinary files conflict. Reused resolutions remain unmerged in the index until you review and stage them too.

```sh
# Review and edit each conflicted file, then stage the reviewed resolution.
git add <reviewed-file>
php artisan upstream:sync continue
```

If the generated `config/sitemap_lastmod.php` conflicts, the command restores and stages the starting branch's copy temporarily before stopping, so that PHP file will not block the next Artisan invocation. Once ordinary conflicts are resolved, it regenerates the manifest through Laravel and stages the result. No other conflict is selected automatically.

The checks are sitemap generation, `npm run build` (including Vue binding checks), and focused PHP tests covering the sync workflow itself, branding translations, administrator overrides, logos/theme defaults, existing translation behavior, theme boundaries, and the EventWillow public API. PHP OPcache settings are provided for the test process. Its environment comes from `phpunit.xml`, so the parent Artisan application’s local environment cannot accidentally enable CSRF or override the test profile. The existing test bootstrap protects the dedicated test database; the command never migrates the development database or installs dependencies.

All subprocesses run in the same environment as Artisan. For this project, use `ddev artisan upstream:sync ...`; Git, npm, PHP, and the existing dependencies must be available there. The command uses Laravel’s process handling and the same PHP executable as Artisan. It does not introduce a separate runtime selector or install dependencies. Failed checks stop the workflow and point to logs under `.git/eventwillow-sync/`.

After checks pass, review the diff and commit the merge yourself. Then verify the committed state on the sync branch (`check` refuses to regenerate files on main):

```sh
git diff --cached
# After reviewing the changes:
git commit -m 'Merge EventSchedule upstream for development testing'
php artisan upstream:sync check
```

Post-commit sitemap dates can differ because Git follows the upstream parent for unchanged upstream views. If `check` reports a changed manifest, review and commit those generated dates, then rerun `check`. Any later source changes also require checks again.

## Optional draft pull request

```sh
php artisan upstream:sync draft-pr
```

This explicit action pushes the current branch to origin and creates or updates a draft PR into main using the prepared notes. It requires a clean, committed, non-main branch whose tree matches successful checks and includes the recorded starting point and upstream snapshot. It refuses to modify an existing non-draft PR. GitHub CLI must be installed and authenticated in the environment running Artisan. Push access must include permission to update upstream workflow files. No force push or PR merge is performed.

Artisan must be able to boot the application. If a merge leaves PHP conflict markers or breaks configuration/providers, resolve those files with Git before running `upstream:sync continue`. To abandon the merge, use `git merge --abort`. There is no separate recovery script.

These checks do not establish a green full-suite baseline or confirm interactive browser behavior. Review the update and test it in development before merging into main. Diagnosing existing failures and fixing CI coverage remains a separate step.

## Verify the command itself

```sh
php artisan test --filter=UpstreamSyncWorkflowTest
```

These PHP tests use disposable local Git repositories, including paths with spaces, and exercise Laravel command registration and workflow safeguards. They do not access the network, application database, GitHub, or this checkout's branches.

The previous Python scripts and their test runner have been removed. Old validation state cannot authorize a push or PR through Artisan; `check` regenerates validation state in the native workflow.
