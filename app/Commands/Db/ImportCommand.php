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
        {--s3sync= : Using '--s3sync=true' will additionally sync the media assets and rewrite s3bucket urls. }";

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Import WP multisite or single site database SQL file.';

    /**
     * Instance of EnvSet class.
     *
     * @var EnvSet
     */
    protected $envSet;

    /**
     * Constructor method.
     *
     * @param EnvSet $envSet An instance of the EnvSet class.
     * @return void
     */
    public function __construct(EnvSet $envSet)
    {
        parent::__construct();
        $this->envSet = $envSet;
    }

    /**
     * Handle the command execution.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->validateInput();

            $source = $this->envSet->determineSource($this->argument('file'));

            $this->confirmProdWarning();

            $importDatabaseObject = new ImportDatabase(
                $this->argument('target'),
                $source,
                $this->argument('file'),
                basename($this->argument('file')),
                $this->option('blogID'),
                $this->option('s3sync')
            );

            $importDatabaseObject->runDatabaseImport();
            
            $this->info("Import completed successfully.");
        } catch (\Exception $e) {
            $this->error("Error during import: " . $e->getMessage());
        }
    }

    /**
     * Validates input parameters before initiating the database import process.
     *
     * This method checks the validity of the target environment, file path, and database import type (single-site or multisite).
     * Additionally, it ensures that the specified SQL file is valid.
     *
     * @throws \InvalidArgumentException When invalid input parameters are detected.
     * @return void
     */
    protected function validateInput()
    {
        $target = $this->argument('target');
        $filePath = $this->argument('file');
        $blogID = $this->option('blogID');

        // Validate the target environment, file path, and database import type
        $this->envSet->validateTargetEnvironment($target);
        $this->envSet->validateFilePath($filePath);
        $this->envSet->validateMultisiteImport($filePath, $blogID);

        // Check the validity of the specified SQL file
        $this->envSet->checkSQLfileIsValid($filePath);
    }

    /**
     * Confirm the user's intention to proceed when targeting the production environment.
     * 
     * If the target environment is 'prod', this method prompts the user with a warning message
     * and asks for confirmation to proceed. If the user's response is not 'yes' or 'y', the
     * command execution is canceled, and the task exits.
     *
     * @return void
     */
    protected function confirmProdWarning()
    {
        $target = $this->argument('target');

        if ($target === 'prod') {
            $proceed = $this->ask('### WARNING ### You are running a command against prod. Do you wish to proceed? y/n');

            // If not "yes", then exit.
            if ($proceed !== 'yes' && $proceed !== 'y') {
                $this->info("Command canceled. Exiting task.");
                exit(0);
            }
        }
    }
}





































