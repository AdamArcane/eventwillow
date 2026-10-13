<?php

namespace App\Modules\EventWillowSync;

use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

class Workflow
{
    public const MANIFEST = 'config/sitemap_lastmod.php';

    public const REQUIRED_SOURCE = [
        'app/Modules/EventWillowSync/Commands/UpstreamSync.php',
        'app/Modules/EventWillowSync/Workflow.php',
        'app/Modules/EventWillowSync/ServiceProvider.php',
    ];

    public const FOCUSED_TESTS = [
        'tests/Feature/EventWillowBrandingTranslationsTest.php',
        'tests/Feature/EventWillowBrandingOverridesTest.php',
        'tests/Feature/EventWillowPresentationTest.php',
        'tests/Feature/TranslationOverrideTest.php',
        'tests/Feature/ThemeVariantGatingTest.php',
        'tests/Feature/UpstreamSyncWorkflowTest.php',
        'app/Modules/EventWillowPublicApi/tests/SiteApiTest.php',
    ];

    private string $directory;

    public function __construct(
        protected string $root,
        private string $base = 'origin/main',
        private ?string $targetBranch = null,
        private bool $noFetch = false,
        private ?Closure $output = null,
    ) {
        $path = $this->git(['rev-parse', '--git-path', 'eventwillow-sync']);
        $this->directory = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $root.'/'.$path;
    }

    public function execute(string $action): void
    {
        match ($action) {
            'setup' => $this->setup(),
            'preview' => $this->preview(),
            'prepare' => $this->prepare(),
            'continue' => $this->resume(),
            'check' => $this->check(),
            'draft-pr' => $this->draftPr(),
            default => throw new RuntimeException('Choose setup, preview, prepare, continue, check, or draft-pr.'),
        };
    }

    protected function say(string $message): void
    {
        if ($this->output) {
            ($this->output)($message);
        }
    }

    protected function run(array $command, array $allowed = [0]): ProcessResult
    {
        $result = Process::path($this->root)->forever()->run($command);
        if (! in_array($result->exitCode(), $allowed, true)) {
            throw new RuntimeException(trim($result->errorOutput()) ?: trim($result->output()) ?: 'Command failed.');
        }

        return $result;
    }

    protected function git(array $arguments, array $allowed = [0]): string
    {
        return trim($this->run(['git', ...$arguments], $allowed)->output());
    }

    private function lines(string $text): array
    {
        return $text === '' ? [] : explode("\n", $text);
    }

    public function activeMerge(): bool
    {
        return $this->run(['git', 'rev-parse', '--verify', 'MERGE_HEAD'], [0, 128])->exitCode() === 0;
    }

    public function conflicts(): array
    {
        return $this->lines($this->git(['diff', '--name-only', '--diff-filter=U']));
    }

    private function branch(): string
    {
        return $this->git(['branch', '--show-current']) ?: throw new RuntimeException('Switch to a branch first; detached HEAD is not supported.');
    }

    private function requireClean(): void
    {
        if ($this->activeMerge() || $this->git(['status', '--porcelain']) !== '') {
            throw new RuntimeException('The checkout must be clean, with no merge in progress. Commit or stash your work first.');
        }
    }

    public function setup(): void
    {
        $this->git(['config', '--local', 'rerere.enabled', 'true']);
        $this->git(['config', '--local', 'rerere.autoupdate', 'false']);
        $this->say('Conflict reuse enabled for this checkout. Reused resolutions remain unstaged for review.');
    }

    public function statePath(): string
    {
        return $this->directory.'/state.json';
    }

    public function reportPath(): string
    {
        return $this->directory.'/pull-request.md';
    }

