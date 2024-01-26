<?php

namespace App\Commands\Db;

use Illuminate\Console\Command;
use App\Commands\ImportDatabase;
use App\Helpers\EnvSet;

class ImportCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = "db:import
        { target : Target environment you are importing DB to, ie prod, staging, dev, demo, local. }
        { file : Path to the database file you want to import. }
        {--blogID= : Blog id of remote site db you want to replace. }
        {--s3sync= : Set s3 sync to --s3sync=true will import the db and if available sync the media assets and rewrite s3bucket urls. }";

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Import WP multisite database(s) in .sql file format.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get command line arguments and options
        $target = $this->argument('target');
        $filePath = $this->argument('file');
        $blogID = $this->option('blogID');
        $s3sync = $this->option('s3sync');

        $source = '';

        // Check if the SQL file is valid
        $envSetObject = new EnvSet();
        $sqlFileIsValid = $envSetObject->checkSQLfileIsValid($filePath);

        if (!$sqlFileIsValid) {
            $this->error("Invalid SQL file at: $filePath");
            return;
        }

        $fileName = basename($filePath);
 
        // Check SQL file and determine what environment it came from
        $extractEnvFromFileName = $envSetObject->extractFileNameEnvironment($fileName);

        // Two checks to confirm file is multsite not single site
        $isMultsiteFileName = $envSetObject->isMultisiteDbExportByFileName($fileName);
        $isMultisiteDbTables = $envSetObject->searchWordsInSqlFile($filePath, ['wp_blogs','wp_site']);

        $isInvalidSingleSiteImport = $isMultsiteFileName && $isMultisiteDbTables && !empty($blogID);

        if ($isInvalidSingleSiteImport) {
            echo 'Error: Your DB file contains tables indicating it is not a single site import. ' .
                 'Omit the --blogID option if you want to import an entire multisite but this will overwrite all blogs ' .
                 'with the imported data.' . PHP_EOL;
                 exit(0);
        }

        $isSingleSiteExport = $isMultsiteFileName === false && $isMultisiteDbTables === false && empty($blogID);

        if ($isSingleSiteExport) {
            // Handle the error condition for a single site export without the --blogID option
            echo 'Error: The file you are importing appears to contain a single site export however ' .
                 'you have not included the --blogID option. ' .
                 'Add --blogID option with the ID of the site you wish to import into.' . PHP_EOL;
            exit(0);
        }

        if (in_array($extractEnvFromFileName, ['prod', 'staging', 'dev', 'demo', 'local'])) {
            $source = $extractEnvFromFileName;
        }

        if ($extractEnvFromFileName == null) {
            $customDatabaseDomain = $this->ask(
                'The SQL file you are importing does not match one of our environments. ' .
                'Enter the domain or URL of the database you are importing. ' .
                'For example, ccrc.gov.uk. This is required to rewrite the database when it is imported. '. PHP_EOL
            );
            $source = $customDatabaseDomain;
        }

        if (empty($source)) {
            echo 'Undetermined database origin. Exiting.' . PHP_EOL;
            exit(0);
        }

        // Alert if importing to prod
        if ($target === 'prod') {
            $proceed = $this->ask('##### WARNING ##### You are running a command against prod. Do you wish to proceed? y/n');

            // If not "yes", then exit.
            if ($proceed != 'yes' && $proceed != 'y') {
                $this->info("Command canceled. Exiting task.");
                exit(0);
            }
        }

        $importDatabaseObject = new ImportDatabase($target, $source, $filePath, $fileName, $blogID, $s3sync);

        try {
            $importDatabaseObject->runDatabaseImport();
            $this->info("Import completed successfully.");
        } catch (\Exception $e) {
            $this->error("Error during import: " . $e->getMessage());
        }
    }
}
