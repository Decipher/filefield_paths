<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\filefield_paths\Utility\FieldItem;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Field Widget Single Element Form Alter hook implementation.
 */
final readonly class FieldWidgetSingleElementForm {

  /**
   * Constructor.
   *
   * @param \Closure $configFactoryClosure
   *   The config factory closure.
   */
  public function __construct(
    #[AutowireServiceClosure(ConfigFactoryInterface::class)]
    private \Closure $configFactoryClosure,
  ) {}

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  // @phpstan-ignore-next-line
  #[Hook('field_widget_single_element_form_alter')]
  public function formAlter(array &$element, FormStateInterface $form_state, array $context): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    // Force all File (Field) Paths uploads to go to the temporary file system
    // prior to being processed. Skipped entirely when disabled site-wide, so
    // uploads fall back to the field's own upload location instead of being
    // staged somewhere they will never be moved out of.
    if (!($this->getSettings()->get('enabled') ?? TRUE)) {
      return;
    }
    if (FieldItem::hasConfigurationEnabled(FieldItem::getFromSupportedWidget($element, $context))) {
      $settings = $context['items']->getFieldDefinition()
        ->getThirdPartySettings('filefield_paths');
      $temp_location = $settings['temp_location'] ?? NULL;
      $element['#upload_location'] = $temp_location ?:
        $this->getSettings()->get('temp_location');
    }
  }

  /**
   * Retrieves the configuration settings for filefield paths.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration settings object.
   */
  private function getSettings(): ImmutableConfig {
    return ($this->configFactoryClosure)()->get('filefield_paths.settings');
  }

}
