<?php

namespace Drupal\narrow_widgets\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\narrow_widgets\MultipleNarrowWidgetTrait;
use Drupal\narrow_widgets\ReferenceNarrowWidgetTrait;

/**
 * Plugin implementation of the 'reference_narrow_widget' widget.
 *
 * Adds possibility for:
 *   - inline bundle selection of the available ones
 *   - limitation of the number of references (min, max)
 *
 * @FieldWidget(
 *   id = "reference_narrow_widget",
 *   label = @Translation("Autocomplete (Narrower)"),
 *   description = @Translation("An autocomplete widget with additional settings."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ReferenceNarrowWidget extends EntityReferenceAutocompleteWidget {

  use ReferenceNarrowWidgetTrait;
  use MultipleNarrowWidgetTrait;

  /**
   * {@inheritdoc}
   */
  protected $primaryValueKey = 'target_id';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() +
      static::getReferenceNarrowDefaultSettings() +
      static::getMultipleNarrowDefaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $formBuild = parent::settingsForm($form, $form_state);
    $this->addReferenceNarrowSettings($formBuild, $form_state);
    $this->addMultipleNarrowSettings($formBuild, $form_state);
    return $formBuild;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $formBuild = parent::form($items, $form, $form_state, $get_delta);
    // Add validation for multiple limitations.
    $formBuild['widget']['#element_validate'] = [[$this, 'validateMultipleNarrowForm']];
    $this->addLimitToFieldLabel($formBuild['widget']['#title'], $form_state);
    return $formBuild;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $formBuild = parent::formMultipleElements($items, $form, $form_state);
    $this->alterAddMoreButtonForm($formBuild, 'add_more', $form_state);
    return $formBuild;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $formBuild = parent::formElement($items, $delta, $element, $form, $form_state);
    $this->formElementAddBundleSelector($formBuild, $items, $delta, $element, $form_state);
    return $formBuild;
  }

}
