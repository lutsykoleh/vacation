<?php

namespace Drupal\time_range\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeWidgetBase;

/**
 * Base class for the 'timerange_*' widgets.
 */
class TimeRangeWidgetBase extends DateRangeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'start_label' => "Start time",
      'end_label' => "End time",
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#element_validate'][] = [$this, 'validateStartEnd'];
    $element['value']['#title'] = $this->getSetting('start_label');

    $element['end_value'] = [
      '#title' => $this->getSetting('end_label'),
    ] + $element['value'];

    if ($items[$delta]->start_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $items[$delta]->start_date;
      $element['value']['#default_value'] = $this->createDefaultValue($start_date, $element['value']['#date_timezone']);
    }

    if ($items[$delta]->end_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $items[$delta]->end_date;
      $element['end_value']['#default_value'] = $this->createDefaultValue($end_date, $element['end_value']['#date_timezone']);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['start_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start time label'),
      '#default_value' => $this->getSetting('start_label'),
    ];

    $form['end_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End time label'),
      '#default_value' => $this->getSetting('end_label'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Start time label: @label', ['@label' => $this->getSetting('start_label')]);
    $summary[] = $this->t('End time label: @label', ['@label' => $this->getSetting('end_label')]);

    return $summary;
  }

}
