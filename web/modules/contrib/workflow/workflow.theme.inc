<?php

/**
 * @file
 * Preprocessors for workflow elements.
 */

use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;

/**
 * Prepares variables for workflow_transition templates.
 *
 * Default template: workflow-transition.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: An associative array containing the properties and children of
 *     the details element. Properties used: #children.
 */
function template_preprocess_workflow_transition(array &$variables) {
  /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $workflowTransition */
  $workflowTransition = $variables['elements']['#workflow_transition'];

  $variables['from_state'] = $workflowTransition->getFromState();
  $variables['to_state'] = $workflowTransition->getToState();
  $variables['time'] = $workflowTransition->getTimestampFormatted();
  $variables['user'] = $workflowTransition->getOwner();
  $variables['comment'] = $workflowTransition->getComment();

  $variables['field_attributes'] = new Attribute();
  $variables['label_attributes'] = new Attribute();
  $variables['value_attributes'] = new Attribute();

  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }

  $variables['attributes']['class'][] = 'workflow-transition';
}
