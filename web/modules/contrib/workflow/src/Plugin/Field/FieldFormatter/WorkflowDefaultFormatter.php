<?php

namespace Drupal\workflow\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a default workflow formatter.
 *
 * @FieldFormatter(
 *   id = "workflow_default",
 *   module = "workflow",
 *   label = @Translation("Workflow form"),
 *   field_types = {
 *     "workflow"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class WorkflowDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The workflow storage.
   *
   * @var \Drupal\workflow\Entity\WorkflowStorage
   */
  protected $storage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The render controller.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WorkflowDefaultFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->viewBuilder = $entity_type_manager->getViewBuilder('workflow_transition');
    $this->storage = $entity_type_manager->getStorage('workflow_transition');
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * N.B. A large part of this function is taken from CommentDefaultFormatter.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $output = [];

    $field_name = $this->fieldDefinition->getName();
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();

    // @todo Perhaps global user is not always the correct user.
    // E.g., on ScheduledTransition->execute()? But this function is mostly used in UI.
    $user = $this->currentUser; // @todo #2287057: OK?

    $current_sid = WorkflowManager::getCurrentStateId($entity, $field_name);
    // First compose the current value with the normal formatter from list.module.
    $elements = workflow_state_formatter($entity, $field_name, $current_sid);

    /** @var \Drupal\workflow\Entity\WorkflowState $current_state */
    $current_state = WorkflowState::load($current_sid);
    // The state must not be deleted, or corrupted.
    if (!$current_state) {
      return $elements;
    }

    // Check permission, so that even with state change rights,
    // the form can be suppressed from the entity view (#1893724).
    $type_id = $current_state->getWorkflowId();
    if (!$this->currentUser->hasPermission("access $type_id workflow_transition form")) {
      return $elements;
    }

    // Workflows are added to the search results and search index by
    // workflow_node_update_index() instead of by this formatter, so don't
    // return anything if the view mode is search_index or search_result.
    if (in_array($this->viewMode, ['search_result', 'search_index'])) {
      return $elements;
    }

    if ($entity_type == 'comment') {
      // No Workflow form allowed on a comment display.
      // (Also, this avoids a lot of error messages.)
      return $elements;
    }

    // Only build form if user has possible target state(s).
    if (!$current_state->showWidget($entity, $field_name, $user, FALSE)) {
      return $elements;
    }

    // Remove the default formatter. We are now building the widget.
    $elements = [];

    // BEGIN Copy from CommentDefaultFormatter.
    $elements['#cache']['contexts'][] = 'user.permissions';
    // Add the WorkflowTransitionForm to the page.
    $output['workflows'] = WorkflowManager::getWorkflowTransitionForm($entity, $field_name, []);

    // Only show the add workflow form if the user has permission.
    $elements['#cache']['contexts'][] = 'user.roles';
    // Do not show the form for the print view mode.
    $elements[] = $output + [
      '#workflow_type' => $this->getFieldSetting('workflow_type'),
      '#workflow_display_mode' => $this->getFieldSetting('default_mode'),
      'workflows' => [],
    ];
    // END Copy from CommentDefaultFormatter.
    return $elements;
  }

}
