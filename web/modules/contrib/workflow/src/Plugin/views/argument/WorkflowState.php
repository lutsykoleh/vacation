<?php

namespace Drupal\workflow\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * Argument handler to accept a Workflow State.
 *
 * @ViewsArgument("workflow_state")
 */
class WorkflowState extends StringArgument {

  /**
   * Override the behavior of title().
   *
   * Get the user friendly version of the workflow state.
   */
  public function title() {
    return workflow_get_sid_name($this->argument);
  }

}
