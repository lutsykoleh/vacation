<?php

/**
 * @file
 * Implements 'rules' event for 'Revert' link to the first workflow history row.
 */

/**
 * Implements hook_rules_event_info().
 *
 * @todo Add support for every entity_type in Revert rules.
 */
function workflow_rules_event_info() {
  workflow_debug(__FILE__, __FUNCTION__, __LINE__); // @todo D8: test this snippet.
  $events = [
    'workflow_state_reverted' => [
      'group' => t('Workflow'),
      'label' => t('Workflow state reverted'),
      'variables' => rules_events_node_variables(t('updated content'), TRUE),
    ],
  ];
  return $events;
}
