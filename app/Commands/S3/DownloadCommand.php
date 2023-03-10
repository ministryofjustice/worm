<?php

namespace App\Commands\S3;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class DownloadCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 's3:download { bucket : s3 bucket name } { profile : AWS s3 profile name }';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Download all multisite assets stored in s3 bucket.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bucket = $this->argument('bucket');
        $profile = $this->argument('profile');

        $path = rtrim(shell_exec('pwd'));

        # Check that there is a /wordpress folder in the directory this is run
        if (!is_dir($path . "/wordpress")) {
            $this->info('Wordpress installation not found. Check you are in the root
                directory of the hale-platform repo and have already run
                the site locally, so that a wordpress folder has been generated.');
            return;
        }

        $uploadsPath = $path . "/wordpress/wp-content";

        passthru("aws s3 sync s3://$bucket $uploadsPath --profile $profile");
    }

    /*
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
