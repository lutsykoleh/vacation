<?php

namespace Drupal\workflow;

/**
 * Provides route parameters for workflow entity types.
 */
trait WorkflowURLRouteParametersTrait {

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    $uri_route_parameters['workflow_type'] = $this->getWorkflowId();
    return $uri_route_parameters;
  }

}
