<?php

namespace Drupal\workflow\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\WorkflowTransition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a workflow state history formatter.
 *
 * @FieldFormatter(
 *   id = "workflow_state_history",
 *   module = "workflow",
 *   label = @Translation("Workflow state history"),
 *   field_types = {
 *     "workflow"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class WorkflowStateHistoryFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

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
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $output = [];

    $field_name = $this->fieldDefinition->getName();
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();

    // @todo Expose limit into formatter settings form.
    $limit = 10;

    $workflowTransitions = WorkflowTransition::loadMultipleByProperties($entity_type, [$entity->id()], [], $field_name, '', $limit, 'DESC');
    if ($workflowTransitions) {
      $workflowTransitionsViews = [];
      foreach ($workflowTransitions as $workflowTransition) {
        $workflowTransitionsView = $this->viewBuilder->view($workflowTransition, 'default');
        $workflowTransitionsViews[] = $workflowTransitionsView;
      }

      $output[] = [
        '#theme' => 'item_list',
        '#items' => $workflowTransitionsViews,
      ];
    }
    else {
      $output[] = [
        '#markup' => Markup::create($this->t('Empty')),
      ];
    }

    return $output;
  }

}
