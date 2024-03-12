<?php

namespace App\Commands;

use App\Helpers\Kubernetes;
use App\Helpers\Wordpress;
use App\Helpers\EnvUtils;
use Illuminate\Container\Container;

/**
 * Class ImportDatabase
 *
 * Represents a utility for importing WordPress multisite databases within a Kubernetes environment.
 *
 * @package App\Commands
 */
class ImportDatabase
{
    /**
     * The target environment for the database import operation.
     *
     * @var string
     */
    protected $target;


    /**
     * The source.
     *
     * @var string
     */
    protected $source;

    /**
     * The file path of the SQL file to be imported.
     *
     * @var string
     */
    protected $filePath;

    /**
     * The name of the SQL file to be imported.
     *
     * @var string
     */
    protected $fileName;

    /**
     * Optional blog ID for single-site import. Default is null.
     *
     * @var int|null
     */
    protected $blogID;

    /**
     * Optional S3 synchronization flag. Default is null.
     *
     * @var string|null
     */
    protected $s3sync;

    /**
     * The name of the Kubernetes pod where the WordPress instance resides.
     *
     * @var string
     */
    protected $podName;

    /**
     * The command used for executing commands within a Kubernetes container.
     *
     * @var string
     */
    protected $containerExec;

    /**
     * An instance of the Kubernetes helper class.
     *
     * @var Kubernetes
     */
    protected $kubernetesObject;

    /**
     * An instance of the WordPress helper class.
     *
     * @var Wordpress
     */
    protected $wordpressObject;

    /**
     * An instance of the EnvUtils helper class.
     *
     * @var envUtilsObject
     */
    protected $envUtilsObject;

    /**
     * An instance of the Container site list class.
     *
     * @var Container
     */
    protected $containerObject;


    /**
     * Constructor for the ImportDatabase class.
     *
     * Initializes an instance of the ImportDatabase class with the provided parameters, such as the target environment,
     * file path, file name, an optional blog ID, and an optional S3 synchronization flag.
     * This constructor sets up Kubernetes-related properties, including the pod name and container execution command,
     * and initializes instances of the Kubernetes and WordPress helper classes for subsequent import operations.
     *
     * @param string $target   The target environment for the import.
     * @param string $filePath The path to the SQL file to be imported.
     * @param string $fileName The name of the SQL file to be imported.
     * @param int|null $blogID Optional blog ID for single-site import. Default is null.
     * @param string|null $s3sync Optional S3 synchronization flag. Default is null.
     */
    public function __construct($target, $source, $filePath, $fileName, $blogID = null, $s3sync = null)
    {
        $this->target = $target;
        $this->source = $source;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->blogID = $blogID;
        $this->s3sync = $s3sync;
        $this->kubernetesObject = new Kubernetes();
        $this->wordpressObject = new Wordpress();
        $this->envUtilsObject = new EnvUtils();
        $this->containerObject = Container::getInstance();
        $this->containerExec = $this->kubernetesObject->getExecCommand($target);
        $this->podName = $this->kubernetesObject->getPodName($target, "wordpress");
    }

    /**
     * Execute the WordPress multisite database import operation.
     *
     * This method orchestrates the database import process.
     * It copies the SQL file into the Kubernetes container, executes the 'wp db import' command,
     * removes the imported SQL file, performs URL replacements in the database, and optionally synchronizes S3 buckets
     * and performs string replacements of S3 bucket names in the WordPress installation.
     */
    public function runDatabaseImport()
    {
        $this->copyDatabaseToContainer();
        $this->executeDbImportCommand();
        $this->removeSqlFileFromContainer();
        $this->replaceDatabaseURLs();
        $this->applyDomainRewriteNonProd();

        if ($this->s3sync === 'true') {
            $this->syncS3Buckets();
        }

        $this->replaceS3BucketNames();
    }

    /**
     * Copy the SQL file into the Kubernetes container.
     */
    protected function copyDatabaseToContainer()
    {
        $this->kubernetesObject
            ->copyDatabaseToContainer($this->target, $this->filePath, $this->fileName, $this->podName);
    }

