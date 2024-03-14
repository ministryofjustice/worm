<?php

namespace App\Commands\Db;

use Illuminate\Console\Command;
use App\Commands\ImportDatabase;
use App\Helpers\EnvUtils;

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
     * Instance of EnvUtils class.
     *
     * @var EnvUtils
     */
    protected $envUtils;

    /**
     * Constructor method.
     *
     * @param EnvUtils $envUtils An instance of the EnvUtils class.
     * @return void
     */
    public function __construct(EnvUtils $envUtils)
    {
        parent::__construct();
        $this->envUtils = $envUtils;
    }

    /**
     * Handle the command execution.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->envUtils->updateCloudPlatformCli();
            $this->validateInput();

            $source = $this->envUtils->identifySource($this->argument('file'));

            $this->confirmProdWarning();

            $importDatabaseObject = new ImportDatabase(
                target: $this->argument('target'),
                source: $source,
                filePath: $this->argument('file'),
                fileName: basename($this->argument('file')),
                blogID: $this->option('blogID'),
                s3sync: $this->option('s3sync')
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
        $this->envUtils->validateTargetEnvironment($this->argument('target'));
        $this->envUtils->validateFilePath($this->argument('file'));
        $this->envUtils->validateMultisiteImport(
            filePath: $this->argument('file'),
            blogID: $this->option('blogID')
        );
        $this->envUtils->checkSQLfileIsValid($this->argument('file'));
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
            echo 'You cannot import to prod. Functionality not yet added.' . PHP_EOL;
            exit;
        }
    }
}
