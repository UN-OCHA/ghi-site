
Command line tools to interact with the HPC API
===============================================

Purpose
-------

This directory contains some useful command line tools that allow to interact
with the HPA API.

    $ api
    Usages:
      api <endpoint path>
      api login

    Endpoint requests:
      api -e demo -v2 <endpoint path>
        [ -e PROD|DEMO|STAGE|DEV_BLUE|DEV_RED ]
        [ -v 1|2 ]
        [ -b Issue a backend request with API key]
        [ -h Issue an HID authenticated request]
        [ -s Show the resulting URL, but don't actually send the request]

    Login via HID:
      api login


Prerequisites
-------------

In order for these scripts to work, some environment variables need to be
configured. The easiest is to copy the `example.hpc.env` to `hpc.env` and set
the environment variables based on the template.

In order for the command line tools to be available, you need to include them
into your profile, on Linux/MacOS this can be done like this, depending on your
specific OS flavour:

    echo 'source PATH_TO_PROJECT/scripts/hpc-cli/hpc-api' >>~/.bash_profile

You also need to install jq from https://jqlang.org/