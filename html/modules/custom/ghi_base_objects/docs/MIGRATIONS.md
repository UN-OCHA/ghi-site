# Migrations for HPC plans and related objects #

## Available object types ##

This modules includes migrations for some base object types from HPC, which are used in GHI.

- Plans
- Plan entities
- Governing entities
- Countries
- Organizations

There are also migrations to import some of the HPC categories

- Plan types
- Plan costing types
- Organization types


## Running migrations with drush ##

Import/update all available data from the HPC API

```drush migrate:import --group=hpc_api_data --update```

Import/update only plans

```drush migrate:import plan --update```

Import/update only plan entities

```drush migrate:import plan_entity --update```

Import/update only governing entities

```drush migrate:import governing_entity --update```

Import/update only countries

```drush migrate:import country --update```


## Modifying migrations ##
When making changes to the migration files in `config/install`, they need to be re-imported to take effect:

```drush cim --partial --source="modules/custom/ghi_plans/config/install" -y```