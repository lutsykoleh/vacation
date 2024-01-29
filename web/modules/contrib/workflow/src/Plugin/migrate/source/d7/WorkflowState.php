<?php

namespace Drupal\workflow\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 workflow states source from database.
 *
 * @MigrateSource(
 *   id = "d7_workflow_state",
 *   source_module = "workflow"
 * )
 */
class WorkflowState extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('workflow_states', 'ws')
      ->fields('ws')
      ->condition('ws.sid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'sid' => $this->t('Workflow State ID'),
      'wid' => $this->t('Associated workflow ID'),
      'name' => $this->t('Machine readable name'),
      'state' => $this->t('Human readable name'),
      'weight' => $this->t('Weight (order) of the state'),
      'sysid' => $this->t('Type of state, usually either WORKFLOW_CREATION or empty'),
      'status' => $this->t('Status'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'sid' => [
        'type' => 'integer',
        'alias' => 'ws',
      ],
    ];
  }

}
