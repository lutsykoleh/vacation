<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\Language;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\workflow\WorkflowTypeAttributeTrait;

/**
 * Implements an actual, executed, Transition.
 *
 * If a transition is executed, the new state is saved in the Field.
 * If a transition is saved, it is saved in table {workflow_transition_history}.
 *
 * @ContentEntityType(
 *   id = "workflow_transition",
 *   label = @Translation("Workflow transition"),
 *   label_singular = @Translation("Workflow transition"),
 *   label_plural = @Translation("Workflow transitions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Workflow transition",
 *     plural = "@count Workflow transitions",
 *   ),
 *   bundle_label = @Translation("Workflow type"),
 *   module = "workflow",
 *   translatable = FALSE,
 *   handlers = {
 *     "access" = "Drupal\workflow\WorkflowAccessControlHandler",
 *     "list_builder" = "Drupal\workflow\WorkflowTransitionListBuilder",
 *     "form" = {
 *        "add" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *        "edit" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "revert" = "Drupal\workflow\Form\WorkflowTransitionRevertForm",
 *      },
 *     "views_data" = "Drupal\workflow\WorkflowTransitionViewsData",
 *   },
 *   base_table = "workflow_transition_history",
 *   entity_keys = {
 *     "id" = "hid",
 *     "bundle" = "wid",
 *     "langcode" = "langcode",
 *   },
 *   permission_granularity = "bundle",
 *   bundle_entity_type = "workflow_type",
 *   field_ui_base_route = "entity.workflow_type.edit_form",
 *   links = {
 *     "canonical" = "/workflow_transition/{workflow_transition}",
 *     "delete-form" = "/workflow_transition/{workflow_transition}/delete",
 *     "edit-form" = "/workflow_transition/{workflow_transition}/edit",
 *     "revert-form" = "/workflow_transition/{workflow_transition}/revert",
 *   },
 * )
 */
class WorkflowTransition extends ContentEntityBase implements WorkflowTransitionInterface {

  /*
   * Adds the messenger trait.
   */
  use MessengerTrait;
  /*
   * Adds the translation trait.
   */
  use StringTranslationTrait;
  /*
   * Adds variables and get/set methods for Workflow property.
   */
  use WorkflowTypeAttributeTrait;

  /*
   * Transition data: are provided via baseFieldDefinitions().
   */

  /*
   * Cache data.
   */

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   *
   * @usage Use WorkflowTransition->getTargetEntity() to fetch this.
   */
  protected $entity = NULL;
  /**
   * @var \Drupal\user\UserInterface
   *
   * @usage Use WorkflowTransition->getOwner() to fetch this.
   */
  protected $user = NULL;
  /**
   * Extra data: describe the state of the transition.
   *
   * @var bool
   */
  protected $isScheduled = FALSE;
  /**
   * Extra data: describe the state of the transition.
   *
   * @var bool
   */
  protected $isExecuted = FALSE;
  /**
   * Extra data: describe the state of the transition.
   *
   * @var bool
   */
  protected $isForced = FALSE;

  /**
   * Entity class functions.
   */

  /**
   * Creates a new entity.
   *
   * @param array $values
   * @param string $entityType
   *   The entity type of this Entity subclass.
   * @param bool $bundle
   * @param array $translations
   *
   * @see entity_create()
   *
   * No arguments passed, when loading from DB.
   * All arguments must be passed, when creating an object programmatically.
   * One argument $entity may be passed, only to directly call delete() afterwards.
   */
  public function __construct(array $values = [], $entityType = 'workflow_transition', $bundle = FALSE, array $translations = []) {
    // Please be aware that $entity_type and $entityType are different things!
    parent::__construct($values, $entityType, $bundle, $translations);
    // This transition is not scheduled.
    $this->isScheduled = FALSE;
    // This transition is not executed, if it has no hid, yet, upon load.
    $this->isExecuted = ($this->id() > 0);
  }

