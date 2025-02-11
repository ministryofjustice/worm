#!/bin/bash

##################
# Install WORM
##################

dir=$(pwd)

# Check we have PHP installed
if ! command -v php &> /dev/null
then
    echo "PHP could not found on this machine, and is a requirement
    for WORM. Terminating install."
    exit
fi

# Check we have AWS cli installed
if ! command -v aws &> /dev/null
then
    echo "AWS cli could not found on this machine, and is a requirement
    for WORM. Terminating install."
    exit
fi

# Check we have kubectl installed
if ! command -v kubectl &> /dev/null
then
    echo "kubectl could not found on this machine, and is a requirement
    for WORM. Terminating install."
    exit
fi

# Check we have kubectl installed
if ! command -v cloud-platform &> /dev/null
then
    echo "cloud-platform could not found on this machine, and is a requirement
    for WORM. Run brew install ministryofjustice/cloud-platform-tap/cloud-platform-cli
    Terminating install."
    exit
fi

# Build binary of latest worm
php worm app:build --build-version=1.0.0 --no-interaction

# System link to add build to local $PATH
sudo ln -s $dir/builds/worm /usr/local/bin/worm

echo "WORM installed successfully"
