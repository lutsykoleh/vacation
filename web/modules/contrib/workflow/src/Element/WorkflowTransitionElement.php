<?php

namespace Drupal\workflow\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowScheduledTransition;

/**
 * Provides a form element for the WorkflowTransitionForm and ~Widget.
 *
 * Properties:
 * - #return_value: The value to return when the checkbox is checked.
 *
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://www.drupal.org/node/169815 "Creating Custom Elements"
 *
 * @FormElement("workflow_transition")
 */
class WorkflowTransitionElement extends FormElement {

  /**
   * Generate an element.
   *
   * This function is referenced in the Annotation for this class.
   *
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $complete_form
   *
   * @return array
   *   The Workflow element
   */
  public static function processTransition(array &$element, FormStateInterface $form_state, array &$complete_form) {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__); // @todo D8:  test this snippet.
    return self::transitionElement($element, $form_state, $complete_form);
  }

  /**
   * Generate an element.
   *
   * This function is an internal function, to be reused in:
   * - TransitionElement,
   * - TransitionDefaultWidget.
   *
   * @param array $element
   *   Reference to the form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $complete_form
   *
   * @return array
   *   The form element $element.
   *
   * @usage:
   *   @example $element['#default_value'] = $transition;
   *   @example $element += WorkflowTransitionElement::transitionElement($element, $form_state, $form);
   */
  public static function transitionElement(array &$element, FormStateInterface $form_state, array &$complete_form) {

    /*
     * Input.
     */
    // A Transition object must have been set explicitly.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $element['#default_value'];
    /** @var \Drupal\Core\Session\AccountInterface $user */
    $user = \Drupal::currentUser();

    /*
     * Derived input.
     */
    $field_name = $transition->getFieldName();
    $workflow = $transition->getWorkflow();
    $wid = $transition->getWorkflowId();
    $force = $transition->isForced();
    $entity = $transition->getTargetEntity();
    $entity_type = $transition->getTargetEntityTypeId();
    $entity_id = $transition->getTargetEntityId();

    if ($transition->isExecuted()) {
      // We are editing an existing/executed/not-scheduled transition.
      // Only the comments may be changed!
      $current_sid = $from_sid = $transition->getFromSid();
      // The states may not be changed anymore.
      $to_state = $transition->getToState();
      $options = [$to_state->id() => $to_state->label()];
      // We need the widget to edit the comment.
      $show_widget = TRUE;
      $default_value = $transition->getToSid();
    }
    elseif ($entity) {
      // Normal situation: adding a new transition on an new/existing entity.
      //
      // Get the scheduling info, only when updating an existing entity.
      // This may change the $default_value on the Form.
      // Technically you could have more than one scheduled transition, but
      // this will only add the soonest one.
      // @todo Read the history with an explicit langcode?
      $langcode = ''; // $entity->language()->getId();
      if ($entity_id && $scheduled_transition = WorkflowScheduledTransition::loadByProperties($entity_type, $entity_id, [], $field_name, $langcode)) {
        $transition = $scheduled_transition;
      }

      $current_sid = $from_sid = $transition->getFromSid();
      $current_state = $from_state = $transition->getFromState();
      $options = ($current_state) ? $current_state->getOptions($entity, $field_name, $user, FALSE) : [];
      $show_widget = ($from_state) ? $from_state->showWidget($entity, $field_name, $user, FALSE) : [];
      $default_value = $from_sid;
      $default_value = ($from_state && $from_state->isCreationState()) ? $workflow->getFirstSid($entity, $field_name, $user, FALSE) : $default_value;
      $default_value = ($transition->isScheduled()) ? $transition->getToSid() : $default_value;
    }
    elseif (!$entity) {
      // Sometimes, no entity is given. We encountered the following cases:
      // - D7: the Field settings page,
      // - D7: the VBO action form;
      // - D7/D8: the Advance Action form on admin/config/system/actions;
      // If so, show all options for the given workflow(s).
      if (!$temp_state = $transition->getFromState()) {
        $temp_state = $transition->getToState();
      }
      $options = ($temp_state)
        ? $temp_state->getOptions($entity, $field_name, $user, FALSE)
        : workflow_get_workflow_state_names($wid, $grouped = TRUE);
      $show_widget = TRUE;
      $current_sid = $transition->getToSid();
      $default_value = $from_sid = $transition->getToSid();
    }
    else {
      // We are in trouble! A message is already set in workflow_node_current_state().
      $options = [];
      $current_sid = 0;
      $show_widget = FALSE;
      $default_value = FALSE;
    }

    /*
     * Output: generate the element.
     */
    // Get settings from workflow.
    if (!$workflow) {
      // Might be empty on Action configuration.
      $workflow_settings = Workflow::defaultSettings();
      $wid = '';
    }
    else {
      $workflow_settings = $workflow->getSettings();
      $wid = $workflow->id(); // Might be empty on Action/VBO configuration.
    }
    $settings_options_type = $workflow_settings['options'];

    // Display scheduling form if user has permission.
    // Not shown on new entity (not supported by workflow module, because that
    // leaves the entity in the (creation) state until scheduling time.)
    // Not shown when editing existing transition.
    $settings_schedule =
      !$transition->isExecuted()
      && $user->hasPermission("schedule $wid workflow_transition")
      && ($workflow_settings['schedule_enable']);
    if ($settings_schedule) {
      // @todo D8: check below code: form on VBO.
      // workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8: test this snippet.
      $step = $form_state->getValue('step');
      if (isset($step) && ($form_state->getValue('step') == 'views_bulk_operations_config_form')) {
        // On VBO 'modify entity values' form, leave field settings.
        $settings_schedule = TRUE;
      }
      else {
        // ... and cannot be shown on a Content add page (no $entity_id),
        // ...but can be shown on a VBO 'set workflow state to..'page (no entity).
        $settings_schedule = !($entity && !$entity_id);
      }
    }

    // Save the current value of the entity in the form, for later Workflow-module specific references.
    // We add prefix, since #tree == FALSE.
    $element['workflow_transition'] = [
      '#type' => 'value',
      '#value' => $transition,
    ];

    // Decide if we show either a widget or a formatter.
    // Add a state formatter before the rest of the form,
    // when transition is scheduled or widget is hidden.
    // Also no widget if the only option is the current sid.
    if ((!$show_widget) || $transition->isScheduled() || $transition->isExecuted()) {
      if ($entity) { // may be empty in VBO.
        $element['workflow_current_state'] = workflow_state_formatter($entity, $field_name, $current_sid);
        // Set a proper weight, which works for Workflow Options in select list AND action buttons.
        $element['workflow_current_state']['#weight'] = -0.005;
      }
    }

    $element['#tree'] = TRUE;
    // Add class following node-form pattern (both on form and container).
    $workflow_type_id = ($workflow) ? $workflow->id() : '';
    $element['#attributes']['class'][] = 'workflow-transition-' . $workflow_type_id . '-container';
    $element['#attributes']['class'][] = 'workflow-transition-container';
    if (!$show_widget) {
      // Show no widget.
      $element['to_sid']['#type'] = 'value';
      $element['to_sid']['#value'] = $default_value;
      $element['to_sid']['#options'] = $options; // In case action buttons need them.
      $element['comment']['#type'] = 'value';
      $element['comment']['#value'] = '';

      return $element; // <-- exit.
    }

    // Prepare a UI wrapper. This might be a fieldset.
    $element['#type'] = 'container';
    if ($workflow_settings['fieldset']) {
      unset($element['#type']);
      $element += [
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#open' => $workflow_settings['fieldset'] != 2,
      ];
    }

    $element['field_name'] = [
      '#type' => 'select',
      '#title' => t('Field name'),
      '#description' => t('Choose the field name.'),
      '#access' => FALSE, // Only show on VBO/Actions screen.
      '#options' => workflow_get_workflow_field_names($entity),
      '#default_value' => $field_name,
      '#required' => TRUE,
      '#weight' => -20,
    ];

    // Add the 'options' widget.
    // It may be replaced later if 'Action buttons' are chosen.
    // The help text is not available for container. Let's add it to the
    // State box. N.B. it is empty on Workflow Tab, Node View page.
    $help_text = isset($element['#description']) ? $element['#description'] : '';
    unset($element['#description']);
    // This overrides BaseFieldDefinition. @todo Apply for form and widget.
    // @todo Repair $workflow->'name_as_title': no container if no details (schedule/comment).
    $element['to_sid'] = [
      '#type' => ($wid) ? $settings_options_type : 'select', // Avoid error with grouped options.
      '#title' => (!$workflow_settings['name_as_title'] && !$transition->isExecuted())
      ? t('Change @name state', ['@name' => $workflow->label()])
      : t('Change state'),
      '#access' => TRUE,
      '#options' => $options,
      '#default_value' => $default_value,
      '#description' => $help_text,
    ];
    $element['force'] = [
      '#type' => 'checkbox',
      '#title' => t('Force transition'),
      '#description' => t('If this box is checked, the new state will be
        assigned even if workflow permissions disallow it.'),
      '#access' => FALSE, // Only show on VBO/Actions screen.
      '#default_value' => $force,
      '#weight' => -19,
    ];

    // Display scheduling form under certain conditions.
    if ($settings_schedule) {
      $timezone = $user->getTimeZone();
      if (empty($timezone)) {
        $timezone = \Drupal::config('system.date')->get('timezone.default');
      }

      $timezone_options = array_combine(timezone_identifiers_list(), timezone_identifiers_list());
      $timestamp = $transition->getTimestamp();
      $hours = $transition->isScheduled() ? \Drupal::service('date.formatter')->format($timestamp, 'custom', 'H:i', $timezone) : '00:00';
      // Add a container, so checkbox and time stay together in extra fields.
      $element['workflow_scheduling'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
      // Define class for '#states' behaviour.
      // Fetch the form ID. This is unique for each entity, to allow multiple form per page (Views, etc.).
      // Make it uniquer by adding the field name, or else the scheduling of
      // multiple workflow_fields is not independent of each other.
      // If we are indeed on a Transition form (so, not a Node Form with widget)
      // then change the form id, too.
      $form_id = $form_state->getBuildInfo()['form_id'];
      // @todo Align with WorkflowTransitionForm->getFormId().
      $class_identifier = Html::getClass('scheduled_' . Html::getUniqueId($form_id) . '-' . $field_name);
      $element['workflow_scheduling']['scheduled'] = [
        '#type' => 'radios',
        '#title' => t('Schedule'),
        '#options' => [
          '0' => t('Immediately'),
          '1' => t('Schedule for state change'),
        ],
        '#default_value' => (string) $transition->isScheduled(),
        '#attributes' => [
          // 'id' => 'scheduled_' . $form_id,
          'class' => [$class_identifier],
        ],
      ];
      $element['workflow_scheduling']['date_time'] = [
        '#type' => 'details', // 'container',
        '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
        '#attributes' => ['class' => ['container-inline']],
        '#prefix' => '<div style="margin-left: 1em;">',
        '#suffix' => '</div>',
        '#states' => [
          'visible' => ['input.' . $class_identifier => ['value' => '1']],
        ],
      ];
      $element['workflow_scheduling']['date_time']['workflow_scheduled_date'] = [
        '#type' => 'date',
        '#prefix' => t('At'),
        '#default_value' => implode('-', [
          'year' => date('Y', $timestamp),
          'month' => date('m', $timestamp),
          'day' => date('d', $timestamp),
        ]),
      ];
      $element['workflow_scheduling']['date_time']['workflow_scheduled_hour'] = [
        '#type' => 'textfield',
        '#title' => t('Time'),
        '#maxlength' => 7,
        '#size' => 6,
        '#default_value' => $hours,
        '#element_validate' => ['_workflow_transition_form_element_validate_time'], // @todo D8: this is not called.
      ];
      $element['workflow_scheduling']['date_time']['workflow_scheduled_timezone'] = [
        '#type' => $workflow_settings['schedule_timezone'] ? 'select' : 'hidden',
        '#title' => t('Time zone'),
        '#options' => $timezone_options,
        '#default_value' => [$timezone => $timezone],
      ];
      $element['workflow_scheduling']['date_time']['workflow_scheduled_help'] = [
        '#type' => 'item',
        '#prefix' => '<br />',
        '#description' => t('Please enter a time. If no time is included,
          the default will be midnight on the specified date.
          The current time is: @time.', [
            '@time' => \Drupal::service('date.formatter')
              ->format(\Drupal::time()->getRequestTime(), 'custom', 'H:i', $timezone),
          ]
        ),
      ];
    }

    // Show comment, when both Field and Instance allow this.
    // This overrides BaseFieldDefinition. @todo Apply for form and widget.
    $element['comment'] = [
      '#type' => 'textarea',
      '#required' => $workflow_settings['comment_log_node'] == '2',
      '#access' => $workflow_settings['comment_log_node'] != '0', // Align with action buttons.
      '#title' => t('Comment'),
      '#description' => t('Briefly describe the changes you have made.'),
      '#default_value' => $transition->getComment(),
      '#rows' => 2,
    ];

    if ($settings_options_type == 'buttons' || $settings_options_type == 'dropbutton') {
      // In WorkflowTransitionForm, a default 'Submit' button is added there.
      // In Entity Form, workflow_form_alter() adds button per permitted state.
      //
      // D7: How do action buttons work? See also d.o. issue #2187151.
      // D7: Create 'action buttons' per state option. Set $sid property on each button.
      // 1. Admin sets ['widget']['options']['#type'] = 'buttons'.
      // 2. This function formElement() creates 'action buttons' per option;
      //    sets $sid property on each button.
      // 3. User clicks button.
      // 4. Callback _workflow_transition_form_validate_buttons() sets proper State.
      // 5. Callback _workflow_transition_form_validate_buttons() sets Submit function.
      //
      // Performance: inform workflow_form_alter() to do its job.
      _workflow_use_action_buttons($settings_options_type);

      // Make sure the '#type' is not set to the invalid 'buttons' value.
      // It will be replaced by action buttons, but sometimes, the select box
      // is still shown.
      // @see workflow_form_alter().
      $element['to_sid']['#type'] = 'select';
      $element['to_sid']['#access'] = FALSE;
    }
    return $element;
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The form ID.
   */
  protected static function getFormId() {
    return 'workflow_transition_form'; // @todo D8-port: add $form_id for widget and History tab.
  }

  /**
   * Implements ContentEntityForm::copyFormValuesToEntity().
   *
   * This is called from:
   * - WorkflowTransitionForm::copyFormValuesToEntity(),
   * - WorkflowDefaultWidget.
   *
   * N.B. in contrary to ContentEntityForm::copyFormValuesToEntity(),
   * - parameter 1 is returned as result, to be able to create a new Transition object.
   * - parameter 3 is not $form_state (from Form), but an $item array (from Widget).
   *
   * @param \Drupal\Core\Entity\EntityInterface $transition
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $item
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   A new Transition object.
   */
  public static function copyFormValuesToTransition(EntityInterface $transition, array $form, FormStateInterface $form_state, array $item) {
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    // @todo #2287057: verify if submit() really is only used for UI. If not, $user must be passed.
    /** @var \Drupal\user\UserInterface $user */
    $user = workflow_current_user();

    /*
     * Derived input
     */
    // Make sure we have subset ['workflow_scheduled_date_time'].
    if (!isset($item['to_sid'])) {
      $entity_id = $transition->getTargetEntityId();
      \Drupal::messenger()->addError(t('Error: content @id has no workflow attached. The data is not saved.', ['@id' => $entity_id]));
      // The new state is still the previous state.
      return $transition;
    }

    // In WorkflowTransitionForm, we receive the complete $form_state.
    // Remember, the workflow_scheduled element is not set on 'add' page.
    $scheduled = !empty($item['workflow_scheduling']['scheduled']);
    $schedule_values = ($scheduled) ? $item['workflow_scheduling']['date_time'] : [];

    // Get user input from element.
    $to_sid = $item['to_sid'];
    $comment = $item['comment'];
    $force = FALSE;

    // @todo D8: add the VBO use case.
    /*
    // Determine if the transition is forced.
    // This can be set by a 'workflow_vbo action' in an additional form element.
    $force = isset($form_state['input']['workflow_force'])
    ? $form_state['input']['workflow_force'] : FALSE;
    if (!$entity) {
      // E.g., on VBO form.
    }
     */

    // @todo D8: add below exception.
    // Extract the data from $items, depending on the type of widget.
    // @todo D8: use MassageFormValues($item, $form, $form_state).
    /*
    $old_sid = workflow_node_previous_state($entity, $entity_type, $field_name);
    if (!$old_sid) {
      // At this moment, $old_sid should have a value. If the content does not
      // have a state yet, old_sid contains '(creation)' state. But if the
      // content is not associated to a workflow, old_sid is now 0. This may
      // happen in workflow_vbo, if you assign a state to non-relevant nodes.
      $entity_id = entity_id($entity_type, $entity);
      \Drupal::messenger()->addError(t('Error: content @id has no workflow
        attached. The data is not saved.', ['@id' => $entity_id]));
      // The new state is still the previous state.
      $new_sid = $old_sid;
      return $new_sid;
    }
     */

    $timestamp = \Drupal::time()->getRequestTime();
    if ($scheduled) {
      // Fetch the (scheduled) timestamp to change the state.
      // Override $timestamp.
      $scheduled_date_time = implode(' ', [
        $schedule_values['workflow_scheduled_date'],
        $schedule_values['workflow_scheduled_hour'],
        // $schedule_values['workflow_scheduled_timezone'],
      ]);
      $timezone = $schedule_values['workflow_scheduled_timezone'];
      $old_timezone = date_default_timezone_get();
      date_default_timezone_set($timezone);
      $timestamp = strtotime($scheduled_date_time);
      date_default_timezone_set($old_timezone);
      if (!$timestamp) {
        // Time should have been validated in form/widget.
        $timestamp = \Drupal::time()->getRequestTime();
      }
    }

    /*
     * Process.
     */

    /*
     * Create a new ScheduledTransition.
     */
    if ($scheduled) {
      $transition_entity = $transition->getTargetEntity();
      $field_name = $transition->getFieldName();
      $from_sid = $transition->getFromSid();
      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
      $transition = WorkflowScheduledTransition::create([$from_sid, 'field_name' => $field_name]);
      $transition->setTargetEntity($transition_entity);
      $transition->setValues($to_sid, $user->id(), $timestamp, $comment);
    }
    if (!$transition->isExecuted()) {
      // Set new values.
      // When editing an existing Transition, only comments may change.
      $transition->setValues($to_sid, $user->id(), $timestamp, $comment);
      $transition->schedule($scheduled);
      $transition->force($force);
    }
    $transition->setComment($comment);

    // Determine and add the attached fields.
    // Caveat: This works automatically on a Workflow Form,
    // but only with a hack on a widget.
    // @todo Attached fields are not supported in ScheduledTransitions.
    $fields = WorkflowManager::getAttachedFields('workflow_transition', $transition->bundle());
    /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $field */
    foreach ($fields as $field_name => $field) {
      $user_input = isset($form_state->getUserInput()[$field_name]) ? $form_state->getUserInput()[$field_name] : [];
      if (isset($item[$field_name])) {
        // On Workflow Form (e.g., history tab, block).
        // @todo In latest tests, this line seems not necessary.
        $transition->{$field_name} = $item[$field_name];
      }
      elseif ($user_input) {
        // On Workflow Widget (e.g., on node, comment).
        // @todo Some field types are not supported here.
        $transition->{$field_name} = $user_input;
      }
      // #2899025 As a workaround for field types not supported,
      // let other modules modify the copied values.
      $context = [
        'field' => $field,
        'field_name' => $field_name,
        'user_input' => $user_input,
        'item' => $item,
      ];
      \Drupal::moduleHandler()->alter('copy_form_values_to_transition_field', $transition, $context);
    }

    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#return_value' => 1,
      '#process' => [
        [$class, 'processTransition'],
        [$class, 'processAjaxForm'],
        // array($class, 'processGroup'),
      ],
      '#element_validate' => [
        [$class, 'validateTransition'],
      ],
      '#pre_render' => [
        [$class, 'preRenderTransition'],
        // array($class, 'preRenderGroup'),
      ],
      // '#theme' => 'input__checkbox',
      // '#theme' => 'input__textfield',
      '#theme_wrappers' => ['form_element'],
      // '#title_display' => 'after',
    ];
  }

}
