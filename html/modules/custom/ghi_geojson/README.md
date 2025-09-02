# Purpose

This directory contains re-usable assets that are intentionally added to the
repository instead of make them manageble in the Drupal system (or by other
means) as content.

## GeoJSON

### Directory structure

GeoJSON shapefiles for country (admin level 0) and administrative boundaries
inside a country (admin level 1 and higher) are stored following this directory
structure.

    assets/geojson
    - [ISO3]
      - current
        - [ISO]_0.geojson / [ISO]_0.min.geojson
        - adm1
          - [PCODE].geojson / - [PCODE].min.geojson
        - adm2
          - [PCODE].geojson / - [PCODE].min.geojson
        ..
      - 2024
        - [ISO]_0.geojson / [ISO]_0.min.geojson
        - adm1
          - [PCODE].geojson / - [PCODE].min.geojson
        - adm2
          - [PCODE].geojson / - [PCODE].min.geojson
        ..

### Optimizations

The minified versions of the shapefiles have been created using
https://github.com/ben-nour/geojson-shave and some command line tools.

For country level:

    cd geojson
    find . | grep _0.geojson | awk -F '.' '{print "geojson-shave -d 2 "substr($0,3)" -o "substr($2,2)".min.geojson"}'

For the admin level 1+ files:

    cd geojson
    find . | grep '\.geojson' | grep -v '_0' | awk -F '/' '{print "geojson-shave "$0" -o "$1"/"$2"/"$3"/"$4"/"substr($5, 0, index($5, "."))"min"substr($5, index($5, "."))}'

Use the above commands to preview the commands, the add an `| sh` at the end to execute them.

### Usage in Drupal

These shape files are used for the homepage maps to highlight countries and
associated countries, as well as in attachment and choropleth maps.

See `\Drupal\ghi_base_objects\ApiObjects\Location` for technical details.