<?php

namespace Drupal\eca_tamper\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\tamper\Exception\SkipTamperDataException;
use Drupal\tamper\Exception\SkipTamperItemException;
use Drupal\tamper\Exception\TamperException;
use Drupal\tamper\Plugin\Tamper\FindReplaceRegex;
use Drupal\tamper\SourceDefinition;
use Drupal\tamper\TamperInterface;
use Drupal\tamper\TamperManagerInterface;

/**
 * Trait for ECA tamper actions and conditions.
 */
trait TamperTrait {

  /**
   * The tamper plugin manager.
   *
   * @var \Drupal\tamper\TamperManagerInterface
   */
  protected TamperManagerInterface $tamperManager;

  /**
   * The tamper plugin.
   *
   * @var \Drupal\tamper\TamperInterface
   */
  protected TamperInterface $tamperPlugin;

  /**
   * Return the tamper plugin after it has been fully configured.
   *
   * @return \Drupal\tamper\TamperInterface
   *   This tamper action plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function tamperPlugin(): TamperInterface {
    if (!isset($this->tamperPlugin)) {
      /* @noinspection PhpFieldAssignmentTypeMismatchInspection */
      $this->tamperPlugin = $this->tamperManager->createInstance($this->pluginDefinition['tamper_plugin'], ['source_definition' => new SourceDefinition([])]);

      $configuration = $this->configuration;
      unset($configuration['eca_data'], $configuration['eca_token_name']);
      $this->tamperPlugin->setConfiguration($configuration);
    }
    return $this->tamperPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function tamperDefaultConfiguration(): array {
    if (!isset($this->tamperManager)) {
      return parent::defaultConfiguration();
    }
    try {
      $pluginDefault = $this->tamperPlugin()->defaultConfiguration();
    }
    catch (PluginException $e) {
      $pluginDefault = [];
    }
    return $pluginDefault +
      parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildTamperConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    try {
      return $this->tamperPlugin()->buildConfigurationForm($form, $form_state);
    }
    catch (PluginException $e) {
      // @todo Do we need to log this?
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateTamperConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    try {
      $this->tamperPlugin()->validateConfigurationForm($form, $form_state);
    }
    catch (PluginException $e) {
      // @todo Do we need to log this?
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitTamperConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    try {
      $this->tamperPlugin()->submitConfigurationForm($form, $form_state);
    }
    catch (PluginException $e) {
      // @todo Do we need to log this?
    }
  }

  /**
   * Prepares the plugin and executes the tamper.
   *
   * @return mixed
   *   The tampered result.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException | \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function doTamper(string $dataKey, string $tokenKey) {
    $tamperPlugin = $this->tamperPlugin();
    $regexPlugin = $tamperPlugin instanceof FindReplaceRegex;
    $config = [];
    foreach ($tamperPlugin->defaultConfiguration() as $key => $value) {
      if (in_array($key, [$dataKey, $tokenKey], TRUE)) {
        continue;
      }
      $config[$key] = $regexPlugin && $key === FindReplaceRegex::SETTING_FIND ?
        $this->tokenServices->replace($this->configuration[$key]) :
        $this->tokenServices->replaceClear($this->configuration[$key]);
    }
    $tamperPlugin->setConfiguration($config);
    if (empty($tamperPlugin->getPluginDefinition()['handle_multiples'])) {
      $data = $this->tokenServices->replaceClear($this->configuration[$dataKey]);
    }
    else {
      $data = $this->tokenServices->getOrReplace($this->configuration[$dataKey]);
      if ($data instanceof ComplexDataInterface) {
        $data = $data->toArray();
      }
      elseif ($data instanceof ListInterface) {
        $item_definition = $data->getItemDefinition();
        $main_property = $item_definition instanceof ComplexDataDefinitionInterface ? $item_definition->getMainPropertyName() : NULL;
        $data = $data->getValue() ?? [];
        array_walk($data, static function (&$item) use ($main_property) {
          if (isset($main_property) && is_array($item)) {
            $item = $item[$main_property];
          }
          elseif (is_scalar($item)) {
            // Nothing to do.
          }
          else {
            $item = NULL;
          }
        });
        $data = array_filter($data, static function ($item) {
          return NULL !== $item;
        });
      }
      if (!is_array($data)) {
        $data = [$data];
      }
    }
    try {
      $value = $tamperPlugin->tamper($data);
    }
    catch (SkipTamperDataException | TamperException | SkipTamperItemException $e) {
      $value = $data;
    }
    return $value;
  }

}
