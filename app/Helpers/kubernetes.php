<?php

namespace App\Helpers;

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
     * Copy the database to the Kubernetes container running in the target environment.
     *
     */
    public function copyDatabaseToContainer($target, $podName, $sqlFilePath, $container = 'wordpress')
    {
        $command = passthru("kubectl cp --retries=10 -n hale-platform-$target -c $container $sqlFile hale-platform-$target/$podName:$sqlFile");
        
        var_dump($command); die();
        //passthru($command);
    }
}