  /**
   * {@inheritdoc}
   *
   * @param array $values
   *   $values[0] may contain a Workflow object or State object or State ID.
   *
   * @return static
   *   The entity object.
   */
  public static function create(array $values = []) {
    if (is_array($values) && isset($values[0])) {
      $value = $values[0];
      $state = NULL;
      if (is_string($value)) {
        $state = WorkflowState::load($value);
      }
      elseif (is_object($value) && $value instanceof WorkflowState) {
        $state = $value;
      }
      $values['wid'] = $state ? $state->getWorkflowId() : '';
      $values['from_sid'] = $state ? $state->id() : '';
    }

    // Add default values.
    $values += [
      'timestamp' => \Drupal::time()->getRequestTime(),
      'uid' => \Drupal::currentUser()->id(),
    ];
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function setValues($to_sid, $uid = NULL, $timestamp = NULL, $comment = '', $force_create = FALSE) {
    // Normally, the values are passed in an array, and set in parent::__construct, but we do it ourselves.
    $uid = ($uid === NULL) ? workflow_current_user()->id() : $uid;
    $from_sid = $this->getFromSid();

    $this->set('to_sid', $to_sid);
    $this->setOwnerId($uid);
    $this->setTimestamp($timestamp == NULL ? \Drupal::time()->getRequestTime() : $timestamp);
    $this->setComment($comment);

    // If constructor is called with new() and arguments.
    if (!$from_sid && !$to_sid && !$this->getTargetEntity()) {
      // If constructor is called without arguments, e.g., loading from db.
    }
    elseif ($from_sid && $this->getTargetEntity()) {
      // Caveat: upon entity_delete, $to_sid is '0'.
      // If constructor is called with new() and arguments.
    }
    elseif (!$from_sid) {
      // Not all parameters are passed programmatically.
      if (!$force_create) {
        $this->messenger()->addError(
          $this->t('Wrong call to constructor Workflow*Transition(%from_sid to %to_sid)',
            ['%from_sid' => $from_sid, '%to_sid' => $to_sid]));
      }
    }

    return $this;
  }

  /**
   * CRUD functions.
   */

  /**
   * Saves the entity.
   *
   * Mostly, you'd better use WorkflowTransitionInterface::execute().
   *
   * {@inheritdoc}
   */
  public function save() {
    // Set Target Entity, to be used by Rules.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $reference */
    $reference = $this->get('entity_id')->first();
    if ($reference) {
      $reference->set('entity', $this->getTargetEntity());
    }

    // Avoid custom actions for subclass WorkflowScheduledTransition.
    if ($this->isScheduled()) {
      return parent::save();
    }
    if ($this->getEntityTypeId() != 'workflow_transition') {
      return parent::save();
    }

    $transition = $this;
    $entity_type = $transition->getTargetEntityTypeId();
    $entity_id = $transition->getTargetEntityId();
    $field_name = $transition->getFieldName();

    // Remove any scheduled state transitions.
    foreach (WorkflowScheduledTransition::loadMultipleByProperties($entity_type, [$entity_id], [], $field_name) as $scheduled_transition) {
      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $scheduled_transition */
      $scheduled_transition->delete();
    }

    // Check for no transition.
    if ($this->isEmpty()) {
      return SAVED_UPDATED;
    }
    // Update the transition, if already exists.
    if ($this->id()) {
      return parent::save();
    }
    // Insert the transition. Make sure it hasn't already been inserted.
    // @todo Allow a scheduled transition per revision.
    // @todo Allow a state per language version (langcode).
    $found_transition = self::loadByProperties($entity_type, $entity_id, [], $field_name);
    if ($found_transition &&
      $found_transition->getTimestamp() == \Drupal::time()->getRequestTime() &&
      $found_transition->getToSid() == $this->getToSid()) {
      return SAVED_UPDATED;
    }
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByProperties($entity_type, $entity_id, array $revision_ids = [], $field_name = '', $langcode = '', $sort = 'ASC', $transition_type = 'workflow_transition') {
    $limit = 1;
    $transitions = self::loadMultipleByProperties($entity_type, [$entity_id], $revision_ids, $field_name, $langcode, $limit, $sort, $transition_type);
    if ($transitions) {
      $transition = reset($transitions);
      return $transition;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultipleByProperties($entity_type, array $entity_ids, array $revision_ids = [], $field_name = '', $langcode = '', $limit = NULL, $sort = 'ASC', $transition_type = 'workflow_transition') {

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery($transition_type)
      ->condition('entity_type', $entity_type)
      ->accessCheck(FALSE)
      ->sort('timestamp', $sort)
      ->addTag($transition_type);
    if (!empty($entity_ids)) {
      $query->condition('entity_id', $entity_ids, 'IN');
    }
    if (!empty($revision_ids)) {
      $query->condition('revision_id', $entity_ids, 'IN');
    }
    if ($field_name != '') {
      $query->condition('field_name', $field_name, '=');
    }
    if ($langcode != '') {
      $query->condition('langcode', $langcode, '=');
    }
    if ($limit) {
      $query->range(0, $limit);
    }
    if ($transition_type == 'workflow_transition') {
      $query->sort('hid', 'DESC');
    }
    $ids = $query->execute();
    $transitions = self::loadMultiple($ids);
    return $transitions;
  }

  /**
   * Implementing interface WorkflowTransitionInterface - properties.
   */

  /**
   * Determines if the Transition is valid and can be executed.
   *
   * @todo Add to isAllowed() ?
   * @todo Add checks to WorkflowTransitionElement ?
   *
   * @return bool
   */
  public function isValid() {
    // Load the entity, if not already loaded.
    // This also sets the (empty) $revision_id in Scheduled Transitions.
    $entity = $this->getTargetEntity();

    if (!$entity) {
      // @todo There is a watchdog error, but no UI-error. Is this OK?
      $message = 'User tried to execute a Transition without an entity.';
      $this->logError($message);
      return FALSE;  // <-- exit !!!
    }
    if (!$this->getFromState()) {
      // @todo The page is not correctly refreshed after this error.
      $message = $this->t('You tried to set a Workflow State, but
        the entity is not relevant. Please contact your system administrator.');
      $this->messenger()->addError($message);
      $message = 'Setting a non-relevant Entity from state %sid1 to %sid2';
      $this->logError($message);
      return FALSE;  // <-- exit !!!
    }

    // The transition is OK.
    return TRUE;
  }

  /**
   * Check if anything has changed in this transition.
   *
   * @return bool
   */
  protected function isEmpty() {
    if ($this->getToSid() != $this->getFromSid()) {
      return FALSE;
    }
    if ($this->getComment()) {
      return FALSE;
    }
    $fields = WorkflowManager::getAttachedFields('workflow_transition', $this->bundle());
    /** @var \Drupal\Core\Field\Entity\BaseFieldOverride $field */
    foreach ($fields as $field_name => $field) {
      if (!$this->{$field_name}->isEmpty()) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(UserInterface $user, $force = FALSE) {
    if ($force) {
      // $force allows Rules to cause transition.
      return TRUE;
    }

    // @todo Keep below code aligned between WorkflowState, ~Transition, ~HistoryAccess
    /*
       * Get  user's permissions.
       */
    $type_id = $this->getWorkflowId();
    if ($user->hasPermission("bypass $type_id workflow_transition access")) {
      // Superuser is special (might be cron).
      // And $force allows Rules to cause transition.
      return TRUE;
    }
    // Determine if user is owner of the entity.
    $is_owner = WorkflowManager::isOwner($user, $this->getTargetEntity());
    if ($is_owner) {
      $user->addRole(WORKFLOW_ROLE_AUTHOR_RID);
    }

    /*
     * Get the object and its permissions.
     */
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface[] $config_transitions */
    $config_transitions = $this->getWorkflow()->getTransitionsByStateId($this->getFromSid(), $this->getToSid());

    /*
     * Determine if user has Access.
     */
    $result = FALSE;
    foreach ($config_transitions as $config_transition) {
      $result = $result || $config_transition->isAllowed($user, $force);
    }

    if ($result == FALSE) {
      // @todo There is a watchdog error, but no UI-error. Is this OK?
      $message = $this->t('Attempt to go to nonexistent transition (from %sid1 to %sid2)');
      $this->logError($message);
    }

    return $result;
  }

  /**
   * Determines if the State changes by this Transition.
   *
   * @return bool
   */
  public function hasStateChange() {
    if ($this->from_sid->target_id == $this->to_sid->target_id) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($force = FALSE) {
    // Load the entity, if not already loaded.
    // This also sets the (empty) $revision_id in Scheduled Transitions.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getTargetEntity();
    // Load explicit User object (not via $transition) for adding Role later.
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getOwner();
    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();
    $field_name = $this->getFieldName();
    $comment = $this->getComment();
    // Create a label to identify this transition,
    // even upon insert, when id() is not set, yet.
    $label = $from_sid . '-' . $to_sid;

    static $static_info = NULL;

    $entity_id = $entity->id();
    // For non-default revisions, there is no way of executing the same transition twice in one call.
    // Set a random identifier since we won't be needing to access this variable later.
    if ($entity instanceof RevisionableInterface && !$entity->isDefaultRevision()) {
      $entity_id = $entity_id . $entity->getRevisionId();
    }

    if (isset($static_info[$entity_id][$field_name][$label]) && !$this->isEmpty()) {
      // Error: this Transition is already executed.
      // On the development machine, execute() is called twice, when
      // on an Edit Page, the entity has a scheduled transition, and
      // user changes it to 'immediately'.
      // Why does this happen?? ( BTW. This happens with every submit.)
      // Remedies:
      // - search root cause of second call.
      // - try adapting code of transition->save() to avoid second record.
      // - avoid executing twice.
      $message = 'Transition is executed twice in a call. The second call for
        @entity_type %entity_id is not executed.';
      $this->logError($message);

      // Return the result of the last call.
      return $static_info[$entity_id][$field_name][$label]; // <-- exit !!!
    }

    // OK. Prepare for next round. Do not set last_sid!!
    $static_info[$entity_id][$field_name][$label] = $from_sid;

    // Make sure $force is set in the transition, too.
    if ($force) {
      $this->force($force);
    }
    $force = $this->isForced();

    // Store the transition(s), so it can be easily fetched later on.
    // This is a.o. used in:
    // - hook_entity_update to trigger 'transition post',
    // - hook workflow_access_node_access_records.
    $entity->workflow_transitions[$field_name] = $this;

    if (!$this->isValid()) {
      return $from_sid;  // <-- exit !!!
    }

    // @todo Move below code to $this->isAllowed().
    // If the state has changed, check the permissions.
    // No need to check if Comments or attached fields are filled.
    if ($this->hasStateChange()) {
      // Make sure this transition is allowed by workflow module Admin UI.
      if (!$force) {
        $user->addRole(WORKFLOW_ROLE_AUTHOR_RID);
      }
      if (!$this->isAllowed($user, $force)) {
        $message = 'User %user not allowed to go from state %sid1 to %sid2';
        $this->logError($message);
        return FALSE;  // <-- exit !!!
      }

      // Make sure this transition is valid and allowed for the current user.
      // Invoke a callback indicating a transition is about to occur.
      // Modules may veto the transition by returning FALSE.
      // (Even if $force is TRUE, but they shouldn't do that.)
      // P.S. The D7 hook_workflow 'transition permitted' is removed, in favour of below hook_workflow 'transition pre'.
      $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition pre', $this, $user]);
      // Stop if a module says so.
      if (in_array(FALSE, $permitted, TRUE)) {
        // @todo There is a watchdog error, but no UI-error. Is this OK?
        $message = 'Transition vetoed by module.';
        $this->logError($message, 'notice');
        return FALSE;  // <-- exit !!!
      }
    }

    /*
     * Output: process the transition.
     */
    if ($this->isScheduled()) {
      /*
       * Log the transition in {workflow_transition_scheduled}.
       */
      $this->save();
    }
    else {
      // The transition is allowed, but not scheduled.
      // Let other modules modify the comment.
      // The transition (in context) contains all relevant data.
      $context = ['transition' => $this];
      \Drupal::moduleHandler()->alter('workflow_comment', $comment, $context);
      $this->setComment($comment);

      $this->isExecuted = TRUE;

      if (!$this->isEmpty()) {
        /*
         * Log the transition in {workflow_transition_history}.
         */
        $this->save();

        // Register state change with watchdog.
        if ($this->hasStateChange() && !empty($this->getWorkflow()->getSetting('watchdog_log'))) {
          if ($this->getEntityTypeId() == 'workflow_scheduled_transition') {
            $message = 'Scheduled state change of @entity_type_label %entity_label to %sid2 executed';
          }
          else {
            $message = 'State of @entity_type_label %entity_label set to %sid2';
          }
          $this->logError($message, 'notice');
        }

        // Notify modules that transition has occurred.
        // Action triggers should take place in response to this callback, not the 'transaction pre'.
        //
        // \Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $this, $user]);
        // We have a problem here with Rules, Trigger, etc. when invoking
        // 'transition post': the entity has not been saved, yet. we are still
        // IN the transition, not AFTER. Alternatives:
        // 1. Save the field here explicitly, using field_attach_save;
        // 2. Move the invoke to another place: hook_entity_insert(), hook_entity_update();
        // 3. Rely on the entity hooks. This works for Rules, not for Trigger.
        // --> We choose option 2:
        // @todo D8: figure out usage of $entity->workflow_transitions[$field_name].
        // - First, $entity->workflow_transitions[] is set for easy re-fetching.
        // - Then, post_execute() is invoked via workflow_entity_insert(), _update().
      }
    }

    // Save value in static from top of this function.
    $static_info[$entity_id][$field_name][$label] = $to_sid;

    return $to_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAndUpdateEntity($force = FALSE) {
    $to_sid = $this->getToSid();

    // Generate error and stop if transition has no new State.
    if (!$to_sid) {
      $t_args = [
        '%sid2' => $this->getToState()->label(),
        '%entity_label' => $this->getTargetEntity()->label(),
      ];
      $message = "Transition is not executed for %entity_label, since 'To' state %sid2 is invalid.";
      $this->logError($message);
      $this->messenger()->addError($this->t($message, $t_args));

      return $this->getFromSid();
    }

    // Save the (scheduled) transition.
    $do_update_entity = (!$this->isScheduled() && !$this->isExecuted());
    if ($do_update_entity) {
      $this->_updateEntity();
    }
    else {
      // We create a new transition, or update an existing one.
      // Do not update the entity itself.
      // Validate transition, save in history table and delete from schedule table.
      $to_sid = $this->execute($force);
    }

    return $to_sid;
  }

  /**
   * Internal function to update the Entity.
   */
  private function _updateEntity() {
    // Update the workflow field of the entity.
    $field_name = $this->getFieldName();
    $entity = $this->getTargetEntity();
    // N.B. Align the following functions:
    // - WorkflowDefaultWidget::massageFormValues();
    // - WorkflowManager::executeTransition().
    $entity->{$field_name}->workflow_transition = $this;
    $entity->{$field_name}->value = $this->getToSid();
    // Populate the entity changed timestamp when the option is checked.
    if ($this->getWorkflow()->getSetting('always_update_entity')) {
      $entity->changed = $this->getTimestamp();
    }
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function post_execute($force = FALSE) {
    // @todo D8: This function post_execute() is not yet used.
    workflow_debug(__FILE__, __FUNCTION__, __LINE__); // @todo D8-port: Test this snippet.
    if (!$this->isEmpty()) {
      $user = $this->getOwner();
      \Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $this, $user]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntity(EntityInterface $entity) {
    if (!$entity) {
      $this->entity_type = '';
      $this->entity_id = '';
      $this->revision_id = '';
      $this->delta = 0; // Only single value is supported.
      $this->langcode = Language::LANGCODE_NOT_SPECIFIED;
      return $this;
    }

    // If Transition is added via CommentForm, use the Commented Entity.
    if ($entity->getEntityTypeId() == 'comment') {
      /** @var \Drupal\comment\CommentInterface $entity */
      $entity = $entity->getCommentedEntity();
    }

    $this->entity = $entity;
    /** @var \Drupal\Core\Entity\RevisionableContentEntityBase $entity */
    $this->entity_type = $entity->getEntityTypeId();
    $this->entity_id = $entity->id();
    $this->revision_id = $entity->getRevisionId();
    $this->delta = 0; // Only single value is supported.
    $this->langcode = $entity->language()->getId();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntity() {
    // Use an explicit property, in case of adding new entities.
    if (isset($this->entity)) {
      return $this->entity;
    }
    // @todo D8: the following line only returns Node, not Term.
    /* return $this->entity = $this->get('entity_id')->entity; */

    $entity_type = $this->getTargetEntityTypeId();
    if ($id = $this->getTargetEntityId()) {
      $this->entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);
    }
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityId() {
    return $this->get('entity_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId() {
    return $this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName() {
    return $this->get('field_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->getTargetEntity()->language()->getId();

  }

  /**
   * {@inheritdoc}
   */
  public function getFromState() {
    $sid = $this->getFromSid();
    return ($sid) ? WorkflowState::load($sid) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getToState() {
    $sid = $this->getToSid();
    return ($sid) ? WorkflowState::load($sid) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFromSid() {
    $sid = $this->{'from_sid'}->target_id;
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getToSid() {
    $sid = $this->{'to_sid'}->target_id;
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getComment() {
    return $this->get('comment')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setComment($value) {
    $this->set('comment', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp() {
    return $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestampFormatted() {
    $timestamp = $this->getTimestamp();
    return \Drupal::service('date.formatter')->format($timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function setTimestamp($value) {
    $this->set('timestamp', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isScheduled() {
    return $this->isScheduled;
  }

  /**
   * {@inheritdoc}
   */
  public function isRevertable() {
    // Some states are useless to revert.
    if ($this->getFromSid() == $this->getToSid()) {
      return FALSE;
    }
    // Some states are not fit to revert to.
    $from_state = $this->getFromState();
    if (!$from_state
      || !$from_state->isActive()
      || $from_state->isCreationState()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function schedule($schedule = TRUE) {
    // We do a tricky thing here. The id of the entity is altered, so
    // all functions of another subclass are called.
    // $this->entityTypeId = ($schedule) ? 'workflow_scheduled_transition' : 'workflow_transition';
    //
    $this->isScheduled = $schedule;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecuted($isExecuted = TRUE) {
    $this->isExecuted = $isExecuted;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isExecuted() {
    return (bool) $this->isExecuted;
  }

  /**
   * {@inheritdoc}
   */
  public function isForced() {
    return (bool) $this->isForced;
  }

  /**
   * {@inheritdoc}
   */
  public function force($force = TRUE) {
    $this->isForced = $force;
    return $this;
  }

  /**
   * Implementing interface EntityOwnerInterface. Copied from Comment.php.
   */

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->get('uid')->entity;
    if (!$user || $user->isAnonymous()) {
      $user = User::getAnonymousUser();
      $user->setUsername(\Drupal::config('user.settings')->get('anonymous'));
    }
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * Implementing interface FieldableEntityInterface extends EntityInterface.
   */

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['hid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Transition ID'))
      ->setDescription(t('The transition ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

//    $fields['wid'] = BaseFieldDefinition::create('string')
    $fields['wid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Workflow Type'))
      ->setDescription(t('The workflow type the transition relates to.'))
      ->setSetting('target_type', 'workflow_type')
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
//      ->setSetting('max_length', 32)
//      ->setDisplayOptions('view', [
//        'label' => 'hidden',
//        'type' => 'string',
//        'weight' => -5,
//      ])
//      ->setDisplayOptions('form', [
//        'type' => 'string_textfield',
//        'weight' => -5,
//      ])
//      ->setDisplayConfigurable('form', TRUE)
    ;

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The Entity type this transition belongs to.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setReadOnly(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The Entity ID this record is for.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The current version identifier.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field name'))
      ->setDescription(t('The name of the field the transition relates to.'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setSetting('max_length', 32)
//      ->setDisplayConfigurable('form', FALSE)
//      ->setDisplayOptions('form', [
//        'type' => 'string_textfield',
//        'weight' => -5,
//      ])
//      ->setDisplayConfigurable('view', FALSE)
//      ->setDisplayOptions('view', [
//        'label' => 'hidden',
//        'type' => 'string',
//        'weight' => -5,
//      ])
    ;

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The entity language code.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => 2,
      ]);

    $fields['delta'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Delta'))
      ->setDescription(t('The sequence number for this data item, used for multi-value fields.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['from_sid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('From state'))
      ->setDescription(t('The {workflow_states}.sid the entity started as.'))
      ->setSetting('target_type', 'workflow_state')
      ->setReadOnly(TRUE);

    $fields['to_sid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('To state'))
      ->setDescription(t('The {workflow_states}.sid the entity transitioned to.'))
      ->setSetting('target_type', 'workflow_state')
// @todo D8: Activate this. Test with both Form and Widget.
//      ->setDisplayOptions('form', [
//        'type' => 'select',
//        'weight' => -5,
//      ])
//      ->setDisplayConfigurable('form', TRUE)
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of the transition author.'))
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValue(0)
//      ->setQueryable(FALSE)
//      ->setSetting('handler', 'default')
//      ->setDefaultValueCallback('Drupal\node\Entity\Node::getCurrentUserId')
//      ->setTranslatable(TRUE)
//      ->setDisplayOptions('view', [
//        'label' => 'hidden',
//        'type' => 'author',
//        'weight' => 0,
//      ])
//      ->setDisplayOptions('form', [
//        'type' => 'entity_reference_autocomplete',
//        'weight' => 5,
//        'settings' => [
//          'match_operator' => 'CONTAINS',
//          'size' => '60',
//          'placeholder' => '',
//        ],
//      ])
//      ->setDisplayConfigurable('form', TRUE),
      ->setRevisionable(TRUE);

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('The time that the current transition was executed.'))
//      ->setQueryable(FALSE)
//      ->setTranslatable(TRUE)
//      ->setDisplayOptions('view', [
//        'label' => 'hidden',
//        'type' => 'timestamp',
//        'weight' => 0,
//      ])
// @todo D8: activate this. Test with both Form and Widget.
//      ->setDisplayOptions('form', [
//        'type' => 'datetime_timestamp',
//        'weight' => 10,
//      ])
//      ->setDisplayConfigurable('form', TRUE);
      ->setRevisionable(TRUE);

    $fields['comment'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Log message'))
      ->setDescription(t('The comment explaining this transition.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
// @todo D8: activate this. Test with both Form and Widget.
//      ->setDisplayOptions('form', [
//        'type' => 'string_textarea',
//        'weight' => 25,
//        'settings' => [
//          'rows' => 4,
//        ],
//      ])
//      ->setDisplayConfigurable('form', FALSE)
    ;

    return $fields;
  }

  /**
   * Generate a Watchdog error.
   *
   * @param $message
   * @param $type
   *   {'error' | 'notice'}
   * @param $from_sid
   * @param $to_sid
   */
  public function logError($message, $type = 'error', $from_sid = '', $to_sid = '') {

    // Prepare an array of arguments for error messages.
    $entity = $this->getTargetEntity();
    $t_args = [
      /** @var \Drupal\user\UserInterface $user */
      '%user' => ($user = $this->getOwner()) ? $user->getDisplayName() : '',
      '%sid1' => ($from_sid || !$this->getFromState()) ? $from_sid : $this->getFromState()->label(),
      '%sid2' => ($to_sid || !$this->getToState()) ? $to_sid : $this->getToState()->label(),
      '%entity_id' => $this->getTargetEntityId(),
      '%entity_label' => $entity ? $entity->label() : '',
      '@entity_type' => ($entity) ? $entity->getEntityTypeId() : '',
      '@entity_type_label' => ($entity) ? $entity->getEntityType()->getLabel() : '',
      'link' => ($this->getTargetEntityId() && $this->getTargetEntity()->hasLinkTemplate('canonical')) ? $this->getTargetEntity()->toLink($this->t('View'))->toString() : '',
    ];
    ($type == 'error') ? \Drupal::logger('workflow')->error($message, $t_args)
      : \Drupal::logger('workflow')->notice($message, $t_args);
  }

  /**
   * {@inheritdoc}
   */
  public function dpm($function = '') {
    $transition = $this;
    $entity = $transition->getTargetEntity();
    $time = \Drupal::service('date.formatter')->format($transition->getTimestamp());
    // Do this extensive $user_name lines, for some troubles with Action.
    $user = $transition->getOwner();
    $user_name = ($user) ? $user->getAccountName() : 'unknown username';
    $t_string = $this->getEntityTypeId() . ' ' . $this->id() . ' for workflow_type <i>' . $this->getWorkflowId() . '</i> ' . ($function ? ("in function '$function'") : '');
    $output[] = 'Entity  = ' . $this->getTargetEntityTypeId() . '/' . (($entity) ? ($entity->bundle() . '/' . $entity->id()) : '___/0');
    $output[] = 'Field   = ' . $transition->getFieldName();
    $output[] = 'From/To = ' . $transition->getFromSid() . ' > ' . $transition->getToSid() . ' @ ' . $time;
    $output[] = 'Comment = ' . $user_name . ' says: ' . $transition->getComment();
    $output[] = 'Forced  = ' . ($transition->isForced() ? 'yes' : 'no') . '; ' . 'Scheduled = ' . ($transition->isScheduled() ? 'yes' : 'no');
    if (function_exists('dpm')) {// In Workflow->dpm().
      /** @phpstan-ignore-next-line */
      dpm($output, $t_string);   // In Workflow->dpm().
    }                            // In Workflow->dpm().
  }

}
