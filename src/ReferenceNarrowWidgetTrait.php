<?php

namespace Drupal\narrow_widgets;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Trait ReferenceNarrowWidgetTrait.
 *
 * Adds possibility to display near the reference autocomplete field an
 * additional field for selection for the bundle of the entity to reference.
 * In this manner the autocomplete field will only search for the selected
 * bundle.
 *
 * @package Drupal\narrow_widgets
 */
trait ReferenceNarrowWidgetTrait {

  /**
   * Get default additional settings for this widget.
   *
   * @return array
   *   Array of defaults.
   */
  protected static function getReferenceNarrowDefaultSettings() {
    return [
      'show_bundle_selector' => FALSE,
      'handlers' => [],
    ];
  }

  /**
   * Adds the reference bundle narrow option to the settings form.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function addReferenceNarrowSettings(array &$form, FormStateInterface $form_state) {
    // If we cannot reference multiple bundles there is no point in adding
    // the bundle type selector.
    if (!$this->canReferenceMultipleBundles()) {
      return;
    }

    $form['show_bundle_selector'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display bundle narrowing'),
      '#default_value' => $this->getSetting('show_bundle_selector'),
    ];

    $form['handlers'] = [
      '#type' => 'details',
      '#title' => $this->t('Bundle selection handlers'),
      '#tree' => TRUE,
    ];

    $viewOptions = [
      'target_type' => $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type'),
      'handler' => 'views',
      'handler_settings' => [],
      'entity' => NULL,
    ];
    $selectionManager = \Drupal::service('plugin.manager.entity_reference_selection');
    $viewHandler = $selectionManager->getInstance($viewOptions);
    $viewSelection = $viewHandler->buildConfigurationForm([], $form_state);
    $viewSelection = $viewSelection['view'];
    $viewSelection['view_and_display']['#options']['default'] = $this->t('Don\'t use view selection');

    foreach ($this->getReferenceableBundles() as $bundle) {
      $handlerType = $this->getSetting('handlers')[$bundle];
      $form['handlers'][$bundle] = $viewSelection;
      $form['handlers'][$bundle]['#type'] = 'fieldset';
      $form['handlers'][$bundle]['#title'] = $bundle;
      $form['handlers'][$bundle]['view_and_display']['#default_value'] = ($handlerType && $handlerType['display_name']) ? $handlerType['view_name'] . '.' . $handlerType['display_name'] : 'default';
      $form['handlers'][$bundle]['arguments']['#default_value'] = ($handlerType && $handlerType['arguments']) ? $handlerType['arguments'] : NULL;
      $form['handlers'][$bundle]['arguments']['#required'] = FALSE;
    }
  }

  /**
   * Determines whether the field can reference multiple bundles.
   *
   * @return bool
   *   Multiple bundles or not.
   */
  protected function canReferenceMultipleBundles() {
    return count($this->getReferenceableBundles()) > 1;
  }

  /**
   * Gets the reference-able bundles.
   *
   * @return array
   *   Bundle IDs.
   */
  protected function getReferenceableBundles() {
    $settings = $this->getFieldSetting('handler_settings');
    // We might have different handlers that don't have this setting. In that
    // case we can't determine the support so just return false.
    if (!array_key_exists('target_bundles', $settings)) {
      return [];
    }

    return $settings['target_bundles'];
  }

  /**
   * Adds the bundle type selector to the element if it is relevant.
   *
   * @param array $form
   *   Current form.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Items.
   * @param int $delta
   *   The number of the item.
   * @param array $element
   *   The main element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function formElementAddBundleSelector(array &$form, FieldItemListInterface $items, $delta, array $element, FormStateInterface $form_state) {
    // If we cannot reference multiple bundles there is no point in adding
    // the bundle type selector.
    if (!$this->canReferenceMultipleBundles()) {
      return;
    }

    // Build list of entity bundle options.
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo */
    $bundleInfo = \Drupal::service('entity_type.bundle.info');
    $entityBundles = $bundleInfo->getBundleInfo($this->getFieldSetting('target_type'));
    $bundleOptions = $this->getFieldSetting('handler_settings')['target_bundles'];
    foreach ($bundleOptions as $id => $bundleOption) {
      $bundleOptions[$id] = $entityBundles[$id]['label'];
    }

    $fieldName = array_merge($element['#field_parents'], [
      $items->getFieldDefinition()->getName(), $delta, 'bundle'
    ]);

    // Get the current selections bundle.
    // First try to get it from the form state.
    $currentBundle = $form_state->getValue($fieldName);
    // If the form state has no value than try to put in the selected entities
    // bundle.
    if (!$currentBundle) {
      if (($referencedEntities = $items->referencedEntities()) && !empty($referencedEntities[$delta])) {
        $currentBundle = $referencedEntities[$delta]->getType();
      }
      // Finally select the first one from the possible ones.
      else {
        $currentBundle = key($bundleOptions);
      }
    }

    $formBuild = [
      '#type' => 'fieldset',
      '#title' => $form['target_id']['#title'],
      '#attributes' => ['class' => ['form--inline']],
    ];

    $autocompleteWrapperId = implode('-', $fieldName) . '-' . $delta . '-type';
    // Add the selector for the bundle.
    $formBuild['bundle'] = [
      '#type' => 'select',
      '#options' => $bundleOptions,
      '#default_value' => $currentBundle,
      '#ajax' => [
        'callback' => [$this, 'setReferenceAbleBundle'],
        'event' => 'change',
        'wrapper' => $autocompleteWrapperId,
        'method' => 'replace',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ]
    ];

    $formBuild += $form;
    $formBuild['target_id']['#title_display'] = FALSE;

    $handlerSettings = $this->getSetting('handlers');
    if (!array_key_exists($currentBundle, $handlerSettings)) {
      $handlerSettings[$currentBundle]['view_name'] = 'default';
    }

    // Set the default or the view selection for the bundle.
    if ($handlerSettings[$currentBundle]['view_name'] === 'default') {
      $formBuild['target_id']['#selection_settings']['target_bundles'] = [$currentBundle];
    }
    else {
      unset($formBuild['target_id']['#selection_settings']['target_bundles']);
      $formBuild['target_id']['#selection_handler'] = 'views';
      $formBuild['target_id']['#selection_settings']['view'] = $handlerSettings[$currentBundle];
    }

    $formBuild['target_id']['#prefix'] = "<div id='$autocompleteWrapperId'>";
    $formBuild['target_id']['#suffix'] = "</div>";

    $form = $formBuild;
  }

  /**
   * Replaces the reference selection field.
   *
   * @param array $form
   *   Current form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Current form state.
   *
   * @return array
   *   The new element.
   */
  public function setReferenceableBundle(array &$form, FormStateInterface $formState) {
    $triggerElement = $formState->getTriggeringElement();

    $elementWidget = NestedArray::getValue($form, array_slice($triggerElement['#array_parents'], 0, -1));
    $element = $elementWidget['target_id'];
    $element['#value'] = $element['#default_value'] = NULL;

    $formState->setRebuild(TRUE);

    return $element;
  }

}
