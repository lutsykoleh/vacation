<?php

namespace Drupal\eca_tamper\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_tamper\Plugin\TamperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide all tamper plugins as ECA actions.
 *
 * @Action(
 *   id = "eca_tamper",
 *   deriver = "Drupal\eca_tamper\Plugin\Action\TamperDeriver"
 * )
 */
class Tamper extends ConfigurableActionBase {

  use TamperTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ConfigurableActionBase {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tamperManager = $container->get('plugin.manager.tamper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException | \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function execute(): void {
    $value = $this->doTamper('eca_data', 'eca_token_name');
    $this->tokenServices->addTokenData($this->configuration['eca_token_name'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'eca_data' => '',
      'eca_token_name' => '',
    ] + $this->tamperDefaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['eca_data'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data to be tampered'),
      '#description' => $this->t('This field supports tokens.'),
      '#default_value' => $this->configuration['eca_data'],
      '#required' => TRUE,
      '#weight' => -10,
    ];
    $form['eca_token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Result token name'),
      '#description' => $this->t('Provide a token name under which the tampered result will be made available for subsequent actions.'),
      '#default_value' => $this->configuration['eca_token_name'],
      '#required' => TRUE,
      '#weight' => 99,
    ];
    return $this->buildTamperConfigurationForm($form, $form_state);
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
    $this->configuration['eca_data'] = $form_state->getValue('eca_data');
    $this->configuration['eca_token_name'] = $form_state->getValue('eca_token_name');
    $this->submitTamperConfigurationForm($form, $form_state);
    $this->configuration = $this->tamperPlugin()->getConfiguration() + $this->configuration;
  }

}
