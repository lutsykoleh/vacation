<?php

namespace Drupal\workflow\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a workflow status field mapper.
 *
 * @FeedsTarget(
 * id = "workflow_feeds_target",
 * field_types = {"workflow"}
 * )
 */
class Workflow extends FieldTargetBase {

  /**
   * {@inheritdoc}
   *
   * @see https://www.drupal.org/project/workflow/issues/2913828
   *
   * In the mapping section of Feeds importer,
   * choose your Workflow field available in the "Select a target" drop down.
   * Please note, the Workflow mapping expects the key and not value of your
   * Workflow field. Meaning, the ID of your states not the label.
   * You can find these at
   *   "/admin/config/workflow/workflow/[your_workflow_id]/states".
   * Caveats:
   *  1. This code was not tested thoroughly;
   *  2. This code will only import the workflow state, not workflow comment;
   *  3. Please post an example import file in the ticket.
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return parent::prepareTarget($field_definition);
  }

}
