<?php

/**
 * @file
 * Support migration of workflows from older versions.
 */

/**
 * Implements hook_migrate_MIGRATION_ID_prepare_row().
 */
function workflow_migrate_upgrade_d7_workflow_state_prepare_row(\Drupal\migrate\Row $row, \Drupal\migrate\Plugin\MigrateSourceInterface $source, \Drupal\migrate\Plugin\MigrationInterface $migration) {
  if ('(creation)' == $row->getSourceProperty('name')) {
    $row->setSourceProperty('name', WORKFLOW_CREATION_STATE_NAME);
  }
}

/**
 * Implements hook_migrate_MIGRATION_ID_prepare_row().
 */
function workflow_migrate_upgrade_d7_workflow_transition_prepare_row(\Drupal\migrate\Row $row, \Drupal\migrate\Plugin\MigrateSourceInterface $source, \Drupal\migrate\Plugin\MigrationInterface $migration) {
  static $sid2wid = [];
  static $migration = NULL;

  $sid = $row->getSourceProperty('sid');

  if (!isset($sid2wid[$sid])) {
    if (!$migration) {
      $migrations = \Drupal::service('plugin.manager.migration')
        ->createInstances('upgrade_d7_workflow_state');
      $migration = reset($migrations);
    }
    if ($migration) {
      $new_sid = reset($migration
        ->getIdMap()
        ->lookupDestinationId([$sid]));
      // Set source and target lookups.
      $sid2wid[$row->getSourceProperty('old_sid')] =
      $sid2wid[$sid] = WorkflowState::load($new_sid)->getWorkflowId();
    }
  }
  if (isset($sid2wid[$sid])) {
    $row->setSourceProperty('wid', $sid2wid[$sid]);
  }
}

/**
 * Implements hook_migrate_MIGRATION_ID_prepare_row().
 */
function workflow_migrate_upgrade_d7_workflow_scheduled_transition_prepare_row(\Drupal\migrate\Row $row, \Drupal\migrate\Plugin\MigrateSourceInterface $source, \Drupal\migrate\Plugin\MigrationInterface $migration) {
  workflow_migrate_upgrade_d7_workflow_transition_prepare_row($row, $source, $migration);
}
