<?php

namespace Drupal\workflow\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;

/**
 * Provides a Transition Form to be used in the Workflow Widget.
 */
class WorkflowTransitionForm extends ContentEntityForm {

  /*************************************************************************
   * Implementation of interface FormInterface.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // We need a proprietary Form ID, to identify the unique forms
    // when multiple fields or entities are shown on 1 page.
    // Test this f.i. by checking the'scheduled' box. It will not unfold.
    // $form_id = parent::getFormId();

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->entity;
    $field_name = $transition->getFieldName();

    // Entity may be empty on VBO bulk form.
    // $entity = $transition->getTargetEntity();
    // Compose Form Id from string + Entity Id + Field name.
    // Field ID contains entity_type, bundle, field_name.
    // The Form Id is unique, to allow for multiple forms per page.
    // $workflow_type_id = $transition->getWorkflowId();
    // Field name contains implicit entity_type & bundle (since 1 field per entity)
    // $entity_type = $transition->getTargetEntityTypeId();
    // $entity_id = $transition->getTargetEntityId();;
    $suffix = 'form';
    // Emulate nodeForm convention.
    if ($transition->id()) {
      $suffix = 'edit_form';
    }
    $form_id = implode('_', ['workflow_transition', $transition->getTargetEntityTypeId(), $transition->getTargetEntityId(), $field_name, $suffix]);
    $form_id = Html::getUniqueId($form_id);

    return $form_id;
  }

  /* *************************************************************************
   *
   * Implementation of interface EntityFormInterface (extends FormInterface).
   *
   */

  /**
   * This function is called by buildForm().
   *
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Call parent to get (extra) fields.
    // This might cause baseFieldDefinitions to appear twice.
    $form = parent::form($form, $form_state);

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->entity;

    // Do not pass the element, but the form.
    // $element['#default_value'] = $transition;
    // $form += WorkflowTransitionElement::transitionElement($element, $form_state, $form);
    //
    // Pass the form via parameter.
    $form['#default_value'] = $transition;
    $form = WorkflowTransitionElement::transitionElement($form, $form_state, $form);
    return $form;
  }

  /**
   * Returns an array of supported actions for the current entity form.
   *
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // N.B. Keep code aligned: workflow_form_alter(), WorkflowTransitionForm::actions().
    $actions = parent::actions($form, $form_state);

    // A default button is provided by core. Override it.
    $actions['submit']['#value'] = $this->t('Update workflow');
    $actions['submit']['#attributes'] = ['class' => ['form-save-default-button']];

    if (!_workflow_use_action_buttons()) {
      // Change the default submit button on the Workflow History tab.
      return $actions;
    }

    // Find the first workflow.
    // (So this won't work with multiple workflows per entity.)
    // Quit if there is no Workflow on this page.
    if (!$workflow_form = &$form) {
      return $actions;
    }

    // Quit if there are no Workflow Action buttons.
    // (If user has only 1 workflow option, there are no Action buttons.)
    if (count($workflow_form['to_sid']['#options']) <= 1) {
      return $actions;
    }

    // Place the buttons. Remove the default 'Save' button.
    // $actions += _workflow_transition_form_get_action_buttons($form, $workflow_form);
    // Remove the default submit button from the form.
    // unset($actions['submit']);
    $default_submit_action = $actions['submit'];
    $actions = _workflow_transition_form_get_action_buttons($form, $workflow_form, $default_submit_action);
    foreach ($actions as &$action) {
      $action['#submit'] = $default_submit_action['#submit'];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = clone $this->entity;
    // Update the entity.
    $entity = $this->copyFormValuesToEntity($entity, $form, $form_state);
    // Mark the entity as NOT requiring validation. (Used in validateForm().)
    $entity->setValidationRequired(FALSE);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    // Use a proprietary version of copyFormValuesToEntity(),
    // passing $entity by reference...
    $values = $form_state->getValues();
    // ... but only the returning object is OK (!).
    return WorkflowTransitionElement::copyFormValuesToTransition($entity, $form, $form_state, $values);
  }

  /**
   * {@inheritdoc}
   *
   * This is called from submitForm().
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Execute transition and update the attached entity.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    return $transition->executeAndUpdateEntity();
  }

}
