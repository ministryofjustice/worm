# WordPress Online Resource Manager (WORM)

![WORM cli interface](https://github.com/ministryofjustice/worm/blob/assets/screenshot.png)

[![Apply linting and code sniffer](https://github.com/ministryofjustice/worm/actions/workflows/lint.yml/badge.svg)](https://github.com/ministryofjustice/worm/actions/workflows/lint.yml)

WORM is a command line tool for managing WordPress multisite databases and
media assets across environments on the MoJ [Cloud Platform](https://user-guide.cloud-platform.service.justice.gov.uk/).
It can export/import databases, sync S3 media, migrate whole sites (or single
blogs) between environments, and inspect the state of the cluster.

Built on the [Laravel Zero](https://laravel-zero.com/) framework.

## Environments

WORM works with five environments: `local`, `demo`, `dev`, `staging` and `prod`.

## Requirements

Make sure the following are installed and configured before installing WORM:

* [PHP](https://www.php.net/) 8.2 or later and [Composer](https://getcomposer.org/)
* [kubectl, authenticated to the Cloud Platform cluster](https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/kubectl-config.html)
* [Cloud Platform CLI](https://user-guide.cloud-platform.service.justice.gov.uk/documentation/getting-started/cloud-platform-cli.html#cloud-platform-cli)
  (`brew install ministryofjustice/cloud-platform-tap/cloud-platform-cli`)
* [AWS CLI](https://aws.amazon.com/cli/)

## Installation

1. Clone the repository:

   ```sh
   git clone git@github.com:ministryofjustice/worm.git
   cd worm
   ```

2. Install PHP dependencies:

   ```sh
   composer install
   ```

3. Build and install WORM globally:

   ```sh
   make install
   ```

   This compiles WORM into a binary and symlinks it into `/usr/local/bin` so
   `worm` is available from any directory. You will be prompted for your
   password so the symlink can be created.

   `make install` first asks whether you want to tag a new version. Press
   enter to skip and build the version already tagged — see
   [Releasing a new version](#releasing-a-new-version) if you do want to bump it.

To verify the install, run `worm` in a new terminal window — you should see
the list of available commands. `worm --version` shows the installed version.

Re-run `make install` at any time to rebuild and repoint the symlink at the
latest build.

### Install options

| Variable | Purpose |
| --- | --- |
| `VERSION` | Version to tag and build, skipping the prompt, e.g. `make install VERSION=2.1.0` |
| `WORM_VERSION` | Version to build **without** tagging, e.g. `WORM_VERSION=2.1.0-rc1 make install` |
| `WORM_BIN_DIR` | Directory to symlink into, instead of `/usr/local/bin`, e.g. `WORM_BIN_DIR=~/bin make install` |

Running `./install.sh` directly works the same way, and takes the version as
its first argument (`./install.sh 2.1.0`) — it just does not offer to tag.

## Releasing a new version

The version stamped into the binary comes from the git tags, so releasing is a
matter of tagging the commit you want to ship:

```sh
make install
# Current version: 2.0.0
# New version to tag (blank to build 2.0.0): 2.0.1
```

That creates an annotated `2.0.1` tag on the current commit and builds the
binary at that version. Then push the tag:

```sh
git push origin 2.0.1
```

Points to watch:

* Tag the commit you are shipping. The version is read from the tags on the
  checked-out commit, so merge your work to `main` before tagging it.
* `make install` never pushes. It prints the `git push` command for you to run.
* Tagging is refused if the version already exists, so re-running the install
  cannot move an existing release tag.
* Without any tags — a source download rather than a clone, say — the build
  falls back to `WORM_FALLBACK_VERSION` in `install.sh`.

## Command reference

| Command | Description |
| --- | --- |
| `worm status [--secrets]` | Show current k8s cluster connection details; `--secrets` also prints namespace secrets |
| `worm sites` | List all sites in the multisite installation with their blog IDs |
| `worm switch <env>` | Switch kubectl context to another environment (`prod`, `staging`, `dev`, `demo`) |
| `worm jump <env>` | Open a shell into the first available pod in an environment |
| `worm releases` | Show the multisite deployment history for the environment |
| `worm rollback [--revision=N]` | Roll back the WordPress container to a revision (previous revision by default) |
| `worm db:export <env> [--blogID=ID]` | Export a database as a `.sql` file — whole multisite, or one site with `--blogID` |
| `worm db:import <env> <file> [--blogID=ID] [--s3sync=true]` | Import a `.sql` file into an environment; `--s3sync=true` also syncs media and rewrites bucket URLs |
| `worm s3:download <bucket> <profile> [--blogID=ID]` | Download media assets from an S3 bucket using an AWS profile |
| `worm s3:upload <bucket> <profile> [--blogID=ID]` | Upload media assets to an S3 bucket using an AWS profile |
| `worm migrate <source> <target> [options]` | Migrate database and S3 assets between environments |
| `worm setup:profile` | Create AWS profiles for the current namespace |

Run `worm <command> --help` for full details on any command.

## Quick guide

Before running commands, make sure you are in the `hale-platform` repo root
and connected to the right namespace, e.g. `kubens hale-platform-dev`.

### Check where you are

```sh
worm status            # current cluster connection
worm status --secrets  # include namespace secrets
worm sites             # list sites and their blog IDs
```

### Export a database

```sh
worm db:export staging              # whole multisite from staging
worm db:export staging --blogID=5   # single site (blog ID 5) from staging
```

### Import a database

```sh
worm db:import dev path/to/dump.sql              # whole multisite into dev
worm db:import dev path/to/dump.sql --blogID=5   # replace one site's tables
worm db:import dev path/to/dump.sql --s3sync=true # also sync media assets
```

### Sync S3 media

```sh
worm s3:download <bucket> <aws-profile>   # download media locally
worm s3:upload <bucket> <aws-profile>     # upload media to an environment
```

Add `--blogID=ID` to either command to limit it to a single site.

### Migrate between environments

`worm migrate` moves both the database and S3 assets in one step.

```sh
# Whole multisite from staging to demo
worm migrate staging demo

# Whole multisite from staging to local
worm migrate staging local

# Single site (every site has its own blog ID)
worm migrate demo local --blogID=56

# Include users in a whole-multisite migration (not migrated by default)
worm migrate staging demo --migrateUsers=true

# Keep the production domain on a non-prod environment, e.g. move CCRC
# to demo with the domain ccrc.gov.uk
worm migrate staging demo --blogID=5 --keepProdDomain=true

# Revert to the environment domain
worm migrate staging demo --blogID=5 --keepProdDomain=false
```

Note: migrating **to local** currently only works from `staging`, `dev` and
`demo`.

## License

Open-source software licensed under the MIT license.
