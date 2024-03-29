<?php

namespace App\Helpers;

use App\Helpers\Docker;

class Kubernetes
{
    /**
     * Retrieve the name of a Kubernetes pod within a specified namespace and pod type.
     *
     * This method queries the Kubernetes cluster to fetch the name of a pod based on the provided namespace
     * and pod type. The pod type should be one of the allowed keywords: 'wordpress' or 'service-pod'.
     *
     * @param string $env  The Kubernetes namespace in which to search for the pod.
     * @param string $type The type of the pod to retrieve ('wordpress' or 'service-pod').
     *
     * @return string|null The name of the pod if found, or null if no matching pod is found or if the environment is 'local'.
     *
     * @throws InvalidArgumentException If an invalid pod type is provided (not within the allowed keywords).
     * @throws RuntimeException         If the 'kubectl' command encounters an error during execution.
     */
    public function getPodName(string $env, string $type)
    {
        // Guard clause: If the environment is 'local,' return null as there's no need to query in a local environment.
        if ($env === 'local') {
            return null;
        }

        // Allowed pod keywords for the type parameter.
        $allowedPodKeywords = ['wordpress', 'service-pod'];

        // Validate that the provided $type is one of the allowed keywords.
        if (!in_array($type, $allowedPodKeywords)) {
            throw new InvalidArgumentException('Invalid pod type. Allowed types are: ' . implode(', ', $allowedPodKeywords));
        }

        // Execute a 'kubectl' command to retrieve the pod name.
        $command = "kubectl get pods -n hale-platform-$env -o=name | grep -m 1 $type | sed 's/^.\{4\}//'";
        $podName = rtrim(shell_exec($command));

        // Check for potential errors in the execution of the 'kubectl' command.
        if ($podName === false) {
            throw new RuntimeException("Failed to execute the 'kubectl' command.");
        }

        return $podName;
    }

