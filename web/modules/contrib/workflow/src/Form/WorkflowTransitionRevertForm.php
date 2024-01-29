<?php

namespace Drupal\workflow\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Implements a form to revert an entity to a previous state.
 *
 * @package Drupal\workflow\Form
 */
class WorkflowTransitionRevertForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workflow_transition_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->entity;
    $state = $transition->getFromState();

    if (!$state) {
      \Drupal::logger('workflow_revert')->error('Invalid state', []);
      $message = $this->t('Invalid transition. Your information has been recorded.');
      $this->messenger()->addError($message);
      return [];
    }

    $question = $this->t(
      'Are you sure you want to revert %title to the "@state" state?',
      [
        '@state' => $state->label(),
        '%title' => $transition->label(),
      ]
    );
    return $question;
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->entity;
    return $this->getUrl($transition);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   *
   * @todo The fact that we need to overwrite this function,
   * is an indicator that the Transition is not completely a complete Entity.
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // If Rules is available, signal the reversion.
    // @todo Move this Rules_invoke_event to hook outside this module.
    if (\Drupal::moduleHandler()->moduleExists('rules')) {
      rules_invoke_event('workflow_state_reverted', $this->entity);
    }

    $transition = $this->prepareRevertedTransition($this->entity);

    // The entity will be updated when the transition is executed. Keep the
    // original one for the confirmation message.
    $previous_sid = $transition->getToSid();

    // Force the transition because it's probably not valid.
    $transition->force(TRUE);
    $new_sid = workflow_execute_transition($transition, TRUE);

    $comment = ($previous_sid == $new_sid)
      ? $this->t('State is reverted.')
      : $this->t('State could not be reverted.');
    $this->messenger()->addWarning($comment);

    $form_state->setRedirectUrl($this->getUrl($transition));
  }

  /**
   * Prepares a transition to be reverted.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition to be reverted.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The prepared transition ready to be stored.
   */
  protected function prepareRevertedTransition(WorkflowTransitionInterface $transition) {
    $user = \Drupal::currentUser();

    $entity = $transition->getTargetEntity();
    $field_name = $transition->getFieldName();
    $current_sid = workflow_node_current_state($entity, $field_name);
    $previous_sid = $transition->getFromSid();
    $comment = $this->t('State reverted.');

    $transition = WorkflowTransition::create([$current_sid, 'field_name' => $field_name]);
    $transition->setTargetEntity($entity);
    $time = \Drupal::time()->getRequestTime();
    $transition->setValues($previous_sid, $user->id(), $time, $comment);

    return $transition;
  }

  /**
   * Returns the URL for a Transition.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   Transition object.
   *
   * @return \Drupal\Core\Url
   *   The Transition's URL.
   */
  private function getUrl(WorkflowTransitionInterface $transition) {
    $route_provider = \Drupal::service('router.route_provider');
    if (count($route_provider->getRoutesByNames(['entity.node.workflow_history'])) === 1) {
      $url = new Url('entity.node.workflow_history', [
        'node' => $transition->getTargetEntityId(),
        'field_name' => $transition->getFieldName(),
      ]);
    }
    else {
      $entity = $transition->getTargetEntity();
      $url = new Url($transition->getTargetEntity()->toUrl()->getRouteName(), [
        $entity->getEntityTypeId() => $transition->getTargetEntityId(),
        'field_name' => $transition->getFieldName(),
      ]);
    }
    return $url;
  }

}
