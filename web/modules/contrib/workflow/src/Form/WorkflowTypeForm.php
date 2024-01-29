<?php

namespace Drupal\workflow\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the base form for workflow add and edit forms.
 */
class WorkflowTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\workflow\Entity\Workflow $workflow */
    $workflow = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#description' => $this->t(
        'The human-readable label of the workflow.This will be used as a label
         when the workflow status is shown during editing of content.'
      ),
      '#title' => $this->t('Label'),
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#description' => $this->t(
        'A unique machine-readable name. Can only contain lowercase letters,
         numbers, and underscores.'
      ),
      '#disabled' => !$this->entity->isNew(),
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => $this->t(
          'The machine-readable name must be unique, and can only contain
           lowercase letters, numbers, and underscores.
           Additionally, it can not be the reserved word "custom".'
        ),
      ],
    ];

    $form['permissions'] = [
      '#type' => 'details',
      '#title' => $this->t('Workflow permissions'),
      '#open' => TRUE, // Controls HTML5 'open' attribute. Defaults to FALSE.
      '#description' => $this->t(
        'To enable further Workflow functionality, go to the
         /admin/people/permissions page and select any roles that should
         have access to below and other functionality.'),
    ];
    $form['permissions']['transition_execute'] = [
      '#type' => 'item',
      '#title' => $this->t(
        'Participate in workflow (create, execute transitions)'
      ),
      '#markup' => $this->t(
        "You can determine which roles are enabled on the
         'Workflow Transitions & roles' configuration page. Use this to enable
         only the relevant roles. Some sites have too many roles to show on
         the configuration page."
      ),
    ];
    $form['permissions']['transition_schedule'] = [
      '#type' => 'item',
      '#title' => $this->t('Schedule state transition'),
      '#markup' => $this->t(
        'Workflow transitions may be scheduled to a moment
         in the future. Soon after the desired moment, the transition is
         executed by Cron. This may be hidden by settings in widgets,
         formatters or permissions.'
      ),
    ];
    $form['permissions']['history_tab'] = [
      '#type' => 'item',
      '#title' => $this->t('Access Workflow history tab'),
      '#markup' => $this->t(
        "You can determine if a tab 'Workflow history' is shown on the entity
         view page, which gives access to the History of the workflow.
         If you have multiple workflows per bundle, better disable this
         feature, and use, clone & adapt the Views display
         'Workflow history per Entity'."
      ),
    ];

    $form['basic'] = [
      '#type' => 'details',
      '#title' => $this->t('Workflow form settings'),
      '#open' => TRUE, // Controls HTML5 'open' attribute. Defaults to FALSE.
    ];
    $form['basic']['fieldset'] = [
      '#type' => 'select',
      '#options' => [
        0 => $this->t('No fieldset'),
        1 => $this->t('Collapsible fieldset'),
        2 => $this->t('Collapsed fieldset'),
      ],
      '#title' => $this->t('Show the form in a fieldset?'),
      '#default_value' => $workflow->getSetting('fieldset'),
    ];
    $form['basic']['options'] = [
      '#type' => 'select',
      '#title' => $this->t('How to show the available states'),
      '#required' => FALSE,
      '#default_value' => $workflow->getSetting('options'),
      // '#multiple' => TRUE / FALSE,
      '#options' => [
        // These options are taken from options.module.
        'select' => $this->t('Select list'),
        'radios' => $this->t('Radio buttons'),
        'buttons' => $this->t('Action buttons'),
        'dropbutton' => $this->t('Drop button'),
      ],
      '#description' => $this->t(
        'The Widget shows all available states.
         Decide which is the best way to show them.'
      ),
    ];
    $form['basic']['name_as_title'] = [
      '#type' => 'checkbox',
      '#attributes' => ['class' => ['container-inline']],
      '#title' => $this->t(
        'Use the workflow name as the title of the workflow form'
      ),
      '#default_value' => $workflow->getSetting('name_as_title'),
      '#description' => $this->t(
        'The workflow section of the editing form is in its own fieldset.
         Checking the box will add the workflow name as the title of workflow
         section of the editing form.'
      ),
    ];
    $form['basic']['schedule_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable scheduling in Workflow Transition form'),
      '#required' => FALSE,
      '#default_value' => $workflow->getSetting('schedule_enable'),
      '#description' => $this->t(
        'Scheduling may be enabled per Role on /admin/people/permissions page,
        but only if it is enable here.'
      ),
    ];
    $form['basic']['schedule_timezone'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a timezone when scheduling a transition'),
      '#required' => FALSE,
      '#default_value' => $workflow->getSetting('schedule_timezone'),
    ];
    // @todo D9: remove this, and set default to 1.
    $form['basic']['always_update_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always update the entity last updated timestamp'),
      '#description' => $this->t('Entity last updated timestamp is always
        updated as long as transition is allowed. This setting is useful to
        indicate that the entity is updated even when transition sid remains
        the same.'),
      '#required' => FALSE,
      '#default_value' => $workflow->getSetting('always_update_entity'),
    ];
    $form['basic']['comment_log_node'] = [
      '#type' => 'select',
      '#required' => FALSE,
      '#options' => [
        // Use 0/1/2 to stay compatible with previous checkbox.
        0 => $this->t('hidden'),
        1 => $this->t('optional'),
        2 => $this->t('required'),
      ],
      '#attributes' => ['class' => ['container-inline']],
      '#title' => $this->t('How to show the Comment sub-field'),
      '#default_value' => $workflow->getSetting('comment_log_node'),
      '#description' => $this->t(
        'A Comment area can be shown on the Workflow Transition form so that
         the person making a state change can record reasons for doing so.
         The comment is then included in the content\'s workflow history.'
      ),
    ];

    $form['watchdog'] = [
      '#type' => 'details',
      '#title' => $this->t('Watchdog'),
      '#description' => $this->t(
        'Informational watchdog messages can be logged when a transition is
         executed (state of a node is changed).'
      ),
      '#open' => TRUE, // Controls HTML5 'open' attribute. Defaults to FALSE.
    ];
    $form['watchdog']['watchdog_log'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log watchdog messages upon state change'),
      '#default_value' => $workflow->getSetting('watchdog_log'),
      '#description' => '',
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // $actions['submit']['#value'] = $this->t('Save');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\workflow\Entity\Workflow $entity */
    $entity = $this->entity;

    // Prevent leading and trailing spaces.
    $entity->set('label', trim($entity->label()));

    $entity->set('options', [
      'name_as_title' => $form_state->getValue('name_as_title'),
      'fieldset' => $form_state->getValue('fieldset'),
      'options' => $form_state->getValue('options'),
      'schedule_enable' => $form_state->getValue('schedule_enable'),
      'schedule_timezone' => $form_state->getValue('schedule_timezone'),
      'always_update_entity' => $form_state->getValue('always_update_entity'),
      'comment_log_node' => $form_state->getValue('comment_log_node'),
      'watchdog_log' => $form_state->getValue('watchdog_log'),
    ]);

    $status = parent::save($form, $form_state);
    $action = $status == SAVED_UPDATED ? 'updated' : 'added';

    // Tell the user we've updated the data.
    $args = [
      '%label' => $entity->label(),
      '%action' => $action,
    ];
    $this->messenger()->addStatus($this->t(
      'Workflow %label has been %action. Please maintain the permissions,
       states and transitions.', $args));
    $args += [
      'link' => $entity->toLink($this->t('Edit'))->toString(),
    ];
    $this->logger('workflow')->notice('Workflow %label has been %action.', $args);

    if ($status == SAVED_NEW) {
      $form_state->setRedirect('entity.workflow_type.edit_form', ['workflow_type' => $entity->id()]);
    }

  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $workflow = $this->entity;
    $name = $workflow->id();

    // Make sure workflow name is not numeric.
    // @todo D8: this was a prerequisite in D7. Remove in D8?
    if (ctype_digit($name)) {
      $form_state->setErrorByName('id', $this->t('Please choose a non-numeric name for your workflow.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Machine name exists callback.
   *
   * @param string $id
   *   The machine name ID.
   *
   * @return bool
   *   TRUE if an entity with the same name already exists, FALSE otherwise.
   */
  public function exists($id) {
    $type = $this->entity->getEntityTypeId();

    return (bool) $this->entityTypeManager->getStorage($type)->load($id);
  }

}
