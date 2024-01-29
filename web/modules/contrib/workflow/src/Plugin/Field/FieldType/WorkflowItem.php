<?php

namespace Drupal\workflow\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Url;
use Drupal\options\Plugin\Field\FieldType\ListItemBase;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;

/**
 * Plugin implementation of the 'workflow' field type.
 *
 * @FieldType(
 *   id = "workflow",
 *   label = @Translation("Workflow state"),
 *   description = @Translation("This field stores Workflow values for a certain Workflow type from a list of allowed 'value => label' pairs, i.e. 'Publishing': 1 => unpublished, 2 => draft, 3 => published."),
 *   category = @Translation("Workflow"),
 *   default_widget = "workflow_default",
 *   default_formatter = "list_default",
 *   constraints = {
 *     "WorkflowField" = {}
 *   },
 * )
 */
class WorkflowItem extends ListItemBase {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'value' => [
          'description' => 'The {workflow_states}.sid that this entity is currently in.',
          'type' => 'varchar',
          'length' => 128,
        ],
      ],
      'indexes' => [
        'value' => ['value'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    /**
     * Property definitions of the contained properties.
     *
     * @see FileItem::getPropertyDefinitions()
     *
     * @var array
     */
    static $propertyDefinitions;

    $definition['settings']['target_type'] = 'workflow_transition';
    // Definitions vary by entity type and bundle, so key them accordingly.
    $key = $definition['settings']['target_type'] . ':';
    $key .= isset($definition['settings']['target_bundle']) ? $definition['settings']['target_bundle'] : '';

    if (!isset($propertyDefinitions[$key])) {

      $propertyDefinitions[$key]['value'] = DataDefinition::create('string') // @todo D8-port: or 'any'
        ->setLabel(t('Workflow state'))
        ->addConstraint('Length', ['max' => 128])
        ->setRequired(TRUE);

      //workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8: test this snippet.
      /*
      $propertyDefinitions[$key]['workflow_transition'] = DataDefinition::create('any')
        //    $properties['workflow_transition'] = DataDefinition::create('WorkflowTransition')
        ->setLabel(t('Transition'))
        ->setDescription(t('The computed WorkflowItem object.'))
        ->setComputed(TRUE)
        ->setClass('\Drupal\workflow\Entity\WorkflowTransition')
        ->setSetting('date source', 'value');

      $propertyDefinitions[$key]['display'] = array(
        'type' => 'boolean',
        'label' => t('Flag to control whether this file should be displayed when viewing content.'),
      );
      $propertyDefinitions[$key]['description'] = array(
        'type' => 'string',
        'label' => t('A description of the file.'),
      );

      $propertyDefinitions[$key]['display'] = array(
        'type' => 'boolean',
        'label' => t('Flag to control whether this file should be displayed when viewing content.'),
      );
      $propertyDefinitions[$key]['description'] = array(
        'type' => 'string',
        'label' => t('A description of the file.'),
      );
       */
    }
    return $propertyDefinitions[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    $entity = parent::getEntity();

    // For Workflow on CommentForm, get the CommentedEntity.
    if ($entity->getEntityTypeId() == 'comment') {
      /** @var \Drupal\comment\CommentInterface $entity */
      $entity = $entity->getCommentedEntity();
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $is_empty = empty($this->value);
    return $is_empty;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    //workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8: test this snippet.

    // @todo D8: use this function onChange for adding a line in table workflow_transition_*
    // Enforce that the computed date is recalculated.
    //if ($property_name == 'value') {
    //  $this->date = NULL;
    //}
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {

    return [
      'workflow_type' => '',
    ] + parent::defaultStorageSettings();
  }

  /**
   * Implements hook_field_settings_form() -> ConfigFieldItemInterface::settingsForm().
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    // Create list of all Workflow types. Include an initial empty value.
    // Validate each workflow, and generate a message if not complete.
    $workflows = workflow_get_workflow_names(FALSE);

    // @todo D8: add this to WorkflowFieldConstraintValidator.
    // Set message, if no 'validated' workflows exist.
    if (count($workflows) == 1) {
      $this->messenger()->addWarning(
        $this->t('You must <a href=":create">create at least one workflow</a>
          before content can be assigned to a workflow.',
        [':create' => Url::fromRoute('entity.workflow_type.collection')->toString()]
      ));
    }

    // Validate via annotation WorkflowFieldConstraint. Show a message for each error.
    $violation_list = $this->validate();
    /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
    foreach ($violation_list->getIterator() as $violation) {
      switch ($violation->getPropertyPath()) {
        case 'fieldnameOnComment':
          // @todo D8: CommentForm & constraints on storageSettingsForm().
          // A 'comment' field name MUST be equal to content field name.
          // @todo Fix fields on a non-relevant entity_type.
          $this->messenger()->addError($violation->getMessage());
          $workflows = [];
          break;

        default:
          break;
      }
    }

    // @todo D8: CommentForm & constraints on storageSettingsForm.
    // Set the required workflow_type on 'comment' fields.
    // N.B. the following must BELOW the (count($workflows) == 1) snippet.
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage */
    $field_storage = $this->getFieldDefinition()->getFieldStorageDefinition();
    if (!$this->getSetting('workflow_type') && $field_storage->getTargetEntityTypeId() == 'comment') {
      $field_name = $field_storage->get('field_name');
      $workflows = [];
      foreach (_workflow_info_fields($entity = NULL, $entity_type = '', $entity_bundle = '', $field_name) as $key => $info) {
        if ($info->getName() == $field_name && ($info->getTargetEntityTypeId() !== 'comment')) {
          $wid = $info->getSetting('workflow_type');
          $workflow = Workflow::load($wid);
          $workflows[$wid] = $workflow->label();
        }
      }
    }

    // Let the user choose between the available workflow types.
    $wid = $this->getSetting('workflow_type');
    $url = Url::fromRoute('entity.workflow_type.collection')->toString();
    $element['workflow_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow type'),
      '#options' => $workflows,
      '#default_value' => $wid,
      '#required' => TRUE,
      '#disabled' => $has_data,
      '#description' => $this->t('Choose the Workflow type. Maintain workflows
         <a href=":url">here</a>.', [':url' => $url]),
    ];

    // Get a string representation to show all options.

    /*
     * Overwrite ListItemBase::storageSettingsForm().
     */
    if ($wid) {
      $allowed_values = WorkflowState::loadMultiple([], $wid);
      // $allowed_values_function = $this->getSetting('allowed_values_function');

      $element['allowed_values'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Allowed values for the selected Workflow type'),
        '#default_value' => ($wid) ? $this->allowedValuesString($allowed_values) : [],
        '#rows' => count($allowed_values),
        '#access' => ($wid) ? TRUE : FALSE, // User can see the data,
        '#disabled' => TRUE, // .. but cannot change them.
        '#element_validate' => [[get_class($this), 'validateAllowedValues']],

        '#field_has_data' => $has_data,
        '#field_name' => $this->getFieldDefinition()->getName(),
        '#entity_type' => ($this->getEntity()) ? $this->getEntity()->getEntityTypeId() : '',
        '#allowed_values' => $allowed_values,
        '#description' => $this->allowedValuesDescription(),
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function allowedValuesDescription() {
    return '';
  }

  /**
   * Generates a string representation of an array of 'allowed values'.
   *
   * This string format is suitable for edition in a textarea.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $states
   *   An array of WorkflowStates, where array keys are values and array values are
   *   labels.
   *
   * @return string
   *   The string representation of the $states array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "value|label" or "value".
   */
  protected function allowedValuesString($states) {
    $lines = [];

    $wid = $this->getSetting('workflow_type');

    $previous_wid = -1;
    /** @var \Drupal\workflow\Entity\WorkflowState $state */
    foreach ($states as $key => $state) {
      // Only show enabled states.
      if ($state->isActive()) {
        // Show a Workflow name between Workflows, if more then 1 in the list.
        if ((!$wid) && ($previous_wid <> $state->getWorkflowId())) {
          $previous_wid = $state->getWorkflowId();
          $lines[] = $state->getWorkflow()->label() . "'s states: ";
        }
        $label = $this->t('@label', ['@label' => $state->label()]);

        $lines[] = "   $key|$label";
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Implementation of TypedDataInterface.
   *
   * @see folder \workflow\src\Plugin\Validation\Constraint
   */

  /**
   * Implementation of OptionsProviderInterface.
   *
   *   An array of settable options for the object that may be used in an
   *   Options widget, usually when new data should be entered. It may either be
   *   a flat array of option labels keyed by values, or a two-dimensional array
   *   of option groups (array of flat option arrays, keyed by option group
   *   label). Note that labels should NOT be sanitized.
   */

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    // Flatten options first, because SettableOptions may have 'group' arrays.
    $flatten_options = OptGroup::flattenOptions($this->getPossibleOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL) {
    $allowed_options = [];

    // When we are initially on the Storage settings form, no wid is set, yet.
    if (!$wid = $this->getSetting('workflow_type')) {
      return $allowed_options;
    }

    // Create an empty State. This triggers to show all possible states for the Workflow.
    if ($workflow = Workflow::load($wid)) {
      // There is no entity, E.g., on the Rules action "Set a data value".
      $user = workflow_current_user($account); // @todo #2287057: OK?
      /** @var \Drupal\workflow\Entity\WorkflowState $state */
      $state = WorkflowState::create(['wid' => $wid, 'id' => '']);
      $allowed_options = $state->getOptions(NULL, '', $user, FALSE);
    }

    return $allowed_options;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    // Flatten options first, because SettableOptions may have 'group' arrays.
    $flatten_options = OptGroup::flattenOptions($this->getSettableOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    // Use the 'allowed_values_function' to calculate the options.
    $allowed_options = [];

    // When we are initially on the Storage settings form, no wid is set, yet.
    if (!$wid = $this->getSetting('workflow_type')) {
      return $allowed_options;
    }
    // On Field settings page, no entity is set.
    if (!$entity = $this->getEntity()) {
      return $allowed_options;
    }

    $definition = $this->getFieldDefinition()->getFieldStorageDefinition();
    $field_name = $definition->getName();
    $user = workflow_current_user($account); // @todo #2287057: OK?
    // Get the allowed new states for the entity's current state.
    /** @var \Drupal\workflow\Entity\WorkflowState $state */
    $sid = workflow_node_current_state($entity, $field_name);
    $state = WorkflowState::load($sid);
    $allowed_options = ($state) ? $state->getOptions($entity, $field_name, $user, FALSE) : [];

    return $allowed_options;
  }

}
