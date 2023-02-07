
CONTENTS OF THIS FILE
---------------------

 * Setup
 * Configuration and features
 * Testing
 * Troubleshooting

SETUP
-----

Humanitarian Action (GHI) uses docksal which is a web-development environment
based on docker.

Install docksal: https://docksal.io/installation

You will need to create a local environment file in the _./.docksal_ folder and
adjust it to match your local requirements.

    touch ./.docksal/docksal-local.env

You add a port mapping for MySQL to use a specific port:

    MYSQL_PORT_MAPPING='3312:3306'

In order for the mapbox-based maps to work, an access token must be set:

    MAPBOX_TOKEN="THE MAPBOX ACCESS TOKEN"

For docksal to run, you will need to stop the apache service on your system.

Once the above steps are complete, from the project root, run:

    fin init

This should set the stack up and running.


CONFIGURATION AND FEATURES
--------------------------

Tbc.


TESTING
-------

    fin phpunit -c /var/www/phpunit.xml html/modules/custom

The tests can be optionally filtered down very specifically, e.g.:

    fin phpunit -c /var/www/phpunit.xml html/modules/custom --filter testImportParagraphs


REFERENCES
----------

Make sure to follow the [OCHA Standards for Drupal 8+ Websites](https://docs.google.com/document/d/1JMTLyx1dgVMe5Xo85Zn125TX0632y4mDlnUIGqVeAF8/edit#heading=h.yjlosjy2hedn)


TROUBLESHOOTING
---------------

If you run into this error when committing:

    Error: Could not find "stylelint-config-standard". Do you need a `configBasedir`?

You need to install all Drupal packages by doing this from the project root:

    fin exec "cd html/core && yarn"