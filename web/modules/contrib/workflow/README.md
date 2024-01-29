# Customise for other Entity types than node

When creating your own migrations, the file d7_workflow_transition.yml and
d7_workflow_scheduled_transition.yml will need to be copied and customised per
entity migration so that the following items make reference to the correct
migrated values

```yaml
process:
  entity_type:
    -
      plugin: skip_on_value
      source: entity_type
      method: row
      not_equals: true
      value: node
  entity_id:
    -
      plugin: migration_lookup
      migration: d7_node
      source: nid
  revision_id:
    -
      plugin: migration_lookup
      migration: d7_node_revision
      source: revision_id
migration_dependencies:
  required:
    - d7_node
    - d7_node_revision
  optional:
    - d7_node
    - d7_node_revision
```

Here d7_node and d7_node_revision are to be replaced with the name of migration
configuration for the other entity and node replaced with the name of the entity/

```yaml
process:
  entity_type:
    -
      plugin: skip_on_value
      source: entity_type
      method: row
      not_equals: true
      value: node
  entity_id:
    -
      plugin: migration_lookup
      migration: d7_node
      source: nid
migration_dependencies:
  required:
    - d7_node
    - d7_user
    - d7_workflow_state
  optional:
    - d7_node
    - d7_user
    - d7_workflow_state
```
