<?php

namespace Drupal\workflow\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 workflow scheduled transitions source from database.
 *
 * @MigrateSource(
 *   id = "d7_workflow_scheduled_transition",
 *   source_module = "workflow"
 * )
 */
class WorkflowScheduledTransition extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('workflow_scheduled_transition', 'wst')
      ->fields('wst')
      ->condition('wst.tid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'tid' => $this->t('Workflow Scheduled Transition ID'),
      'entity_type' => $this->t('Type of entity this transition belongs to'),
      'nid' => $this->t('Entity ID of the object this transition belongs to'),
      'field_name' => $this->t('Name of the field the transition relates to'),
      'language' => $this->t('Language of the entity'),
      'delta' => $this->t('Sequence number for this data item, used for multi-value fields'),
      'old_sid' => $this->t('Sid this state starts at'),
      'sid' => $this->t('Sid this state transitions to'),
      'uid' => $this->t('User who scheduled this state transition'),
      'scheduled' => $this->t('Date this transition is scheduled for'),
      'comment' => $this->t('Comment explaining this transition'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'tid' => [
        'type' => 'integer',
        'alias' => 'wst',
      ],
    ];
  }

}
