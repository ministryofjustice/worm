<?php

namespace App\Commands\Db;

use App\Commands\ExportDatabase;
use App\Helpers\EnvSet;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ExportCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     *
     * This property defines the command signature used in the console to invoke the 'db:export' command.
     * It includes the required 'target' argument, representing the target environment for exporting the database,
     * and an optional '--blogID' option, allowing specification of a blog ID associated with the export.
     * The format of the signature is 'db:export {target} {--blogID=}'.
     */
    protected $signature = 'db:export 
                            {target : Target environment you are exporting DB from}
                            {--blogID= : blog id}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Export WP multisite database in .sql file format.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $blogID = $this->option('blogID');
        $target = $this->argument('target');

        $envSetObject = new EnvSet();
        $sqlfile = $envSetObject->generateFileName($target, $blogID);
        
        $exportDatabase = new ExportDatabase($target, $sqlfile, $blogID);
    
        if (is_null($blogID)) {
            $exportDatabase->runExportMultisite();
        }

        if (!empty($blogID)) {
            $exportDatabase->runExportSingleSite();
        }
    }
}
