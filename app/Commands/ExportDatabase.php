<?php

namespace App\Commands;

use App\Helpers\Kubernetes;
use App\Helpers\EnvUtils;

class ExportDatabase
{
    protected $target;
    protected $blogID = null;
    protected $podName;
    protected $containerExec;
    protected $sqlFile;
    protected $kubernetesObject;

    /**
     * Constructor for the ExportDatabase class.
     *
     * @param string $target   The target environment for the export.
     * @param string $sqlFile  The SQL file name for the export.
     * @param int|null $blogID Optional blog ID for single-site export. Default is null.
     *
     * Initializes the ExportDatabase instance with the target environment, SQL file name,
     * and an optional blog ID. It sets up Kubernetes-related properties like pod name and
     * container execution command for subsequent export operations.
     */
    public function __construct($target, $sqlFile, $blogID = null)
    {
        $this->blogID = $blogID;
        $this->sqlFile = $sqlFile;
        $this->target = $target;
        $this->kubernetesObject = new Kubernetes();
        $this->podName = $this->kubernetesObject->getPodName($target, "wordpress");
        $this->containerExec = $this->kubernetesObject->getExecCommand($target);
    }

    /**
     * Run the export operation for WP multisite.
     *
     * Executes the 'wp db export' command in the Kubernetes container, exporting the entire
     * multisite database to the specified SQL file format.
     */
    public function runExportMultisite()
    {
        $sqlFile = $this->sqlFile;
        $containerExec = $this->containerExec;
        $target = $this->target;
        $podName = $this->podName;

        passthru("$containerExec wp db export --porcelain $sqlFile");

        $this->kubernetesObject->copyDatabaseToLocal($target, $podName, $sqlFile, $container = 'wordpress');

        // Delete SQL file in container no longer needed
        passthru("$containerExec rm $sqlFile");
    }

    /**
     * Run the export operation for a single WP site.
     *
     * Checks if the specified blog exists, exports the blog's database tables to the specified
     * SQL file, and transfers the SQL file from the container to the local machine. Deletes
     * the SQL file in the container after the export is complete.
     */
    public function runExportSingleSite()
    {
        $validBlogID = false;
        $sqlFile = $this->sqlFile;
        $blogID = $this->blogID;
        $containerExec = $this->containerExec;
        $podName = $this->podName;
        $target = $this->target;
        $envUtilsObject = new EnvUtils();
        $blogExists = $envUtilsObject->checkSiteExists($target, $blogID);

        if (!$blogExists) {
            echo 'Blog with ID ' . $blogID . ' not found during export of single site.' . PHP_EOL;
            return;
        }

        // Get Single Blog Table Names
        $tableNames = rtrim(shell_exec("$containerExec wp db tables 'wp_{$blogID}_*' --all-tables-with-prefix --format=csv"));

        if (count(explode(",", $tableNames)) < 10) {
            $this->info('Not all blog tables found');
            return;
        }

        // Export Single Blog from RDS to container
        passthru("$containerExec wp db export --porcelain $sqlFile --tables='$tableNames'");

        $this->kubernetesObject->copyDatabaseToLocal($target, $podName, $sqlFile, $container = 'wordpress');

        // Delete SQL file in container no longer needed
        passthru("$containerExec rm $sqlFile");
    }
}