    /**
     * Get the execution command for either Docker or Kubernetes
     *
     * @param  string $env the specific environment you want to exec in.
     * @return string The pod execution command.
     */
    public function getExecCommand($env)
    {
        // When running WORM on local use docker commands
        if ($env === 'local') {
            return "docker exec -it wordpress ";
        }

        $podName = $this->getPodName($env, "wordpress");

        // Check if the command failed
        if ($podName == null) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Failed to get pod name."
            );
        }

        return "kubectl exec -it -n hale-platform-$env -c wordpress pod/$podName --";
    }

    /**
     * Copy the database from the Kubernetes container to the local machine.
     *
     * @param string $target      The target environment.
     * @param string $podName     The name of the Kubernetes pod.
     * @param string $sqlFile     The SQL file to be copied.
     * @param string $container   The container name within the pod.
     */
    public function copyDatabaseToLocal($target, $podName, $sqlFile, $container = 'wordpress')
    {
        $command = "kubectl cp --retries=10 -n hale-platform-$target -c $container $podName:$sqlFile $sqlFile";
        passthru($command);
    }

    /**
     * Copy the database file to a Kubernetes container in the specified target environment.
     *
     * This method uses the `kubectl cp` command to copy the database file from the local filesystem
     * to a specified Kubernetes pod running in the target environment.
     *
     * @param string $target     The target environment identifier.
     * @param string $filePath   The local path to the database file to be copied.
     * @param string $fileName   The name of the database file.
     * @param string $podName    The name of the Kubernetes pod where the file will be copied.
     * @param string $container  The name of the container within the pod (default: 'wordpress').
     *
     * @throws \InvalidArgumentException If the `kubectl cp` command fails to execute.
     */
    public function copyDatabaseToContainer($target, $filePath, $fileName, $podName, $container = 'wordpress')
    {
        $DockerObject = new Docker();

        if ($target === 'local') {
            $command = $DockerObject->buildDockerCpCommand($filePath, $fileName);
        } else {
            $command = $this->buildKubectlCpCommand($target, $filePath, $fileName, $podName, $container);
        }

        echo "[*] Copying db file to container" . PHP_EOL;

        // Execute the kubectl cp command
        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: Failed to execute cp command: \n$command"
            );
        }
    }

    /**
     * Constructs the kubectl cp command to copy a file to a Kubernetes container with retries.
     *
     * @param string $target     The target namespace.
     * @param string $container  The name of the container within the pod.
     * @param string $filePath   The path to the file to be copied.
     * @param string $podName    The name of the pod.
     * @param string $fileName   The name of the file within the container.
     * @return string            The constructed kubectl cp command.
     */
    private function buildKubectlCpCommand($target, $filePath, $fileName, $podName, $container)
    {
        // Set the base kubectl cp command
        $command = "kubectl cp";

        // Append options to the command
        $command .= " --retries=10"; // Adjust the number of retries as needed
        $command .= " -n hale-platform-$target";
        $command .= " -c $container";
        $command .= " $filePath hale-platform-$target/$podName:$fileName";

        return $command;
    }


    /**
     * Retrieves the S3 bucket name for a given environment.
     *
     * @param string $env The target environment.
     *
     * @return string The S3 bucket name for the specified environment.
     */
    public function getBucketName($env)
    {
        // Get the command for executing operations in the container
        $containerExec = $this->getExecCommand($env);

        // Get the service pod name for the specified environment
        $servicePodName = $this->getPodName($env, "service-pod");

        // Decode and parse the S3 secrets for the specified environment
        $envBucketsecrets = $this->decodeSecrets($env);
        $envBucketjson_secrets = json_decode($envBucketsecrets, true);

        // Extract the S3 bucket name from the parsed secrets
        $envBucket = $envBucketjson_secrets['data']['S3_UPLOADS_BUCKET'];

        // Return the S3 bucket name
        return $envBucket;
    }

    /**
     * Get the secret name.
     *
     * @param string $env The environment name.
     */
    private function getSecretName($env)
    {
        // Local environment doesn't have secrets
        if ($env === 'local') {
            return;
        }

        $podName = $this->getPodName($env, "wordpress");

        $command = "kubectl describe -n hale-platform-$env pods/$podName | grep -o 'hale-wp-secrets-[[:digit:]]*'";
        $output = shell_exec($command);

        return rtrim($output);
    }

    /**
     * Decode the secrets.
     *
     * @param string $env The environment name.
     */
    private function decodeSecrets($env)
    {
        // Local environment doesn't have secrets
        if ($env === 'local') {
            return;
        }

        $secretName = $this->getSecretName($env);

        $command = "cloud-platform decode-secret -n hale-platform-$env -s $secretName";
        $output = shell_exec($command);

        return rtrim($output);
    }

    /**
     * Syncs S3 buckets between source and target environments.
     *
     * @param string $target The target environment.
     * @param string $source The source environment.
     * @param int|null $blogID The blog ID for multisite migrations (optional).
     *
     * @return void
     */
    public function syncS3Buckets($target, $source, $blogID = null)
    {
        // Get the bucket names for source and target environments
        $targetBucket = $this->getBucketName($target);
        $sourceBucket = $this->getBucketName($source);

        // Determine the uploads directory path based on whether it's a multisite migration
        $uploadsDir = ($blogID != null) ? "uploads/sites/$blogID" : "uploads";

        // Get the service pod name for the target environment
        $servicePodName = $this->getPodName($target, "service-pod");

        // Assemble the command for syncing S3 buckets
        $command = "kubectl exec -it -n hale-platform-$target $servicePodName -- bin/sh -c \"" .
                "aws s3 sync s3://$sourceBucket/$uploadsDir " .
                "s3://$targetBucket/$uploadsDir --acl=public-read\"";

        // Execute the kubectl cp command
        passthru($command, $status);

        // Check if the command failed
        if ($status !== 0) {
            // An error occurred, handle it here
            throw new \InvalidArgumentException(
                "Error: Failed to execute s3 sync. Command run: \n$command"
            );
        }
    }

    /**
     * Retrieves the site slug (path) for a specific blog ID in a WordPress multisite environment.
     *
     * @param string $target The target environment.
     * @param int $blogId The blog ID for which to fetch the site slug.
     * @param string $wpCliPath The path to the WP-CLI executable (default is 'wp').
     *
     * @return string The site slug (path) corresponding to the specified blog ID.
     */
    public function getSiteSlugByBlogId($target, $blogID)
    {
        // Get the command for executing operations in the container
        $containerExec = $this->getExecCommand($target);

        // Use the WP-CLI db query command to fetch the site slug for the specified blog ID
        $command = "$containerExec wp db query 'SELECT path FROM wp_blogs WHERE blog_id = {$blogID}' --skip-column-names";
        $output = shell_exec($command);

        // Define the regular expression pattern to match the content between the inner slashes
        $pattern = '/\/(.*?)\//';

        // Perform a regular expression match to find the content between the inner slashes
        preg_match($pattern, $output, $matches);

        // Extract the matched content from the regex match result
        if (isset($matches[1])) {
            $contentBetweenSlashes = $matches[1];
        } else {
            $contentBetweenSlashes = "No content found";
        }

        return $contentBetweenSlashes; // Output: playground
    }
}
