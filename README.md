# Table of Contents

 * [Setup](#setup)
   * [Database setup](#database-setup)
   * [Composer](#composer)
   * [Drush](#drush)
   * [Code quality](#code-quality)
   * [Theme setup](#theme-setup)
 * [Configuration and features](#configuration-and-features)
 * [Testing](#testing)
 * [References](#references)
 * [Troubleshooting](#troubleshooting)

# Setup

Humanitarian Action (GHI) uses docksal, a web-development environment based on
docker.

Install docksal: https://docksal.io/installation

You will need to create a local environment file in the `./.docksal` folder and
adjust it to match your local requirements.

    touch ./.docksal/docksal-local.env

Certain environment variables need to be requested from HPC and placed in the
local environment file in order for the website to function properly. Refer to
`./.docksal/default.docksal-local.env` for required environment variables.

Once the above steps are complete, from the project root, run:

    fin init-site

This will install all required packages via composer and setup the local
settings files.

For docksal to run, you will need to stop any other webserver or service on
your system that might bind to port 80.


## Database Setup

A database has been created automatically as part of the stack setup above, but it has no content yet.

Pull a database dump from [snapshots](https://snapshots.aws.ahconu.org/ghi) to
get a fresh copy and seed your local database.


## Composer

Using docksal, you can run any composer command as `fin composer {COMMAND}`.


## Drush

Drush commands can be run as `fin drush {COMMAND}`. Eg:

    fin drush cr


## Code Quality

Use PHP Codesniffer to assure code quality.

Code linting

    fin exec vendor/bin/phpcs -p --report=full ./html/modules/custom --extensions=module/php,php/php,inc/php

Drupal best practices

    fin exec vendor/bin/phpcs --standard=DrupalPractice --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md,yml ./html/modules/custom/


## Theme Setup

The public-facing theme is [common_design_subtheme](https://github.com/UN-OCHA/ghi-site/tree/master/html/themes/custom/common_design_subtheme) based on the official OCHA base-theme named [common_design](https://github.com/UN-OCHA/common_design). Initial setup is as follows:

    cd html/themes/custom/common_design_subtheme/
    nvm use
    npm install

While working on style changes, update the `.scss` files under the sub-theme's
`sass` folder. Once done with the changes, run the below commands:

    npm run sass:lint-fix
    npm run sass:build

The Common Design provides tools to pick branding colors out of the box without
overriding any files. See the sub-theme's README for more details. Should you
need to modify the Common Design, there is a Drupal Library located at
`common_design_subtheme/components/ghi-cd-overrides` which can hold all the
overrides in one central location.


# Configuration and Features

To import all current config from the branch, run the following:

    fin drush cim

If you make changes that can be exported, write them to disk and commit them to git:

    fin drush cex


# Testing

PHPUnit tests for the custom modules can be run via docksal like this:

    fin phpunit

The test configuration is in `phpunit.xml`.

The tests can be optionally filtered down very specifically, e.g.:

    fin phpunit --filter testImportParagraphs


# References

Make sure to follow the [OCHA Standards for Drupal 8+ Websites](https://docs.google.com/document/d/1JMTLyx1dgVMe5Xo85Zn125TX0632y4mDlnUIGqVeAF8/edit#heading=h.yjlosjy2hedn)


# Troubleshooting

TBD
