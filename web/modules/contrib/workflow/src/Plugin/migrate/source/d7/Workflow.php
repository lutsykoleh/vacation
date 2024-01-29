<?php

namespace Drupal\workflow\Plugin\migrate\source\d7;

use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 workflow source from database.
 *
 * @MigrateSource(
 *   id = "d7_workflow",
 *   source_module = "workflow"
 * )
 */
class Workflow extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('workflows', 'w')
      ->fields('w')
      ->condition('w.wid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'wid' => $this->t('Workflow ID'),
      'name' => $this->t('Machine readable name'),
      'label' => $this->t('Human readable name'),
      'status' => $this->t('Status'),
      'module' => $this->t('Module'),
      'tab_roles' => $this->t('Role IDs that can access the workflow tabs on node pages'),
      'options' => $this->t('Additional settings'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'wid' => [
        'type' => 'integer',
        'alias' => 'w',
      ],
    ];
  }

}
