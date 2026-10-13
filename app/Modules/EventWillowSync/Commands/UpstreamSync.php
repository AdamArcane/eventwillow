<?php

namespace App\Modules\EventWillowSync\Commands;

use App\Modules\EventWillowSync\Workflow;
use Illuminate\Console\Command;
use Throwable;

class UpstreamSync extends Command
{
    protected $signature = 'upstream:sync
        {action=preview : setup, preview, prepare, continue, check, or draft-pr}
        {--base=origin/main : Starting Git ref}
        {--branch= : New sync branch name for prepare}
        {--no-fetch : Use existing remote refs}';

    protected $description = 'Prepare and validate reviewable EventSchedule upstream syncs';

    public function handle(): int
    {
        try {
            $workflow = new Workflow(base_path(), (string) $this->option('base'), $this->option('branch'), (bool) $this->option('no-fetch'), fn ($message) => $this->line($message));
            $workflow->execute((string) $this->argument('action'));

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Stopped: '.$error->getMessage());

            return self::FAILURE;
        }
    }
}
