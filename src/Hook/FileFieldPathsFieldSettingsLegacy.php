<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * File (Field) Paths field settings legacy hook implementation.
 *
 * @todo Convert to plugin before 2.0.0.
 */
final class FileFieldPathsFieldSettingsLegacy {

  use StringTranslationTrait;

  /**
   * Implements hook_filefield_paths_field_settings().
   */
  // @phpstan-ignore-next-line
  #[Hook('filefield_paths_field_settings')]
  public function fileFieldPathsFieldSettings(array $form) {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    return [
      'file_path' => [
        'title' => 'File path',
        'form' => [
          'value' => [
            '#type' => 'textfield',
            '#title' => $this->t('File path'),
            '#maxlength' => 512,
            '#size' => 128,
            '#element_validate' => $form['settings']['file_directory']['#element_validate'] ?? [],
            '#default_value' => $form['settings']['file_directory']['#default_value'] ?? NULL,
          ],
        ],
      ],
      'file_name' => [
        'title' => 'File name',
        'form' => [
          'value' => [
            '#type' => 'textfield',
            '#title' => $this->t('File name'),
            '#maxlength' => 512,
            '#size' => 128,
            '#default_value' => '[file:ffp-name-only-original].[file:ffp-extension-original]',
          ],
        ],
      ],
    ];
  }

}
