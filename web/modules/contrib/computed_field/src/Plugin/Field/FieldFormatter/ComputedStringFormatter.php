<?php

namespace Drupal\computed_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plugin implementation of the 'computed_string' formatter.
 *
 * @FieldFormatter(
 *   id = "computed_string",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "computed_string",
 *     "computed_string_long",
 *   }
 * )
 */
class ComputedStringFormatter extends ComputedFormatterBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'sanitized' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
      'sanitized' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Sanitized'),
        '#default_value' => $this->getSetting('sanitized'),
      ],
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $summary[] = $this->getSetting('sanitized') ? $this->t('Sanitized') : $this->t('Unsanitized');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($value) {

    if ($this->getSetting('sanitized')) {
      return nl2br(Html::escape($value));
    }
    else {
      return nl2br($value);
    }
  }

}
