<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Manages entity type plugin definitions.
 */
class WorkflowManager implements WorkflowManagerInterface {

  use StringTranslationTrait;

  /**
   * The entity_field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity_type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $userConfig;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Construct the WorkflowManager object as a service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity_field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   *
   * @see workflow.services.yml
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, ModuleHandlerInterface $module_handler, AccountInterface $current_user) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->userConfig = $config_factory->get('user.settings');
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function executeScheduledTransitionsBetween($start = 0, $end = 0) {
    $clear_cache = FALSE;

    // If the time now is greater than the time to execute a transition, do it.
    foreach (WorkflowScheduledTransition::loadBetween($start, $end) as $scheduled_transition) {
      $entity = $scheduled_transition->getTargetEntity();
      // Make sure transition is still valid: the entity must still be in
      // the state it was in, when the transition was scheduled.
      if (!$entity) {
        continue;
      }

      $field_name = $scheduled_transition->getFieldName();
      $from_sid = $scheduled_transition->getFromSid();
      $current_sid = workflow_node_current_state($entity, $field_name);
      if (!$current_sid || ($current_sid != $from_sid)) {
        // Entity is not in the same state it was when the transition
        // was scheduled. Defer to the entity's current state and
        // abandon the scheduled transition.
        $message = t('Scheduled Transition is discarded, since Entity has state ID %sid1, instead of expected ID %sid2.');
        $scheduled_transition->logError($message, 'error', $current_sid, $from_sid);
        $scheduled_transition->delete();
        continue;
      }

      // If user didn't give a comment, create one.
      $comment = $scheduled_transition->getComment();
      if (empty($comment)) {
        $scheduled_transition->addDefaultComment();
      }

      // Do transition. Force it because user who scheduled was checked.
      // The scheduled transition is not scheduled anymore, and is also deleted from DB.
      // A watchdog message is created with the result.
      $scheduled_transition->schedule(FALSE);
      $scheduled_transition->executeAndUpdateEntity(TRUE);

      if (!$field_name) {
        $clear_cache = TRUE;
      }
    }

    if ($clear_cache) {
      // Clear the cache so that if the transition resulted in a entity
      // being published, the anonymous user can see it.
      Cache::invalidateTags(['rendered']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function executeTransitionsOfEntity(EntityInterface $entity) {

    // Avoid this hook on workflow objects.
    if (WorkflowManager::isWorkflowEntityType($entity->getEntityTypeId())) {
      return;
    }

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $user = workflow_current_user();

    foreach (workflow_get_workflow_field_names($entity) as $field_name) {
      // Transition is created in widget or WorkflowTransitionForm.
      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
      $transition = $entity->{$field_name}->__get('workflow_transition');
      if (!$transition) {
        // We come from creating/editing an entity via entity_form, with core widget or hidden Workflow widget.
        // @todo D8: From an Edit form with hidden widget.
        /* @noinspection PhpUndefinedFieldInspection */
        if ($entity->original) {
          // Editing a Node with hidden Widget. State change not possible, so bail out.
          // $entity->{$field_name}->value = $entity->original->{$field_name}->value;
          // continue;
        }

