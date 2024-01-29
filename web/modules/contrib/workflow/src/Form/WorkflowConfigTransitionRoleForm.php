<?php

namespace Drupal\workflow\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a class to build a listing of Workflow Config Transitions entities.
 *
 * @see \Drupal\workflow\Entity\WorkflowConfigTransition
 */
class WorkflowConfigTransitionRoleForm extends WorkflowConfigTransitionFormBase {

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'workflow_state';

  /**
   * {@inheritdoc}
   */
  protected $type = 'permission';

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];

    $workflow = $this->workflow;
    $states = $workflow->getStates($all = 'CREATION');
    if ($states) {
      $header['label_new'] = $this->t('From \ To');

      /** @var \Drupal\workflow\Entity\WorkflowState $state */
      foreach ($states as $state) {
        // Don't allow transition TO (creation).
        if (!$state->isCreationState()) {
          $header[$state->id()] = $this->t('@label', ['@label' => $state->label()]);
        }
      }
    }
    return $header;
  }

  /**
   * {@inheritdoc}
   *
   * Builds a row for the following table:
   *   Transitions, for example:
   *     18 => [
   *       20 => [
   *         'author' => 1,
   *         1        => 0,
   *         2        => 1,
   *       ]
   *     ]
   *   means the transition from state 18 to state 20 can be executed by
   *   the content author or a user in role 2. The $transitions array should
   *   contain ALL transitions for the workflow.
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];

    $workflow = $this->workflow;
    if ($workflow) {
      // Each $entity is a from-state.
      /** @var \Drupal\workflow\Entity\WorkflowState $entity */
      $from_state = $entity;
      $from_sid = $from_state->id();

      /** @var \Drupal\workflow\Entity\WorkflowState[] $states */
      $states = $workflow->getStates($all = 'CREATION');
      if ($states) {
        // Only get the roles with proper permission + Author role.
        $type_id = $workflow->id();
        $roles = workflow_get_user_role_names("create $type_id workflow_transition");
        // Prepare default value for 'stay_on_this_state'.
        // array_combine(array_keys($roles), array_keys($roles));
        $allow_all_roles = [];

        /** @var \Drupal\workflow\Entity\WorkflowState $state */
        foreach ($states as $state) {
          $row['to'] = [
            '#type' => 'value',
            '#markup' => $this->t('@label', ['@label' => $from_state->label()]),
          ];

          /** @var \Drupal\workflow\Entity\WorkflowState $to_state */
          foreach ($states as $to_state) {
            // Don't allow transition TO (creation).
            if ($to_state->isCreationState()) {
              continue;
            }
            // Only allow transitions from $from_state.
            if ($state->id() <> $from_state->id()) {
              continue;
            }
            $to_sid = $to_state->id();

            // Load existing config_transitions. Create if not found.
            $config_transitions = $workflow->getTransitionsByStateId($from_sid, $to_sid);
            if (!$config_transition = reset($config_transitions)) {
              $config_transition = $workflow->createTransition($from_sid, $to_sid);
            }
            $stay_on_this_state = !$config_transition->hasStateChange();

            $row[$to_sid]['workflow_config_transition'] = [
              '#type' => 'value',
              '#value' => $config_transition,
            ];
            $row[$to_sid]['roles'] = [
              '#type' => 'checkboxes',
              '#options' => $stay_on_this_state ? [] : $roles,
              '#disabled' => $stay_on_this_state,
              '#default_value' => $stay_on_this_state ? $allow_all_roles : $config_transition->roles,
            ];
          }
        }
      }
    }
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // 'Creation' state may not be the only state.
    if (count($form_state->getValue($this->entitiesKey)) < 2) {
      $form_state->setErrorByName('id', $this->t('Please create at least one other state.'));
      return;
    }

    // Make sure 'author' is checked for (creation) -> [something].
    $creation_state_id = $this->workflow->getCreationState()->id();
    $author_has_permission = FALSE;
    foreach ($form_state->getValue($this->entitiesKey) as $from_sid => $to_data) {
      foreach ($to_data as $to_sid => $transition_data) {
        if (empty($transition_data['roles'][WORKFLOW_ROLE_AUTHOR_RID])) {
          continue;
        }
        if ($from_sid == $creation_state_id && $from_sid != $to_sid) {
          $author_has_permission = TRUE;
          break;
        }
      }
    }
    if (!$author_has_permission) {
      $creation_state = $this->workflow->getCreationState();
      $form_state->setErrorByName('id', $this->t('Please give the author permission to go from %creation to at least one state!',
        ['%creation' => $creation_state->label()]));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    foreach ($form_state->getValue($this->entitiesKey) as $from_sid => $to_data) {
      foreach ($to_data as $transition_data) {
        /** @var \Drupal\workflow\Entity\WorkflowConfigTransition $config_transition */
        if (isset($transition_data['workflow_config_transition'])) {
          $config_transition = $transition_data['workflow_config_transition'];
          $config_transition->roles = $transition_data['roles'];
          $config_transition->save();
        }
        else {
          // Should not be possible.
        }
      }
    }

    $this->messenger()->addStatus($this->t('The workflow was updated.'));
  }

}
