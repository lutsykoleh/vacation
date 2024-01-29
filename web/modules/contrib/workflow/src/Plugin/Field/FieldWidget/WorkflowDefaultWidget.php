<?php

namespace Drupal\workflow\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Plugin implementation of the 'workflow_default' widget.
 *
 * @FieldWidget(
 *   id = "workflow_default",
 *   label = @Translation("Workflow transition form"),
 *   field_types = {
 *     "workflow"
 *   },
 * )
 */
class WorkflowDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   *
   * Be careful: Widget may be shown in very different places. Test carefully!!
   *  - On a entity add/edit page;
   *  - On a entity preview page;
   *  - On a entity view page;
   *  - On a entity 'workflow history' tab;
   *  - On a comment display, in the comment history;
   *  - On a comment form, below the comment history.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $wid = $this->getFieldSetting('workflow_type');
    /** @var \Drupal\workflow\Entity\Workflow $workflow */
    if (!$workflow = Workflow::load($wid)) {
      // @todo Add error message.
      return $element;
    }

    if ($this->isDefaultValueWidget($form_state)) {
      // On the Field settings page, User may not set a default value
      // (this is done by the Workflow module).
      return [];
    }

    /** @var \Drupal\workflow\Plugin\Field\FieldType\WorkflowItem $item */
    $item = $items[$delta];
    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $item->getFieldDefinition();
    /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
    $field_storage = $field_config->getFieldStorageDefinition();

    $entity = $item->getEntity();
    $field_name = $field_storage->getName();

    // Create a transition, to pass to the form. No need to use setValues().
    $from_sid = workflow_node_current_state($entity, $field_name);
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = WorkflowTransition::create([$from_sid, 'field_name' => $field_name]);
    $transition->setTargetEntity($entity);

    // Here, on entity form, not the $element is added, but the entity form.
    // Problem 1: adding the element, does not add added fields.
    // Problem 2: adding the form, generates wrong UI.
    // Problem 3: does not work on ScheduledTransition.
    //
    // Step 1: use the Element.
    $element['#default_value'] = $transition;
    $element += WorkflowTransitionElement::transitionElement($element, $form_state, $form);
    // Step 2: use the Form, in order to get extra fields.
    $form_state_additions = [
      'input' => $form_state->getUserInput(),
      'values' => $form_state->getValues(),
      'triggering_element' => $form_state->getTriggeringElement(),
    ];
    $workflow_form = WorkflowManager::getWorkflowTransitionForm($entity, $field_name, $form_state_additions);

    // Determine and add the attached fields.
    $attached_fields = WorkflowManager::getAttachedFields('workflow_transition', $wid);
    foreach ($attached_fields as $key => $attached_field) {
      $element[$key] = $workflow_form[$key];
    }

    // Option 3: use the true Element.
    // $form = $this->element($form, $form_state, $transition);
    //$element['workflow_transition'] = array(
    //      '#type' => 'workflow_transition',
    //      '#title' => $this->t('Workflow transition'),
    //      '#default_value' => $transition,
    // );

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Implements workflow_transition() -> WorkflowDefaultWidget::submit().
   *
   * This is called from function _workflow_form_submit($form, &$form_state)
   * It is a replacement of function workflow_transition($entity, $to_sid, $force, $field)
   * It performs the following actions;
   * - save a scheduled action
   * - update history
   * - restore the normal $items for the field.
   *
   * @todo Remove update of {node_form} table. (separate task, because it has features, too.)
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    $user = workflow_current_user(); // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.

    // Set the new value.
    // Beware: We presume cardinality = 1 !!
    // The widget form element type has transformed the value to a
    // WorkflowTransition object at this point. We need to convert it
    // back to the regular 'value' string format.
    foreach ($values as &$item) {
      if (!empty($item)) { // } && $item['value'] instanceof DrupalDateTime) {

        // The following can NOT be retrieved from the WorkflowTransition.
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $entity = $form_state->getFormObject()->getEntity();
        /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
        $transition = $item['workflow_transition'];
        // N.B. Use a proprietary version of copyFormValuesToEntity,
        // where $entity/$transition is passed by reference.
        $transition = WorkflowTransitionElement::copyFormValuesToTransition($transition, $form, $form_state, $item);

        // Try to execute the transition. Return $from_sid when error.
        if (!$transition) {
          // This should not be possible (perhaps when testing/developing).
          $this->messenger()->addError($this->t('Error: the transition from %from_sid to %to_sid could not be generated.'));
          // The current value is still the previous state.
          $to_sid = $from_sid = 0;
        }
        else {
          // The transition may be scheduled or not. Save the result, and
          // rely upon hook workflow_entity_insert/update($entity) in
          // file workflow.module to save/execute the transition.

          // - validate option; add hook to let other modules change comment.
          // - add to history; add to watchdog
          // Return the new State ID. (Execution may fail and return the old Sid.)

          // Get the new value from an action button if set in the workflow settings.
          $action_info = _workflow_transition_form_get_triggering_button($form_state);
          $field_name = $transition->getFieldName();
          if ($field_name == $action_info['field_name']) {
            $transition->set('to_sid', $action_info['to_sid']);
          }

          $force = FALSE; // @todo D8-port: add to form for usage in VBO.

          // Now, save/execute the transition.
          $from_sid = $transition->getFromSid();
          $force = $force || $transition->isForced();

          if (!$transition->isAllowed($user, $force)) {
            // Transition is not allowed.
            $to_sid = $from_sid;
          }
          elseif (!$entity || !$entity->id()) {
            // Entity is inserted. The Id is not yet known.
            // So we can't yet save the transition right now, but must rely on
            // function/hook workflow_entity_insert($entity) in file workflow.module.
            // $to_sid = $transition->execute($force);
            $to_sid = $transition->getToSid();
          }
          else {
            // Entity is updated. To stay in sync with insert, we rely on
            // function/hook workflow_entity_update($entity) in file workflow.module.
            // $to_sid = $transition->execute($force);
            $to_sid = $transition->getToSid();
          }
        }

        // Now the data is captured in the Transition, and before calling the
        // Execution, restore the default values for Workflow Field.
        // For instance, workflow_rules evaluates this.
        //
        // N.B. Align the following functions:
        // - WorkflowDefaultWidget::massageFormValues();
        // - WorkflowManager::executeTransition().
        // Set the transition back, to be used in hook_entity_update().
        $item['workflow_transition'] = $transition;
        // Set the value at the proper location.
        if ($transition && $transition->isScheduled()) {
          $item['value'] = $from_sid;
        }
        else {
          $item['value'] = $to_sid;
        }
      }
    }
    return $values;
  }

}
