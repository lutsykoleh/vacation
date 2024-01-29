<?php

namespace Drupal\workflow;

use Drupal\workflow\Entity\Workflow;

/**
 * Wrapper methods for Workflow* objects.
 *
 * Using this trait will add getWorkflow(), getWorkflowID() and setWorkflow()
 * methods to the class.
 *
 * @ingroup workflow
 */
trait WorkflowTypeAttributeTrait {

  /**
   * The machine_name of the attached Workflow.
   *
   * @var string
   */
  protected $wid = '';

  /**
   * The attached Workflow.
   *
   * It must explicitly be defined, and not be public, to avoid errors
   * when exporting with json_encode().
   *
   * @var \Drupal\workflow\Entity\Workflow
   */
  protected $workflow = NULL;

  /**
   * @param \Drupal\workflow\Entity\Workflow $workflow
   */
  public function setWorkflow(Workflow $workflow = NULL) {
    $this->wid = '';
    $this->workflow = NULL;
    if ($workflow) {
      $this->wid = $workflow->id();
      $this->workflow = $workflow;
    }
  }

  /**
   * Returns the Workflow object of this object.
   *
   * @return \Drupal\workflow\Entity\Workflow
   *   Workflow object.
   */
  public function getWorkflow() {
    if (!empty($this->workflow)) {
      return $this->workflow;
    }

    /* @noinspection PhpAssignmentInConditionInspection */
    if ($wid = $this->getWorkflowId()) {
      $this->workflow = Workflow::load($wid);
    }
    return $this->workflow;
  }

  /**
   * Sets the Workflow ID of this object.
   *
   * @param string $wid
   *   Workflow ID
   *
   * @return object
   */
  public function setWorkflowId($wid) {
    $this->wid = $wid;
    $this->workflow = NULL;
    return $this;
  }

  /**
   * Returns the Workflow ID of this object.
   *
   * @return string
   *   Workflow Id.
   */
  public function getWorkflowId() {
    /** @var \Drupal\Core\Entity\ContentEntityBase $this */
    if (!empty($this->wid)) {
      return $this->wid;
    }

    $value = $this->get('wid');
    if (is_string($value)) {
      $this->wid = $value;
    }
    elseif (is_object($value)) {
      // In WorkflowTransition.
      $wid = isset($value->getValue()[0]['target_id']) ? $value->getValue()[0]['target_id'] : '';
      $this->wid = $wid;
    }
    else {
      workflow_debug(__FILE__, __FUNCTION__, __LINE__, '', '');
    }

    return $this->wid;
  }

}
