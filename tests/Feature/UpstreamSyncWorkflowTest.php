<?php

namespace Tests\Feature;

use App\Modules\EventWillowSync\Workflow;
use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class UpstreamSyncWorkflowTest extends TestCase
{
    private string $root;

    private string $base;

    private FixtureSyncWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/eventwillow sync '.Str::random(12);
        File::ensureDirectoryExists($this->root);
        $this->git(['init', '-b', 'main']);
        $this->git(['config', 'commit.gpgsign', 'false']);
        $this->git(['config', 'user.name', 'Sync Test']);
        $this->git(['config', 'user.email', 'sync@example.test']);
        File::put($this->root.'/shared.txt', "base\n");
        $this->commit('base');
        $this->git(['switch', '-c', 'upstream-fixture']);
        File::put($this->root.'/incoming.txt', "upstream\n");
        $this->commit('incoming');
        $this->git(['update-ref', 'refs/remotes/upstream/main', $this->git(['rev-parse', 'HEAD'])]);
        $this->git(['switch', 'main']);
        foreach ([...Workflow::REQUIRED_SOURCE, ...Workflow::FOCUSED_TESTS, Workflow::MANIFEST] as $path) {
            File::ensureDirectoryExists(dirname($this->root.'/'.$path));
            File::put($this->root.'/'.$path, "fixture\n");
        }
        $this->commit('fork infrastructure');
        $this->base = $this->git(['rev-parse', 'HEAD']);
        $this->git(['update-ref', 'refs/remotes/origin/main', $this->base]);
        $this->workflow = $this->makeWorkflow();
        $this->workflow->setup();
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            File::deleteDirectory($this->root);
        }
        parent::tearDown();
    }

    private function makeWorkflow(string $branch = 'sync-test'): FixtureSyncWorkflow
    {
        return new FixtureSyncWorkflow($this->root, 'origin/main', $branch, true);
    }

    private function git(array $arguments): string
    {
        $result = Process::path($this->root)->forever()->run(['git', ...$arguments]);
        $this->assertSame(0, $result->exitCode(), $result->errorOutput());

        return trim($result->output());
    }

    private function commit(string $message): void
    {
        $this->git(['add', '.']);
        $this->git(['commit', '-m', $message]);
    }

    private function refused(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected sync refusal: '.$message);
        } catch (RuntimeException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
    }

    private function introduceConflict(): void
    {
        $this->git(['switch', 'upstream-fixture']);
        File::put($this->root.'/shared.txt', "upstream choice\n");
        $this->commit('upstream conflict');
        $this->git(['update-ref', 'refs/remotes/upstream/main', $this->git(['rev-parse', 'HEAD'])]);
        $this->git(['switch', 'main']);
        File::put($this->root.'/shared.txt', "fork choice\n");
        $this->commit('fork conflict');
        $this->base = $this->git(['rev-parse', 'HEAD']);
        $this->git(['update-ref', 'refs/remotes/origin/main', $this->base]);
    }

    public function test_setup_keeps_reused_resolutions_unstaged(): void
    {
        $this->assertSame('true', $this->git(['config', '--bool', 'rerere.enabled']));
        $this->assertSame('false', $this->git(['config', '--bool', 'rerere.autoupdate']));
    }

    public function test_preview_preserves_branch_and_working_files(): void
    {
        $this->workflow->preview();
        $this->assertSame('main', $this->git(['branch', '--show-current']));
        $this->assertSame('', $this->git(['status', '--porcelain']));
        $this->assertFileDoesNotExist($this->workflow->statePath());
    }

    public function test_dirty_checkout_is_refused_without_switching(): void
    {
        File::put($this->root.'/shared.txt', "my work\n");
        $this->refused(fn () => $this->workflow->prepare(), 'must be clean');
        $this->assertSame('main', $this->git(['branch', '--show-current']));
        $this->assertSame("my work\n", File::get($this->root.'/shared.txt'));
    }

    public function test_main_cannot_be_a_sync_target(): void
    {
        $this->refused(fn () => $this->makeWorkflow('main')->prepare(), 'protected');
        $this->assertSame($this->base, $this->git(['rev-parse', 'main']));
    }

    public function test_validation_cannot_change_generated_files_on_main(): void
    {
        $this->refused(fn () => $this->workflow->check(), 'non-main branch');
        $this->assertSame([], $this->workflow->checked);
        $this->assertSame("fixture\n", File::get($this->root.'/'.Workflow::MANIFEST));
    }

    public function test_existing_branches_are_not_overwritten(): void
    {
        $this->git(['branch', 'sync-test']);
        $this->refused(fn () => $this->workflow->prepare(), 'already exists');
        $this->assertSame($this->base, $this->git(['rev-parse', 'sync-test']));
    }

    public function test_clean_merge_is_staged_but_not_committed_and_main_is_unchanged(): void
    {
        $this->workflow->prepare();
        $this->assertSame('sync-test', $this->git(['branch', '--show-current']));
        $this->assertSame($this->base, $this->git(['rev-parse', 'HEAD']));
        $this->assertSame($this->base, $this->git(['rev-parse', 'main']));
        $this->assertTrue($this->workflow->activeMerge());
        $this->assertSame('checked', $this->workflow->load()['phase']);
        $this->assertStringContainsString('incoming.txt', $this->git(['diff', '--cached', '--name-only']));
    }

    public function test_conflicts_stop_before_checks_and_continue_after_review(): void
    {
        $this->introduceConflict();
        $this->refused(fn () => $this->workflow->prepare(), 'Unresolved conflicts');
        $this->assertSame([], $this->workflow->checked);
        $this->assertContains('shared.txt', $this->workflow->conflicts());
        File::put($this->root.'/shared.txt', "reviewed choice\n");
        $this->git(['add', 'shared.txt']);
        $this->workflow->resume();
        $this->assertSame('checked', $this->workflow->load()['phase']);
        $this->assertSame($this->base, $this->git(['rev-parse', 'main']));
    }

    public function test_only_the_generated_manifest_is_resolved_automatically(): void
    {
        $this->git(['switch', 'upstream-fixture']);
        File::ensureDirectoryExists(dirname($this->root.'/'.Workflow::MANIFEST));
        File::put($this->root.'/'.Workflow::MANIFEST, "upstream manifest\n");
        $this->commit('upstream manifest');
        $this->git(['update-ref', 'refs/remotes/upstream/main', $this->git(['rev-parse', 'HEAD'])]);
        $this->git(['switch', 'main']);
        $this->workflow->callback = function (array &$state, string $name) {
            if ($name === 'sitemap generation') {
                File::put($this->root.'/'.Workflow::MANIFEST, "regenerated dates\n");
            }
        };
        $this->workflow->prepare();
        $this->assertSame([], $this->workflow->conflicts());
        $this->assertSame("regenerated dates\n", File::get($this->root.'/'.Workflow::MANIFEST));
        $this->assertSame('sitemap generation', $this->workflow->load()['checks'][0]['name']);
    }

    public function test_generated_php_manifest_is_restored_even_when_other_files_conflict(): void
    {
        $this->introduceConflict();
        $this->git(['switch', 'upstream-fixture']);
        File::ensureDirectoryExists(dirname($this->root.'/'.Workflow::MANIFEST));
        File::put($this->root.'/'.Workflow::MANIFEST, "upstream manifest\n");
        $this->commit('upstream manifest');
        $this->git(['update-ref', 'refs/remotes/upstream/main', $this->git(['rev-parse', 'HEAD'])]);
        $this->git(['switch', 'main']);
        $this->refused(fn () => $this->workflow->prepare(), 'Unresolved conflicts');
        $this->assertSame(['shared.txt'], $this->workflow->conflicts());
        $this->assertSame("fixture\n", File::get($this->root.'/'.Workflow::MANIFEST));
        $this->assertSame([], $this->workflow->checked);
    }

    public function test_phpunit_child_uses_test_profile_instead_of_parent_artisan_environment(): void
    {
        File::put($this->root.'/phpunit.xml', '<phpunit><php><env name="APP_ENV" value="testing"/><env name="APP_TESTING" value="true"/></php></phpunit>');
        $parent = $_SERVER['APP_ENV'] ?? null;
        $_SERVER['APP_ENV'] = 'local';
        try {
            $probe = new EnvironmentProbeWorkflow($this->root);
            $state = ['version' => 2, 'base' => 'main', 'base_sha' => $this->base, 'upstream_sha' => $this->base, 'commits' => [], 'checks' => []];
            $result = $probe->probe($state);
            $this->assertSame('testing|true', $result);
            $this->assertSame('passed', $state['checks'][0]['status']);
        } finally {
            if ($parent === null) {
                unset($_SERVER['APP_ENV']);
            } else {
                $_SERVER['APP_ENV'] = $parent;
            }
        }
    }

    public function test_continue_requires_the_commands_own_active_merge(): void
    {
        $this->refused(fn () => $this->workflow->resume(), 'No usable sync state');
    }

    public function test_failed_checks_do_not_record_a_successful_tree(): void
    {
        $this->workflow->callback = fn () => throw new RuntimeException('fixture build failure');
        $this->refused(fn () => $this->workflow->prepare(), 'fixture build failure');
        $this->assertArrayNotHasKey('checked_tree', $this->workflow->load());
        $this->assertSame($this->base, $this->git(['rev-parse', 'main']));
    }

    public function test_missing_tests_are_not_silently_skipped(): void
    {
        File::delete($this->root.'/'.Workflow::FOCUSED_TESTS[0]);
        $this->commit('remove test');
        $this->git(['update-ref', 'refs/remotes/origin/main', $this->git(['rev-parse', 'HEAD'])]);
        $this->refused(fn () => $this->workflow->prepare(), 'starting point lacks');
        $this->assertSame('main', $this->git(['branch', '--show-current']));
    }

    public function test_publication_refuses_changes_after_validation_before_pushing(): void
    {
        $this->workflow->prepare();
        $this->git(['commit', '-m', 'reviewed merge']);
        File::put($this->root.'/shared.txt', "untested change\n");
        $this->commit('untested change');
        $this->refused(fn () => $this->workflow->draftPr(), 'match successful checks');
    }

    public function test_recorded_resolutions_are_reused_but_still_need_staging(): void
    {
        $this->introduceConflict();
        $this->refused(fn () => $this->workflow->prepare(), 'Unresolved conflicts');
        File::put($this->root.'/shared.txt', "reviewed choice\n");
        $this->git(['add', 'shared.txt']);
        $this->workflow->resume();
        $this->git(['commit', '-m', 'reviewed merge']);
        $this->git(['switch', 'main']);
        $next = $this->makeWorkflow('another-sync');
        $this->refused(fn () => $next->prepare(), 'Unresolved conflicts');
        $this->assertSame("reviewed choice\n", File::get($this->root.'/shared.txt'));
        $this->assertContains('shared.txt', $next->conflicts());
    }

    public function test_post_commit_sitemap_changes_require_review(): void
    {
        $this->workflow->prepare();
        $this->git(['commit', '-m', 'reviewed merge']);
        $this->workflow->callback = fn () => File::put($this->root.'/'.Workflow::MANIFEST, "corrected dates\n");
        $this->refused(fn () => $this->workflow->check(), 'Sitemap dates changed');
        $this->assertArrayNotHasKey('checked_tree', $this->workflow->load());
    }

    public function test_changes_staged_during_checks_are_not_marked_validated(): void
    {
        $this->workflow->callback = function (array &$state, string $name) {
            if ($name === 'frontend build') {
                File::put($this->root.'/shared.txt', "changed during checks\n");
                $this->git(['add', 'shared.txt']);
            }
        };
        $this->refused(fn () => $this->workflow->prepare(), 'tree changed during checks');
        $this->assertArrayNotHasKey('checked_tree', $this->workflow->load());
    }

    public function test_report_distinguishes_focused_checks_from_full_suite(): void
    {
        $this->workflow->prepare();
        $report = File::get($this->workflow->reportPath());
        $this->assertStringContainsString('not a full-suite result', $report);
        $this->assertStringContainsString('Interactive browser testing', $report);
    }

    public function test_artisan_registers_the_command_and_rejects_unknown_actions(): void
    {
        $this->artisan('upstream:sync', ['action' => 'invalid'])
            ->expectsOutputToContain('Choose setup, preview, prepare, continue, check, or draft-pr.')
            ->assertFailed();
    }

    public function test_old_python_validation_cannot_publish_through_artisan(): void
    {
        File::ensureDirectoryExists(dirname($this->workflow->statePath()));
        File::put($this->workflow->statePath(), json_encode(['branch' => 'main', 'phase' => 'checked']));
        $this->refused(fn () => $this->workflow->draftPr(), 'retired Python helper');
    }
}

class FixtureSyncWorkflow extends Workflow
{
    public ?Closure $callback = null;

    public array $checked = [];

    protected function checkCommand(array &$state, string $name, array $command): void
    {
        $this->checked[] = $name;
        if ($this->callback) {
            ($this->callback)($state, $name, $command);
        }
        $state['checks'][] = ['name' => $name, 'status' => 'passed'];
        $this->save($state);
    }
}

class EnvironmentProbeWorkflow extends Workflow
{
    public function probe(array &$state): string
    {
        $this->save($state);
        parent::checkCommand($state, 'focused PHP tests', [PHP_BINARY, '-r', 'echo getenv("APP_ENV")."|".getenv("APP_TESTING");']);

        return File::get(dirname($this->statePath()).'/focused-PHP-tests.log');
    }
}
