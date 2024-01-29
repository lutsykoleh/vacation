<?php

namespace Drupal\eca_tamper\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\ECA\Condition\StringComparisonBase;
use Drupal\eca_tamper\Plugin\TamperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide all tamper plugins as ECA conditions.
 *
 * @EcaCondition(
 *   id = "eca_tamper_condition",
 *   deriver = "Drupal\eca_tamper\Plugin\ECA\Condition\TamperDeriver"
 * )
 */
class Tamper extends StringComparisonBase {

  use TamperTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ConditionBase {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tamperManager = $container->get('plugin.manager.tamper');
    $instance->setConfiguration($configuration);
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException | \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getLeftValue(): string {
    $value = $this->doTamper('left', 'right');
    return $value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRightValue(): string {
    return $this->configuration['right'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'left' => '',
      'right' => '',
    ] + $this->tamperDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = $this->buildTamperConfigurationForm($form, $form_state);
    $weight = -99;
    foreach ($form as $key => $value) {
      if (!in_array($key, ['operator', 'type', 'case', 'negate'])) {
        $form[$key]['#weight'] = $weight;
        $weight++;
      }
    }
    $form['left'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Data to be tampered'),
      '#description' => $this->t('This field supports tokens.'),
      '#default_value' => $this->configuration['left'] ?? '',
      '#required' => TRUE,
      '#weight' => -100,
    ];
    $form['right'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Data to compare with'),
      '#description' => $this->t('This field supports tokens.'),
      '#default_value' => $this->getRightValue(),
      '#required' => TRUE,
      '#weight' => -45,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->validateTamperConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['left'] = $form_state->getValue('left');
    $this->configuration['right'] = $form_state->getValue('right');
    $this->submitTamperConfigurationForm($form, $form_state);
    if (!empty($this->tamperPlugin()->defaultConfiguration())) {
      $this->configuration = $this->tamperPlugin()->getConfiguration() + $this->configuration;
    }
  }

}
