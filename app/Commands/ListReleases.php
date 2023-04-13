<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ListReleases extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'listReleases';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Displays release history of the wordpress container';

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

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
