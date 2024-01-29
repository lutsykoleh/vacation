<?php

/**
 * @file
 * Implements entity hooks.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\workflow\WorkflowAccessControlHandler;

/**
 * @inheritdoc
 *
 * Core hooks: Change the operations column in a Entity list.
 * Adds a 'revert' operation.
 *
 * @see EntityListBuilder::getOperations()
 */
function workflow_entity_operation(EntityInterface $entity) {
  $operations = [];

  // Only add 'revert' to the first row. Skip all following records.
  static $first = TRUE;
  if (!$first) {
    return $operations;
  }

  // Check correct entity type.
  if (!in_array($entity->getEntityTypeId(), ['workflow_transition'])) {
    // Avoid future calls.
    $first = FALSE;
    return $operations;
  }

  /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
  $transition = $entity;

  if (!$transition->isRevertable()) {
    // Some states are not fit to revert to.
    // In each of these cases, prohibit to revert to an even older state.
    $first = FALSE;
    return $operations;
  }

  $user = workflow_current_user();
  $handler = new WorkflowAccessControlHandler($transition->getEntityType());
  if ($handler->access($transition, 'revert', $user, FALSE)) {
    // User has access to revert to a previous state.
    // And the operation is not vetoed by other module.

    // Note: revert_form route is determined in WorkflowTransition Annotation.
    $operations['revert'] = [
      'title' => t('Revert to last state'),
      'url' => Url::fromRoute(
        'entity.workflow_transition.revert_form',
        ['workflow_transition' => $transition->id()]
      ),
      'query' => \Drupal::destination()->getAsArray(),
      'weight' => 50,
    ];

    // No need to read the following records.
    $first = FALSE;
  }

  return $operations;
}
