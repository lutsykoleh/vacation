<?php

namespace Drupal\computed_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plugin implementation of the 'computed_string' field type.
 *
 * @FieldType(
 *   id = "computed_string",
 *   label = @Translation("Computed (text)"),
 *   description = @Translation("This field defines a text field whose value is computed by PHP-Code"),
 *   category = @Translation("Computed"),
 *   default_widget = "computed_string_widget",
 *   default_formatter = "computed_string"
 * )
 */
class ComputedStringItem extends ComputedStringItemBase {
  use ComputedFieldStronglyTypedItemTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'max_length' => 255,
      'is_ascii' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $schema = [
      'columns' => [
        'value' => [
          'type' => $settings['is_ascii'] === TRUE ? 'varchar_ascii' : 'varchar',
          'length' => (int) $settings['max_length'],
          'binary' => $settings['case_sensitive'],
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $settings = $this->getSettings();
    $element = [];

    $element['max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length'),
      '#default_value' => $settings['max_length'],
      '#required' => TRUE,
      '#description' => $this->t('The maximum length of the field in characters.'),
      '#min' => 1,
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @return string
   *   The string.
   */
  public function executeCode() {
    return mb_substr(parent::executeCode(), 0, $this->getSettings()['max_length'] - 1);
  }

}
