<?php
namespace App\Helpers;

class Docker 
{
    /**
     * Constructs the docker cp command to copy a file from a Docker container with retries.
     *
     * @param string $filePath     The path to the file inside the Docker container.
     * @param string $fileName      File name.
     * @return string              The constructed docker cp command.
     */
    public function buildDockerCpCommand($filePath, $fileName) {
        // Set the base docker cp command
        $command = "docker cp";
        
        // Append options to the command
        $command .= " $filePath wordpress:/var/www/html/$fileName";

        return $command;
    }
}
