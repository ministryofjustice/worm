<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ReleasesCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'releases';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display deployment history of multisite into the CloudPlatform environment.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');
        $this->info("Your current namespace: " . $namespace);

        # Copy database from container to local machine
        passthru("helm history wordpress");
    }
}
