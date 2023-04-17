# Wordpress Online Resource Manager (WORM)

![WORM cli interface](https://github.com/ministryofjustice/worm/blob/assets/screenshot.png)

[![PHP linting and CS Git Action](https://github.com/ministryofjustice/worm/actions/workflows/phplint.yml/badge.svg)](https://github.com/ministryofjustice/worm/actions/workflows/phplint.yml)

WORM is a command line tool that helps manage databases and media assets
generated by WordPress between different environments on CloudPlatform.

It is based off the [Laravel-Zero](https://laravel-zero.com/) framework.

## Environments

WORM targets the following environments, `Local`, `Demo`, `Dev`, `Staging` & `Prod`.

## Features

* Download database or media assets from any environment.
* Upload database or media assets from any environment.
* Check WordPress sites installed on multisite and their blog ids.
* Display wpsecrets `worm status --secrets`
* Display list of sites on multisite cluster `worm listSites`
* Setup AWS Profiles for s3, rds and ecr list for current namespace `worm setup:createProfiles`
* Migrate - `worm migrate <source> <target>` will move the database and s3
  assets from one environment to another. Todo: currently only works with
  staging, dev, demo migrating to local.

## Required

* [AWS
  Cli](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html) setup on your local machine
* [PHP Composer](https://getcomposer.org/) - generates the /vendor folder with
  autoloader that is required to run app.
* [Authentication to
  CloudPlatforms](https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/kubectl-config.html) k8s cluster setup on your local machine
* [Cloud Platform's
  cli](https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/cloud-platform-cli.html#cloud-platform-cli)

## Installation

Step 1: Install WORM via Composer
Download and run `composer install` in this repository's root directory. This
will create a vendor folder.

Step 2: Install WORM globally on your machine
Then run `make install` which compiles the app
into a binary and system links it so that it is available globally on your
machine. You will be prompted to enter your Mac OS password so the system links can be established. You will
also need to have `AWS`, `kubectl`, `cloud-platform` and `php` installed on your command line.

Step 3: Setup your AWS profiles
Once WORM is installed the first thing to setup is your AWS profiles. In each
kubernetes namespaces run `worm create:profiles`. This creates a standard and
unique set of aws profiles in your computer's root directory in the `.aws`
folder.

## Quick guide

Make sure you are in the correct namespace ie `kubens hale-platform-dev`.

### Database download

`worm db:export` or a specific site db `worm db:export --blogID[=BLOGID]`

### Database upload

`worm db:import <target env> <path of sql>` or upload to specific site add --blogID[=BLOGID]

### Download s3 media locally

Make sure you are in the hale-platform repo root on your local machine.

`worm s3:download <s3 bucket> <aws profile name>`

### Upload s3 media to cloud environment

`worm s3:upload <bucket> <profile>`

## License

Open-source software licensed under the MIT license.
