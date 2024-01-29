<?php

namespace Drupal\workflow\Plugin\migrate\field\d7;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * MigrateField plugin for Drupal 7 workflow field.
 *
 * @MigrateField(
 *   id = "workflow",
 *   core = {7},
 *   type_map = {
 *    "workflow" = "workflow"
 *   },
 *   source_module = "workflow",
 *   destination_module = "workflow",
 * )
 */
class WorkflowField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'workflow_default' => 'workflow_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'workflow_default' => 'workflow_default',
    ];
  }

}