        // Creating a Node with hidden Workflow Widget. Generate valid first transition.
        $comment = '';
        $old_sid = WorkflowManager::getPreviousStateId($entity, $field_name);
        $new_sid = $entity->{$field_name}->value;
        if ((!$new_sid) && $wid = $entity->{$field_name}->getSetting('workflow_type')) {
          /** @var \Drupal\workflow\Entity\Workflow $workflow */
          $workflow = Workflow::load($wid);
          $new_sid = $workflow->getFirstSid($entity, $field_name, $user);
        }
        $transition = WorkflowTransition::create([$old_sid, 'field_name' => $field_name]);
        $transition->setValues($new_sid, $user->id(), \Drupal::time()->getRequestTime(), $comment, TRUE);
      }

      // We come from Content/Comment edit page, from widget.
      // Set the just-saved entity explicitly. Not necessary for update,
      // but upon insert, the old version didn't have an ID, yet.
      $transition->setTargetEntity($entity);

      if ($transition->isExecuted()) {
        // Sometimes (due to Rules, or extra programming) it can happen that
        // a Transition is executed twice in a call. This check avoids that
        // situation, that generates message "Transition is executed twice".
        $executed = TRUE;
      }
      elseif ($transition->isScheduled()) {
        // Returns a positive integer.
        $executed = $transition->save();
      }
      elseif ($entity->getEntityTypeId() == 'comment') {
        // If Transition is added via CommentForm, save Comment AND Entity.
        // Execute and check the result.
        $new_sid = $transition->executeAndUpdateEntity();
        $executed = ($new_sid == $transition->getToSid()) ? TRUE : FALSE;
      }
      else {
        // Execute and check the result.
        $new_sid = $transition->execute();
        $executed = ($new_sid == $transition->getToSid()) ? TRUE : FALSE;
      }

      // If the transition failed, revert the entity workflow status.
      // For new entities, we do nothing: it has no original.
      if (!$executed && isset($entity->original)) {
        $originalValue = $entity->original->{$field_name}->value;
        $entity->{$field_name}->setValue($originalValue);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreviousStateId(EntityInterface $entity, $field_name = '') {
    $sid = '';

    if (!$entity) {
      return $sid;
    }

    // Determine $field_name if not known, yet.
    $field_name = ($field_name) ? $field_name : workflow_get_field_name($entity, $field_name);
    if (!$field_name) {
      // Return the initial value.
      return $sid;
    }

    // Retrieve previous state from the original.
    if (isset($entity->original) && !empty($entity->original->{$field_name}->value)) {
      return $entity->original->{$field_name}->value;
    }

    // A node may not have a Workflow attached.
    if ($entity->isNew()) {
      return self::getCreationStateId($entity, $field_name);
    }

    // @todo Read history with an explicit langcode(?).
    $langcode = ''; // $entity->language()->getId();
    // @todo D8: #2373383 Add integration with older revisions via Revisioning module.
    $entity_type = $entity->getEntityTypeId();
    $last_transition = WorkflowTransition::loadByProperties($entity_type, $entity->id(), [], $field_name, $langcode, 'DESC');
    if ($last_transition) {
      $sid = $last_transition->getToSid(); // @see #2637092, #2612702
    }
    if (!$sid) {
      // No history found on an existing entity.
      $sid = self::getCreationStateId($entity, $field_name);
    }

    return $sid;
  }

  /**
   * Gets the creation sid for a given $entity and $field_name.
   *
   * Is a helper function for:
   * - workflow_node_current_state()
   * - workflow_node_previous_state()
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $field_name
   *
   * @return string
   *   The ID of the creation State for the Workflow of the field.
   */
  private static function getCreationStateId(EntityInterface $entity, $field_name) {
    $sid = '';

    /** @var \Drupal\Core\Config\Entity\ConfigEntityBase $entity */
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_config */
    $field_config = $entity->get($field_name)->getFieldDefinition();
    $field_storage = $field_config->getFieldStorageDefinition();
    $wid = $field_storage->getSetting('workflow_type');
    if ($wid) {
      /** @var \Drupal\workflow\Entity\Workflow $workflow */
      $workflow = Workflow::load($wid);
      if (!$workflow) {
        \Drupal::messenger()->addError(t('Workflow %wid cannot be loaded. Contact your system administrator.', ['%wid' => $wid]));
      }
      else {
        $sid = $workflow->getCreationSid();
      }
    }

    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public static function deleteUser(AccountInterface $account) {
    self::cancelUser([], $account, 'user_cancel_delete');
  }

  /**
   * {@inheritdoc}
   */
  public static function cancelUser($edit, AccountInterface $account, $method) {

    switch ($method) {
      case 'user_cancel_block':
        // Disable the account and keep its content.
      case 'user_cancel_block_unpublish':
        // Disable the account and unpublish its content.
        // Do nothing.
        break;

      case 'user_cancel_reassign':
        // Delete the account and make its content belong to the Anonymous user.
      case 'user_cancel_delete':
        // Delete the account and its content.
        /*
         * Update tables for deleted account, move account to user 0 (anon.)
         * ALERT: This may cause previously non-Anonymous posts to suddenly
         * be accessible to Anonymous.
         */

        /*
         * Given a user id, re-assign history to the new user account.
         * Called by user_delete().
         */
        $uid = $account->id();
        $new_uid = 0;
        $database = \Drupal::database();
        $database->update('workflow_transition_history')
          ->fields(['uid' => $new_uid])
          ->condition('uid', $uid, '=')
          ->execute();
        $database->update('workflow_transition_schedule')
          ->fields(['uid' => $new_uid])
          ->condition('uid', $uid, '=')
          ->execute();
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function participateUserRoles(EntityInterface $workflow) {
    $type_id = $workflow->id();
    foreach (user_roles() as $rid => $role) {
      $perms = ["create $type_id workflow_transition" => 1];
      user_role_change_permissions($rid, $perms);  // <=== Enable Roles.
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getWorkflowTransitionForm(EntityInterface $entity, $field_name, array $form_state_additions = []) {
    // Create a transition, to pass to the form. No need to use setValues().
    $current_sid = workflow_node_current_state($entity, $field_name);
    $transition = WorkflowTransition::create([$current_sid, 'field_name' => $field_name]);
    $transition->setTargetEntity($entity);

    // Create the WorkflowTransitionForm.
    /** @var \Drupal\Core\Entity\EntityFormBuilder $entity_form_builder */
    $entity_form_builder = \Drupal::getContainer()->get('entity.form_builder');
    $form = $entity_form_builder->getForm($transition, 'add', $form_state_additions);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMap($entity_type_id = '') {
    if ($entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        return [];
      }
    }

    $map = $this->entityFieldManager->getFieldMapByFieldType('workflow');
    if ($entity_type_id) {
      return $map[$entity_type_id] ?? [];
    }
    return $map;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAttachedFields($entity_type_id, $bundle) {
    $added_fields = [];

    // Determine the fields added by Field UI.
    $entity_field_manager = \Drupal::service('entity_field.manager');
    // $extra_fields = $this->entityFieldManager->getExtraFields($entity_type_id, $bundle);
    // $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id, $bundle);
    $fields = $entity_field_manager->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fields as $key => $field) {
      // Remove BaseFieldDefinition, BaseFieldOverride.
      if (($field instanceof FieldConfig)) {
        $added_fields[$key] = $field;
      }
    }
    return $added_fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCurrentStateId(EntityInterface $entity, $field_name = '') {
    $sid = '';

    if (!$entity) {
      return $sid;
    }

    // If $field_name is not known, yet, determine it.
    $field_name = ($field_name) ? $field_name : workflow_get_field_name($entity, $field_name);
    // If $field_name is found, get more details.
    if (!$field_name || !isset($entity->{$field_name})) {
      // Return the initial value.
      return $sid;
    }

    // Normal situation: get the value.
    $sid = $entity->{$field_name}->value;

    // Entity is new or in preview or there is no current state. Use previous state.
    // (E.g., content was created before adding workflow.)
    if (!$sid || !empty($entity->isNew()) || !empty($entity->in_preview)) {
      $sid = self::getPreviousStateId($entity, $field_name);
    }

    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public static function isOwner(AccountInterface $account, EntityInterface $entity = NULL) {
    $is_owner = FALSE;

    $entity_id = ($entity) ? $entity->id() : '';
    if (!$entity_id) {
      // This is a new entity. User is author. Add 'author' role to user.
      $is_owner = TRUE;
      return $is_owner;
    }

    $uid = ($account) ? $account->id() : -1;
    // Some entities (e.g., taxonomy_term) do not have a uid.
    // $entity_uid = $entity->get('uid'); // isset($entity->uid) ? $entity->uid : 0;
    $entity_uid = (method_exists($entity, 'getOwnerId')) ? $entity->getOwnerId() : -1;
    if (($entity_uid > 0) && ($uid > 0) && ($entity_uid == $uid)) {
      // This is an existing entity. User is author.
      // D8: use "access own" permission. D7: Add 'author' role to user.
      // N.B.: If 'anonymous' is the author, don't allow access to History Tab,
      // since anyone can access it, and it will be published in Search engines.
      $is_owner = TRUE;
    }
    else {
      // This is an existing entity. User is not the author. Do nothing.
    }
    return $is_owner;
  }

  /**
   * {@inheritdoc}
   */
  public static function isWorkflowEntityType($entity_type_id) {
    return in_array($entity_type_id, [
      'workflow_type',
      'workflow_state',
      'workflow_config_transition',
      'workflow_transition',
      'workflow_scheduled_transition',
    ]);
  }

}
