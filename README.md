
CONTENTS OF THIS FILE
---------------------

 * Setup
 * Database setup
 * Search using solr
 * Composer
 * Drush
 * Code quality
 * Theme setup
 * Configuration and features
 * Testing
 * References
 * Troubleshooting

SETUP
-----

Humanitarian Action (GHI) uses docksal which is a web-development environment
based on docker.

Install docksal: https://docksal.io/installation

You will need to create a local environment file in the _./.docksal_ folder and
adjust it to match your local requirements.

    touch ./.docksal/docksal-local.env

Refer to _./.docksal/default.docksal-local.env_ for required environment
variables.

Once the above steps are complete, from the project root, run:

    fin init-site

This will install all required packages via composer and setup the local
settings files.

For docksal to run, you will need to stop any other webserver or service on
your system that might bind to port 80.


DATABASE SETUP
--------------

A database has been created automatically as part of the stack setup above.

Pull a database dump from [here](https://snapshots.aws.ahconu.org/ghi) to get a
fresh copy and seed your local database.


SEARCH USING SOLR
-----------------

The integrated search feature is based on solr. The local docksal stack comes
with a solr instance that is already setup in Drupal. On the first setup of the
site (or when troubleshooting later), you need to create the solr core using
this command.

    fin solr create-core

Once that's done, go to the [Search Api configuration backend page](http://ghi-site.docksal.site/admin/config/search/search-api) and confirm that
the search server and the index are there and enabled. You can then start
indexing content using this command:

    fin solr index


COMPOSER
--------

Using docksal, you can run any composer command as **_fin composer {COMMAND}_**.


DRUSH
-----

Drush commands can be run as **_fin drush {COMMAND}_**. Eg:

    fin drush cr


CODE QUALITY
------------

Use PHP Codesniffer to assure code quality.

Code linting

    fin exec vendor/bin/phpcs -p --report=full ./html/modules/custom --extensions=module/php,php/php,inc/php

Drupal best practices

    fin exec vendor/bin/phpcs --standard=DrupalPractice --extensions=php,module,inc,install,test,profile,theme,css,info,txt,md,yml ./html/modules/custom/


THEME SETUP
-----------

We have created a sub-theme common_design_subtheme from the OCHA provided base
theme named [common_design](https://github.com/UN-OCHA/common_design).

While working on style changes, update the _.sass_ files under the sass folder.
Once done with the changes, run the below commands:

    cd html/themes/custom/common_design_subtheme/
    nvm use
    npm install
    npm run sass:lint-fix
    npm run sass:build



CONFIGURATION AND FEATURES
--------------------------

Tbc.


IMPORTING DATABASE SNAPSHOT
---------------------------

Download database snapshots from https://snapshots.aws.ahconu.org/ghi/ and
place them in **_.docksal_/backups**

To import a snapshot and run all the steps that a deployment would run please
use this command:

    fin post-deploy -i

If you have multiple database snapshots available locally, running this command
will let you select which one you want to import.
Running this without the _-i_ argument, will just run the deployment actions on
the current database without importing a snapshot.


Data migrations
---------------

The data migrations can be run locally with this command:

    fin update-data


TESTING
-------

PHPUnit tests for the custom modules can be run via docksal like this:

    fin phpunit

The test configuration is in _phpunit.xml_.

The tests can be optionally filtered down very specifically, e.g.:

    fin phpunit --filter testImportParagraphs


REFERENCES
----------

Make sure to follow the [OCHA Standards for Drupal 8+ Websites](https://docs.google.com/document/d/1JMTLyx1dgVMe5Xo85Zn125TX0632y4mDlnUIGqVeAF8/edit#heading=h.yjlosjy2hedn)


TROUBLESHOOTING
---------------

If you run into this error when committing:

    Error: Could not find "stylelint-config-standard". Do you need a `configBasedir`?

You need to install all Drupal packages by doing this from the project root:

    fin exec "cd html/core && yarn"