    protected function save(array $state): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Could not create the sync state directory.');
        }
        if (file_put_contents($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n") === false
            || file_put_contents($this->reportPath(), $this->report($state)) === false) {
            throw new RuntimeException('Could not save sync state and PR notes.');
        }
    }

    public function load(): array
    {
        try {
            $state = json_decode(is_file($this->statePath()) ? file_get_contents($this->statePath()) : '', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('No usable sync state. Start with preview or prepare.', previous: $error);
        }
        if (! is_array($state) || ($state['version'] ?? null) !== 2) {
            throw new RuntimeException('This state belongs to the retired Python helper. Run check to validate it through Artisan.');
        }
        if (($state['branch'] ?? null) !== $this->branch()) {
            throw new RuntimeException('The saved sync belongs to another branch. Switch back to it before continuing.');
        }

        return $state;
    }

    private function summary(): array
    {
        if (! $this->noFetch) {
            foreach (['origin', 'upstream'] as $remote) {
                $this->say("Fetching {$remote}...");
                $this->git(['-c', 'core.sshCommand=ssh -o BatchMode=yes -o ConnectTimeout=20', 'fetch', '--no-tags', $remote]);
            }
        }
        $base = $this->git(['rev-parse', '--verify', $this->base.'^{commit}']);
        $upstream = $this->git(['rev-parse', '--verify', 'upstream/main^{commit}']);

        return ['version' => 2, 'base' => $this->base, 'base_sha' => $base, 'upstream_sha' => $upstream,
            'commits' => $this->lines($this->git(['log', '--reverse', '--format=%h %s', "{$base}..{$upstream}"])), 'checks' => []];
    }

    private function report(array $state): string
    {
        $upstream = substr($state['upstream_sha'], 0, 12);
        $base = substr($state['base_sha'], 0, 12);
        $lines = ["Sync EventSchedule through `{$upstream}` for development testing before merging into main.", '',
            "Starting point: `{$state['base']}` (`{$base}`).", '', 'Upstream commits missing from the starting point:'];
        foreach ($state['commits'] ?: ['None.'] as $commit) {
            $lines[] = '- '.$commit;
        }
        $lines = [...$lines, '', 'Validation:'];
        foreach ($state['checks'] as $check) {
            $lines[] = "- {$check['name']}: {$check['status']}";
        }
        if (! $state['checks']) {
            $lines[] = '- Not run yet.';
        }
        $lines = [...$lines, '', 'These are focused checks, not a full-suite result. Interactive browser testing and review remain required. Keep the PR in draft until testing is complete. Main was not modified.', ''];

        return implode("\n", $lines);
    }

    public function preview(): void
    {
        $this->say($this->report($this->summary()));
        $this->say('Preview only: no branch, working files, or pull request changed.');
    }

    public function prepare(): void
    {
        $this->requireClean();
        if ($this->git(['config', '--bool', 'rerere.enabled'], [0, 1]) !== 'true'
            || $this->git(['config', '--bool', 'rerere.autoupdate'], [0, 1]) !== 'false') {
            throw new RuntimeException('Run setup first so conflict reuse is enabled and reused resolutions stay unstaged.');
        }
        $state = $this->summary();
        if (! $state['commits']) {
            $this->say('The starting point already includes upstream/main. Nothing to prepare.');

            return;
        }
        $branch = $this->targetBranch ?? 'upstream-sync/'.date('Y-m-d').'-'.substr($state['upstream_sha'], 0, 8);
        $this->git(['check-ref-format', '--branch', $branch]);
        if (in_array($branch, ['main', preg_replace('~^origin/~', '', $this->base)], true)) {
            throw new RuntimeException('Use a new sync branch; main and the starting branch are protected.');
        }
        if ($this->run(['git', 'show-ref', '--verify', '--quiet', 'refs/heads/'.$branch], [0, 1])->exitCode() === 0) {
            throw new RuntimeException("{$branch} already exists. It will not be reset or overwritten.");
        }
        foreach ([...self::REQUIRED_SOURCE, ...self::FOCUSED_TESTS] as $path) {
            if ($this->run(['git', 'cat-file', '-e', "{$state['base_sha']}:{$path}"], [0, 1, 128])->exitCode() !== 0) {
                throw new RuntimeException("The starting point lacks {$path}. Choose a base that includes the sync improvements; no branch was created.");
            }
        }
        if (! $this->git(['merge-base', $state['base_sha'], $state['upstream_sha']], [0, 1])) {
            throw new RuntimeException('The starting point and upstream have no shared history; no branch was created.');
        }
        $this->git(['switch', '-c', $branch, $state['base_sha']]);
        $state['branch'] = $branch;
        $state['phase'] = 'merging';
        $this->save($state);
        $this->git(['merge', '--no-commit', '--no-ff', $state['upstream_sha']], [0, 1]);
        if (! $this->activeMerge()) {
            throw new RuntimeException('The merge did not start. Inspect Git status; no branch was reset.');
        }
        $this->finish($state, true);
    }

    protected function checkCommand(array &$state, string $name, array $command): void
    {
        $log = $this->directory.'/'.str_replace(' ', '-', $name).'.log';
        $this->say("Running {$name}; log: {$log}");
        $handle = fopen($log, 'w');
        if ($handle === false) {
            throw new RuntimeException('Could not open the validation log.');
        }
        try {
            $process = Process::path($this->root)->forever();
            if ($name === 'focused PHP tests') {
                // Symfony otherwise inherits the booted Artisan application's .env
                // values through $_SERVER. In particular APP_ENV=local would enable
                // CSRF in PHPUnit and turn valid test requests into HTTP 419s.
                $process->env($this->testEnvironment());
            }
            $result = $process->run($command, fn ($type, $output) => fwrite($handle, $output));
        } finally {
            fclose($handle);
        }
        $state['checks'][] = ['name' => $name, 'status' => $result->successful() ? 'passed' : 'failed'];
        $this->save($state);
        if (! $result->successful()) {
            throw new RuntimeException("{$name} failed. Review {$log}; nothing was committed or pushed.");
        }
    }

    private function testEnvironment(): array
    {
        $profile = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $profile->load($this->root.'/phpunit.xml', LIBXML_NONET)) {
                throw new RuntimeException('Could not read the PHPUnit environment profile.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $environment = [];
        foreach ((new \DOMXPath($profile))->query('/phpunit/php/env') as $entry) {
            $environment[$entry->getAttribute('name')] = $entry->getAttribute('value');
        }

        return $environment;
    }

    private function finish(array $state, bool $merging): void
    {
        $state['checks'] = [];
        unset($state['checked_tree']);
        $this->save($state);
        $conflicts = $this->conflicts();
        if (in_array(self::MANIFEST, $conflicts, true)) {
            // Restore this generated PHP file before stopping so the next Artisan
            // invocation can boot. It is rebuilt after ordinary conflicts are resolved.
            $this->git(['checkout', '--ours', '--', self::MANIFEST]);
            $this->git(['add', '--', self::MANIFEST]);
            $conflicts = array_values(array_diff($conflicts, [self::MANIFEST]));
        }
        if ($conflicts) {
            $this->say('Review and resolve these files, then git add each reviewed resolution:');
            foreach ($conflicts as $path) {
                $this->say('  '.$path);
            }
            throw new RuntimeException('Unresolved conflicts. Run continue after reviewing and staging the resolutions.');
        }
        $this->checkCommand($state, 'sitemap generation', ['env', 'IS_NEXUS=true', PHP_BINARY, 'artisan', 'sitemap:lastmod']);
        if ($merging) {
            $this->git(['add', '--', self::MANIFEST]);
        } elseif ($this->git(['status', '--porcelain', '--', self::MANIFEST])) {
            throw new RuntimeException('Sitemap dates changed after the commit. Review and commit the generated manifest, then run check again.');
        }
        if ($this->conflicts() || $this->git(['diff', '--name-only'])) {
            throw new RuntimeException('Unstaged tracked edits remain. Review and stage them before checks.');
        }
        if ($merging) {
            $this->git(['rerere']);
        }
        foreach (self::FOCUSED_TESTS as $path) {
            if (! is_file($this->root.'/'.$path)) {
                throw new RuntimeException("Focused test missing: {$path}. Choose a base that includes the sync improvements.");
            }
        }
        $tree = $this->git(['write-tree']);
        $this->checkCommand($state, 'frontend build', ['npm', 'run', 'build']);
        $this->checkCommand($state, 'focused PHP tests', [PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.memory_consumption=512', '-d', 'opcache.interned_strings_buffer=64', '-d', 'opcache.max_accelerated_files=30000', 'vendor/bin/phpunit', ...self::FOCUSED_TESTS]);
        if ($this->git(['diff', '--name-only']) || $this->git(['write-tree']) !== $tree) {
            throw new RuntimeException('The tracked tree changed during checks. Review those changes and run checks again.');
        }
        $state['checked_tree'] = $tree;
        $state['phase'] = 'checked';
        $this->save($state);
        $this->say('Focused checks passed. PR notes: '.$this->reportPath());
        if ($merging) {
            $this->say('Review the diff and commit the merge, then run check again to verify post-commit sitemap dates.');
        }
        $this->say('No commit, push, or merge into main was performed.');
    }

    public function resume(): void
    {
        $state = $this->load();
        if (! $this->activeMerge() || ! in_array($state['phase'] ?? null, ['merging', 'checked'], true)) {
            throw new RuntimeException('No merge started by this command is active. Use check for a committed branch.');
        }
        $this->finish($state, true);
    }

    public function check(): void
    {
        $this->requireClean();
        if ($this->branch() === 'main') {
            throw new RuntimeException('Run validation on a non-main branch; generated files on main are protected.');
        }
        $old = is_file($this->statePath()) ? json_decode(file_get_contents($this->statePath()), true) : null;
        $state = is_array($old) && ($old['version'] ?? null) === 2 ? $this->load() : $this->summary();
        if ($this->run(['git', 'merge-base', '--is-ancestor', $state['base_sha'], 'HEAD'], [0, 1, 128])->exitCode() !== 0) {
            // An amended/rebased starting commit invalidates the old snapshot.
            $state = $this->summary();
        }
        $state['branch'] = $this->branch();
        $this->finish($state, false);
    }

    public function draftPr(): void
    {
        $this->requireClean();
        $state = $this->load();
        $branch = $this->branch();
        if ($branch === 'main' || ($state['phase'] ?? null) !== 'checked' || ($state['checked_tree'] ?? null) !== $this->git(['rev-parse', 'HEAD^{tree}'])) {
            throw new RuntimeException('The committed tree must match successful checks on a non-main branch. Run check first.');
        }
        if (! $state['checks'] || array_filter($state['checks'], fn ($check) => $check['status'] !== 'passed')) {
            throw new RuntimeException('All focused checks must pass before publishing a draft.');
        }
        foreach ([$state['base_sha'], $state['upstream_sha']] as $ref) {
            if ($this->run(['git', 'merge-base', '--is-ancestor', $ref, 'HEAD'], [0, 1])->exitCode() !== 0) {
                throw new RuntimeException('This branch does not include the recorded starting point and upstream snapshot.');
            }
        }
        if (! (new ExecutableFinder)->find('gh')) {
            throw new RuntimeException('GitHub CLI must be installed and authenticated in the environment running Artisan for draft-pr.');
        }
        $this->run(['gh', 'auth', 'status']);
        $existing = json_decode($this->run(['gh', 'pr', 'list', '--head', $branch, '--base', 'main', '--state', 'open', '--json', 'url,isDraft'])->output(), true, 512, JSON_THROW_ON_ERROR);
        if ($existing && ! $existing[0]['isDraft']) {
            throw new RuntimeException('The existing PR is not a draft; it will not be changed automatically.');
        }
        $this->git(['push', 'origin', 'HEAD:refs/heads/'.$branch]);
        $command = $existing ? ['gh', 'pr', 'edit', $existing[0]['url'], '--body-file', $this->reportPath()]
            : ['gh', 'pr', 'create', '--base', 'main', '--head', $branch, '--draft', '--title', 'Sync EventSchedule upstream for development testing', '--body-file', $this->reportPath()];
        $this->say(trim($this->run($command)->output()));
    }
}
