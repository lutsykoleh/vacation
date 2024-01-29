<?php

namespace Drupal\workflow\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 workflow transitions source from database.
 *
 * @MigrateSource(
 *   id = "d7_workflow_transition",
 *   source_module = "workflow"
 * )
 */
class WorkflowTransition extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('workflow_node_history', 'wnh')
      ->fields('wnh')
      ->condition('wnh.hid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'hid' => $this->t('Workflow Transition History ID'),
      'entity_type' => $this->t('Entity type this transition belongs to'),
      'nid' => $this->t('Entity ID this record is for'),
      'revision_id' => $this->t('Current version identifier'),
      'field_name' => $this->t('Name of the field the transition relates to'),
      'language' => $this->t('Entity language code'),
      'delta' => $this->t('Sequence number for this data item, used for multi-value fields'),
      'old_sid' => $this->t('Sid this state starts at'),
      'sid' => $this->t('Sid this state transitions to'),
      'uid' => $this->t('User ID of the transition author'),
      'stamp' => $this->t('Date this transition was executed'),
      'comment' => $this->t('Comment explaining this transition'),
      'wid' => $this->t('Calculated new workflow id based upon sid value.'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'hid' => [
        'type' => 'integer',
        'alias' => 'wnh',
      ],
    ];
  }

}
