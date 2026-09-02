<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Component\Utility\Crypt;
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
    // prior to being processed.
    if (FieldItem::hasConfigurationEnabled(FieldItem::getFromSupportedWidget($element, $context))) {
      $settings = $context['items']->getFieldDefinition()
        ->getThirdPartySettings('filefield_paths');
      $temp_location = $settings['temp_location'] ?? NULL;
      $temp_location = $temp_location ?: $this->getSettings()->get('temp_location');
      // Stage each upload in a directory of its own. Two files with the same
      // name would otherwise take the same staged path in turn, and the image
      // style preview URL is built from that path. A browser or a CDN then
      // shows the first image in place of the second.
      // See https://www.drupal.org/i/3277844.
      $element['#upload_location'] = sprintf('%s/ffp-%s', rtrim((string) $temp_location, '/'), Crypt::randomBytesBase64(8));
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
