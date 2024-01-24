<?php

namespace App\Commands;

use App\Helpers\Kubernetes;
use App\Helpers\EnvSet;
use App\Helpers\Wordpress;

class ImportDatabase
{
    protected $target;
    protected $source;
    protected $filePath;
    protected $fileName;
    protected $blogID;
    protected $s3sync;
    protected $podName;
    protected $containerExec;
    protected $kubernetesObject;
    protected $wordpressObject;

    /**
     * Constructor for the ImportDatabase class.
     *
     * @param string $target   The target environment for the import.
     * @param string $sqlFile  The SQL file name for the import.
     * @param int|null $blogID Optional blog ID for single-site import. Default is null.
     *
     * Initializes the ImportDatabase instance with the target environment, SQL file name,
     * and an optional blog ID. It sets up Kubernetes-related properties like pod name and
     * container execution command for subsequent import operations.
     */
    public function __construct( $target, $source, $filePath, $fileName, $blogID = null, $s3sync = null )
    {
        $this->target = $target;
        $this->source = $source;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->blogID = $blogID;
        $this->s3sync = $s3sync;
        $this->kubernetesObject = new Kubernetes();
        $this->wordpressObject = new Wordpress();
        $this->containerExec = $this->kubernetesObject->getExecCommand($target);
        $this->podName = $this->kubernetesObject->getPodName($target, "wordpress");
    }

    /**
     * Run the import operation for WordPress multisite.
     *
     * Executes the necessary commands and operations to import a multisite database
     * from a specified SQL file format in a Kubernetes container.
     */
    public function runImportMultisite()
    {
        // Copy the SQL file into the Kubernetes container
        $this->kubernetesObject
            ->copyDatabaseToContainer($this->target, $this->filePath, $this->fileName, $this->podName);

        // Execute 'wp db import' command in the container
        $importCommand = "$this->containerExec wp db import $this->fileName";
        passthru($importCommand);

        // Remove the imported SQL file from the container
        $removeCommand = "$this->containerExec rm $this->fileName";
        passthru($removeCommand);

        // Run WP CLI find and replace on database URLs
        $this->wordpressObject->replaceDatabaseURLs($this->target, $this->source, $this->blogID = null);

        if ($this->s3sync === 'true') {
            // Sync S3 buckets between source and target environments
            $this->kubernetesObject->syncS3Buckets($this->target, $this->source, $this->blogID = null);

            // Perform string replacement of S3 bucket names in the WordPress installation
            $targetBucket = $this->kubernetesObject->getBucketName($this->target);
            $sourceBucket = $this->kubernetesObject->getBucketName($this->source);
            $this->wordpressObject->stringReplaceS3BucketName($targetBucket, $sourceBucket, $blogID = null);
        }
    }

    /**
     * Run the export operation for a single WP site.
     *
     * Checks if the specified blog exists, exports the blog's database tables to the specified
     * SQL file, and transfers the SQL file from the container to the local machine. Deletes
     * the SQL file in the container after the export is complete.
     */
    public function runImportSingleSite()
    {
        // $validBlogID = false;
        // $sqlFile = $this->sqlFile;
        // $blogID = $this->blogID;
        // $containerExec = $this->containerExec;
        // $podName = $this->podName;
        // $target = $this->target;
        // $envSetObject = new EnvSet();
        // $blogExists = $envSetObject->checkSiteExists($target, $blogID);

        // if (!$blogExists) {
        //     echo 'Blog with ID ' . $blogID . ' not found during export of single site.' . PHP_EOL;
        //     return;
        // }

        // // Get Single Blog Table Names
        // $tableNames = rtrim(shell_exec("$containerExec wp db tables 'wp_{$blogID}_*' --all-tables-with-prefix --format=csv"));

        // if (count(explode(",", $tableNames)) < 10) {
        //     $this->info('Not all blog tables found');
        //     return;
        // }

        // // Export Single Blog from RDS to container
        // passthru("$containerExec wp db export --porcelain $sqlFile --tables='$tableNames'");

        // $this->kubernetesObject->copyDatabaseToLocal($target, $podName, $sqlFile, $container = 'wordpress');

        // // Delete SQL file in container no longer needed
        // passthru("$containerExec rm $sqlFile");
    }
}
