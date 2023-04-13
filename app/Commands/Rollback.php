<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Rollback extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'rollback {--revision= : revision number}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Rollbacks wordpress container to a revision. Default is to previous revision';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $revisionNum = ($this->option('revision') != null && is_numeric($this->option('revision')) ? $this->option('revision') : '');

        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');

        $this->info("Your current namespace: " . $namespace);

        $proceed = $this->ask('Do you wish to proceed?');

        if ($proceed != 'yes' && $proceed != 'y') {
            return;
        }

       passthru("helm rollback wordpress $revisionNum");

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
