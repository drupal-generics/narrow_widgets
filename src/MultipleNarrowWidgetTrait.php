<?php

namespace Drupal\narrow_widgets;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Trait MultipleNarrowWidgetTrait.
 *
 * Provides methods for widgets to implement for multiple cardinality fields
 * limitations for the number of selectable values and validation for it.
 *
 * @package Drupal\narrow_widgets
 */
trait MultipleNarrowWidgetTrait {

  /**
   * Get default additional settings for this widget.
   *
   * @return array
   *   Array of defaults.
   */
  protected static function getMultipleNarrowDefaultSettings() {
    return [
      'min' => NULL,
      'max' => NULL,
    ];
  }

  /**
   * Adds the limit field options to the settings form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function addMultipleNarrowSettings(array &$form, FormStateInterface $form_state) {
    // If we cannot add multiple items this has no point.
    if (!$this->isCardinalityMultiple()) {
      return;
    }

    $form['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum'),
      '#default_value' => $this->getSetting('min'),
      '#description' => $this->t('The minimum number of paragraphs that should be allowed in this field. Leave blank for no minimum.'),
    ];
    $form['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum'),
      '#default_value' => $this->getSetting('max'),
      '#description' => $this->t('The maximum number of paragraphs that should be allowed in this field. Leave blank for no maximum.'),
    ];
  }

  /**
   * Adds limit values to the title.
   *
   * Add min/max to the title of the paragraph widget so that the user
   * knows how many is needed for submission.
   *
   * @param string $title
   *   The title element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Current form state.
   */
  protected function addLimitToFieldLabel(&$title, FormStateInterface $formState) {
    $minMax = '';
    if ($min = $this->getSetting('min')) {
      $minMax .= 'min: ' . $min;
    }
    if ($max = $this->getSetting('max')) {
      $minMax .= ($minMax ? ', ' : '') . 'max: ' . $max;
    }
    if ($minMax) {
      $title = $title . ' (' . $minMax . ')';
    }
  }

  /**
   * Removes the add more button if limit has been reached.
   *
   * @param array $form
   *   The current form.
   * @param string $buttonKey
   *   The key to get the button form the form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   */
  protected function alterAddMoreButtonForm(array &$form, $buttonKey, FormStateInterface $formState) {
    // If we reached the maximum allowed values take out the add button.
    if (($max = $this->getSetting('max'))) {
      $valueCount = count(array_filter(array_keys($form), 'is_int'));

      if ($valueCount >= $max) {
        // By default a new field is added to add more content, remove it.
        unset($form[$buttonKey]);
      }

      if ($valueCount > $max && array_key_exists($max, $form)) {
        unset($form[$max]);
      }
    }
  }

  /**
   * Validates the form to contain only the allowed number of values.
   *
   * @param array $element
   *   The widget element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current form state.
   */
  public function validateMultipleNarrowForm(array &$element, FormStateInterface $formState) {
    if (!$this->isCardinalityMultiple()) {
      return;
    }

    $fieldSelector = array_merge($element['#field_parents'] ?: [], [$this->fieldDefinition->getName()]);

    // Don't validate if we are not allowed to.
    if ($formState->getLimitValidationErrors()) {
      return;
    }

    $primaryValue = $this->primaryValueKey ?: 'value';

    $values = $formState->getValue($fieldSelector);
    $elementCount = count(array_filter(array_keys($values), 'is_int'));
    $fieldSelector = array_merge($fieldSelector, [$elementCount - 1, $primaryValue]);

    // Get the number of values supplied.
    $itemCount = count(array_filter($values, function ($element) use ($primaryValue) {
      return is_array($element) && !empty($element[$primaryValue]);
    }));

    // Make sure the user provided at least the specified amount of values.
    if (($min = $this->getSetting('min')) && $itemCount < $min) {
      // Add a + at the end as otherwise errors will spread on all the fields
      // that are in the widget. The + only makes is so that the field is not
      // be found by the form state.
      $formState->setErrorByName(implode('][', $fieldSelector), $this->t('The minimum required amount of values for @label is @number.', [
        '@label' => $this->fieldDefinition->getLabel(),
        '@number' => $min,
      ]));
    }

    // Make sure the user did not provided more then specified amount of values.
    if (($max = $this->getSetting('max')) && $itemCount > $max) {
      $formState->setErrorByName(implode('][', $fieldSelector), $this->t('The maximum number of values for @label is @number.', [
        '@label' => $this->fieldDefinition->getLabel(),
        '@number' => $max,
      ]));
    }
  }

  /**
   * Determines whether the field has multiple cardinality.
   *
   * @return bool
   *   Multiple or single.
   */
  protected function isCardinalityMultiple() {
    return $this->fieldDefinition->getSetting('cardinality') != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
  }

}