    /**
     * Execute the 'wp db import' command in the Kubernetes container.
     */
    protected function executeDbImportCommand()
    {
        $command = "$this->containerExec wp db import $this->fileName";

        echo '[*] Run import' . PHP_EOL;

        // Execute the kubectl cp command
        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: Failed to execute wp db \n$command"
            );
        }
    }

    /**
     * Remove the imported SQL file from the Kubernetes container.
     */
    protected function removeSqlFileFromContainer()
    {
        $command = "$this->containerExec rm $this->fileName";
                // Execute the kubectl cp command
        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: Failed to execute rm \n$command"
            );
        }
    }

    /**
     * Perform URL replacements in the WordPress database.
     */
    protected function replaceDatabaseURLs()
    {
        $this->wordpressObject->replaceDatabaseURLs($this->target, $this->source, $this->blogID);
    }

    /**
     * Sync S3 buckets between source and target environments.
     *
     * This function syncs S3 buckets between source and target environments using the KubernetesObject's syncS3Buckets method.
     * It checks if the target environment is 'local' and returns early if so, as no synchronization is needed for the local environment.
     * Otherwise, it invokes the syncS3Buckets method to perform the synchronization.
     *
     * @return void
     */
    protected function syncS3Buckets()
    {
        // Check if the target environment is 'local'
        if ($this->target == 'local') {
            // No need to sync S3 buckets for the local environment
            return;
        }

        // Sync S3 buckets between source and target environments
        $this->kubernetesObject->syncS3Buckets($this->target, $this->source, $this->blogID);
    }

    /**
     * Perform string replacement of S3 bucket names in the WordPress installation.
     */
    protected function replaceS3BucketNames()
    {
        if ($this->target == 'local') {
            return;
        }

        $targetBucket = $this->kubernetesObject->getBucketName($this->target);
        $sourceBucket = $this->kubernetesObject->getBucketName($this->source);

        $this->wordpressObject->stringReplaceS3BucketName($targetBucket, $sourceBucket, $this->target, $this->blogID);
    }

    /**
     * Performs domain rewrite for non-production environments.
     * This method swaps the production domain with a non-production domain.
     *
     * @return void
     */
    protected function applyDomainRewriteNonProd()
    {
        // Check if the target environment is production; if so, return early
        if ($this->target === 'prod') {
            return;
        }

        // Retrieve the Kubernetes exec command for the target environment
        $containerExecCommand = $this->kubernetesObject->getExecCommand($this->target);

        // Retrieve site information from the container object
        $sites = $this->containerObject->get('sites');

        // Informational text for the operation
        $infoText = "[*] Rewrite prod domain with a non-prod domain" . PHP_EOL;

        // Output the informational text
        echo $infoText;

        // Loop through each site in our hardcoded domain site list and rewrite urls
        foreach ($sites as $site) {
            $domain = $site['domain'];
            $sitePath = $site['path'];
            $siteID = $site['blogID'];

            // Single site import: Run the rewrite code once for the matching single site and finish
            if ($this->blogID !== null && $this->blogID === $siteID) {
                $this->performDomainRewriteNonProd($domain, $sitePath, $siteID);
            }

            // Multisite import: If no blog ID is being used, migrate the entire site without restrictions
            if ($this->blogID === null) {
                $this->performDomainRewriteNonProd($domain, $sitePath, $siteID);
            }
        }
    }

    /**
     * Perform domain rewrite prod to non-prod
     *
     * @param string $domain The domain of the site.
     * @param string $sitePath The path of the site.
     * @param string $containerExecCommand The command to execute in the container.
     * @param int $siteID The ID of the site.
     * @return void
     */
    protected function performDomainRewriteNonProd(string $domain, string $sitePath, int $siteID)
    {
        // Get either the hale.docker url or the CP url depending on the target
        $nonProdDomain = $this->envUtilsObject->getNonProdDomain($this->target);

        $newURL = $nonProdDomain . "/" . $sitePath;

        echo PHP_EOL . 'Domain: ' . $domain . PHP_EOL;

        // These 3 rewrites need to stay here in the loop

        // 1. Search and replace rewrite
        // Use siteID not blogID, so that only that domain is targeted in the rewrite
        $command = "$this->containerExec wp search-replace 'https://$domain' 'https://$newURL'";
        $command .= " --all-tables-with-prefix 'wp_{$siteID}_*'";
        $command .= " --network";
        $command .= " --skip-columns=guid";
        $command .= " --report-changed-only";

        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: domain search and replace failed: \n$command"
            );
        }

        // 2. Domain rewrite
        $this->wordpressObject->replaceDatabaseDomain($this->target, $nonProdDomain, $siteID);

        // 3. Path rewrite
        $this->wordpressObject->replaceDatabasePath($this->target, $sitePath, $siteID);

        // Switch plugins on or off depending on environment
        $status = ($this->target === 'local' || $this->target === 'prod') ? 'deactivate' : 'activate';

        $this->wordpressObject->modifyPluginStatus($this->target, $status, 'wp-force-login', $nonProdDomain);
    }
}
