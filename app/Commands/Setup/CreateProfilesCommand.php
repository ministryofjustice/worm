<?php

namespace App\Commands\Setup;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CreateProfilesCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'setup:createProfiles';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Creates AWS Profiles for current namespace';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespace = shell_exec('kubectl config view --minify -o jsonpath="{..namespace}"');

        $this->info("Your current namespace: " . $namespace);

        $proceed = $this->ask('Do you wish to proceed?');

        if ($proceed != 'yes' && $proceed != 'y') {
            return;
        }

        $resources = array ( 's3' => 's3-bucket-output', 'rds' => 'rds-instance-output', 'ecr' => 'ecr-repo-' . $namespace);

        foreach ($resources as $key => $resource) {
            $secrets = shell_exec("cloud-platform decode-secret -n $namespace -s $resource");

            $json_secrets = json_decode($secrets);

            $user_name = $namespace . '-' . $key;

            $list = array (
                array('User Name', 'Access key ID', 'Secret access key'),
                array($user_name, $json_secrets->data->access_key_id, $json_secrets->data->secret_access_key),
            );

            $csv_filename = $user_name . '-profile.csv';

            $fp = fopen($csv_filename, 'w');

            foreach ($list as $fields) {
                fwrite($fp, implode(',', $fields) . "\n");
            }

            fclose($fp);

            passthru("aws configure import --csv file://$csv_filename");

            passthru("aws configure set region eu-west-2 --profile $user_name");

            passthru("aws configure set output json --profile $user_name");

            $this->info("AWS Profile created: " . $user_name);

            passthru("rm $csv_filename");
        }
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
