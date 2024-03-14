<?php

namespace App\Helpers;

use App\Helpers\Kubernetes;
use App\Helpers\EnvUtils;

class Wordpress
{
    /**
     * The Kubernetes object used for interacting with Kubernetes resources.
     *
     * @var Kubernetes
     */
    protected $kubernetesObject;

    /**
     * The EnvUtils object used for managing environment settings.
     *
     * @var EnvUtils
     */
    protected $envUtilsObject;

    /**
     * Constructor method for the ImportCommand class.
     *
     * Initializes the Kubernetes and EnvUtils objects used within the ImportCommand class.
     * These objects are responsible for interacting with Kubernetes resources and environment settings, respectively.
     *
     * @return void
     */
    public function __construct()
    {
        $this->kubernetesObject = new Kubernetes();
        $this->envUtilsObject = new EnvUtils();
    }

     /**
     * Replace database URLs to match the target environment.
     *
     * @param string $target The target environment name.
     * @return bool True if the URL replacement is successful; otherwise, false.
     */
    public function replaceDatabaseURLs($target, $source, $blogID = null)
    {
        $containerExec = $this->kubernetesObject->getExecCommand($target);

        // Define the old and new URLs based on the environment names
        $sourceSiteURL = $this->envUtilsObject->getDomain($source);
        $targetSiteURL = $this->envUtilsObject->getDomain($target);

        if ($blogID !== null) {
            $extraOptions = "--all-tables-with-prefix 'wp_{$blogID}_*'";
        } else {
            $extraOptions = "--all-tables";
        }

        // Shell into container (kubernetes or docker) and run wp find and replace
        $command = "$containerExec wp search-replace";
        $command .= " $sourceSiteURL";
        $command .= " $targetSiteURL";
        $command .= " $extraOptions";
        $command .= " --network";
        $command .= " --precise";
        $command .= " --skip-columns=guid";
        $command .= " --report-changed-only";
        $command .= " --recurse-objects";

        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: Failed to execute wp search-replace \n$command"
            );
        }
    }

    /**
     * Replaces one S3 bucket name with the target other s3 bucket name.
     *
     * @param string $sourceBucket        The source S3 bucket name.
     * @param string $targetBucket        The target CloudPlatform bucket name.
     * @param string $targetSiteURL       The URL of the target site.
     * @param string $containerExecCommand The command for executing within the container.
     * @return void
     */
    public function stringReplaceS3BucketName($targetBucket, $sourceBucket, $target, $blogID = null)
    {
        if ($blogID !== null) {
            $extraOptions = "--all-tables-with-prefix 'wp_{$blogID}_*'";
        } else {
            $extraOptions = "--all-tables";
        }

        $containerExec = $this->kubernetesObject->getExecCommand($target);

        $command = "$containerExec wp search-replace";
        $command .= " $sourceBucket";
        $command .= " $targetBucket";
        $command .= " $extraOptions";
        $command .= " --network";
        $command .= " --precise";
        $command .= " --skip-columns=guid";
        $command .= " --report-changed-only";
        $command .= " --recurse-objects";

        echo "Run s3 bucket string replace: $sourceBucket with $targetBucket";

        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: Failed s3 bucket string replace. Command run: \n$command"
            );
        }
    }

    /**
     * Execute a WordPress database query.
     *
     * This function executes a WordPress database query using the WP-CLI `wp db query` command
     * inside a Kubernetes (or Docker) container.
     *
     * @param string $target      The target environment where the command will be executed.
     * @param array  $arguments   The arguments for the database query command.
     * @throws InvalidArgumentException If the command execution fails.
     * @return void
     */
    public function executeWordPressDBQuery($target, $arguments)
    {
        // Get the Kubernetes exec command for the target environment
        $containerExec = $this->kubernetesObject->getExecCommand($target);

        // Set the base WP-CLI command
        $command = "wp db query";

        // Construct the full command
        $fullCommand = "$containerExec $command";

        // Append arguments to the command
        if (!empty($arguments)) {
            $fullCommand .= ' ' . implode(' ', $arguments);
        }

        // Execute the command using passthru
        passthru($fullCommand, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: wp db query failed. Command run: \n$fullCommand"
            );
        }
    }

    /**
     * Modify the status of a WordPress plugin on a target environment.
     *
     * This method modifies the status of a specified WordPress plugin on the given target environment.
     * The method utilizes Kubernetes exec command to interact with the container environment.
     * It executes the provided command to enable or disable the plugin.
     *
     * @param string $target The target environment where the plugin status will be modified.
     * @param string $option The action to perform on the plugin ('activate' or 'deactivate').
     * @param string $pluginName The name of the WordPress plugin to modify.
     * @param string $nonProdDomain The non-production domain to use for the WordPress instance.
     * @throws InvalidArgumentException
     * @return void
     */
    public function modifyPluginStatus(string $target, string $option, string $pluginName, string $nonProdDomain): void
    {
        // Retrieve the Kubernetes exec command for the target environment
        $containerExec = $this->kubernetesObject->getExecCommand($target);

        // Construct the command to execute for modifying the plugin status
        $command = "$containerExec wp plugin $option $pluginName --url=$nonProdDomain";

        // Execute the constructed command
        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: modify plugin failed: \n$command"
            );
        }
    }

        /**
     * Perform URL replacements in the WordPress database.
     *
     * This function performs URL replacements in the WordPress database by updating the 'wp_blogs' table.
     * It retrieves the target site URL based on the specified target environment, constructs the SQL query,
     * and executes the query using the WordPressObject's executeWordPressDBQuery method.
     *
     * @throws Exception If an error occurs during database query execution.
     * @return void
     */
    public function replaceDatabaseDomain($target, $domain, $blogID)
    {
        try {
            // Construct the SQL query to update the 'wp_blogs' table
            $arguments = ["'UPDATE wp_blogs SET domain=\"$domain\" WHERE wp_blogs.blog_id=$blogID'"];

            // Execute the WordPress database query
            $this->executeWordPressDBQuery($target, $arguments);
        } catch (Exception $e) {
            // Handle exceptions (e.g., database query execution error)
            error_log("Error replacing database domain: " . $e->getMessage());
            throw $e; // Re-throw the exception to propagate it further
        }
    }

    /**
     * Perform path replacements in the WordPress database.
     *
     * This function performs path replacements in the WordPress database by updating the 'wp_blogs' table.
     * It constructs the SQL query to update the 'path' column to '/' for the specified blog ID and
     * executes the query using the WordPressObject's executeWordPressDBQuery method.
     *
     * @throws Exception If an error occurs during database query execution.
     * @return void
     */
    public function replaceDatabasePath($target, $siteSlug, $blogID)
    {
        try {
            // Construct the SQL query to update the 'wp_blogs' table
            $arguments = ["'UPDATE wp_blogs SET path=\"/$siteSlug/\" WHERE wp_blogs.blog_id=$blogID'"];

            // Execute the WordPress database query
            $this->executeWordPressDBQuery($target, $arguments);
        } catch (Exception $e) {
            // Handle exceptions (e.g., database query execution error)
            error_log("Error replacing database path: " . $e->getMessage());
            throw $e; // Re-throw the exception to propagate it further
        }
    }
}
