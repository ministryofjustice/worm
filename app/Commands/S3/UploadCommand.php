<?php

namespace App\Commands\S3;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class UploadCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 's3:upload { bucket : s3 bucket name } { profile : AWS s3 profile name } {--blogID= : enter site blog id}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Upload either a single site or a multisite\'s media assests.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bucket = $this->argument('bucket');
        $profile = $this->argument('profile');
        $blogID = $this->option('blogID');

        $path = rtrim(shell_exec('pwd'));

        # Check that there is a /wordpress folder in the directory this is run
        if (!is_dir($path . "/wordpress")) {
            $this->info('Wordpress installation not found. Check you are in the root
                directory of the hale-platform repo and have already run
                the site locally, so that a wordpress folder has been generated.');
            return;
        }

        $uploadsPath = $path . "/wordpress/wp-content/uploads";

        if ($blogID === null) {
            passthru("aws s3 sync $uploadsPath s3://$bucket/uploads --profile $profile --acl=public-read");
        } else {
            passthru("aws s3 sync $uploadsPath/sites/$blogID s3://$bucket/uploads/sites/$blogID --profile $profile --acl=public-read");
        }
    }
}
