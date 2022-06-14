
CONTENTS OF THIS FILE
---------------------

 * Setup
 * Configuration and features
 * Testing
 * Troubleshooting

SETUP
-----

Tbc.


CONFIGURATION AND FEATURES
--------------------------

Tbc.


TESTING
-------

    fin phpunit -c /var/www/phpunit.xml html/modules/custom

The tests can be optionally filtered down very specifically, e.g.:

    fin phpunit -c /var/www/phpunit.xml html/modules/custom --filter testImportParagraphs


TROUBLESHOOTING
---------------

If you run into this error when committing:

    Error: Could not find "stylelint-config-standard". Do you need a `configBasedir`?

You need to install all Drupal packages by doing this from the project root:

    fin exec "cd html/core && yarn"