<?php

/**
 * @file
 * Hooks provided by the File (Field) Paths module.
 *
 * @section sec_disabling Disabling processing
 *
 * File (Field) Paths offers three ways to suppress processing, at different
 * levels of granularity:
 * - Site-wide: disable the "Enable File (Field) Paths" checkbox on the
 *   module's settings form (filefield_paths.admin_settings), or set the
 *   `enabled` key of the `filefield_paths.settings` config object to FALSE.
 *   A persistent config change affecting every field on the site.
 * - Per field, persistent: disable the "Enable File (Field) Paths?" checkbox
 *   on a specific field's settings form. A persistent config change
 *   affecting every entity that uses that field.
 * - Per entity, transient: set the `filefield_paths_settings` property on an
 *   entity before saving it, to suppress (or otherwise override) processing
 *   for that single save only. This is a plain runtime property, never
 *   persisted to storage, and is the recommended approach for migrations and
 *   other programmatic imports where you don't want to touch field
 *   configuration (which would invalidate site-wide caches) just to skip
 *   processing for the rows being imported. For example:
 *   @code
 *   // Suppress every file field on this entity for this save only.
 *   $entity->filefield_paths_settings = ['enabled' => FALSE];
 *
 *   // Suppress just one field, leaving others on the entity untouched.
 *   $entity->filefield_paths_settings = ['field_image' => ['enabled' => FALSE]];
 *
 *   // Override any other settings key the same way, not just 'enabled' -
 *   // here the file still gets moved/renamed, but no redirect is created
 *   // for this save.
 *   $entity->filefield_paths_settings = ['redirect' => FALSE];
 *   @endcode
 *   A key matching a field name on the entity is treated as a field-specific
 *   override (and takes precedence); any other key is applied to every
 *   field. This can only ever suppress or modify processing that is already
 *   enabled via field configuration - it cannot enable File (Field) Paths on
 *   a field where it isn't configured.
 */

declare(strict_types=1);

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Form settings hook.
 *
 * Define field(s) to be displayed on the File (Field) Paths settings form and
 * used during the processing of uploaded files.
 *
 * @param array $form
 *   The form File (Field) Paths settings field applies to.
 *
 * @return array
 *   An array whose keys are field names and whose values are arrays defining
 *   the field, with the following key/value pairs:
 *   - title: The title fo the field.
 *   - form: A keyed array of Form API elements.
 *
 * @see hook_filefield_paths_process_file()
 */
function hook_filefield_paths_field_settings(array $form): array {
  return [
    'file_path' => [
      'title' => 'File path',
      'form' => [
        'value' => [
          '#type' => 'textfield',
          '#title' => t('File path'),
          '#maxlength' => 512,
          '#size' => 128,
          '#element_validate' => ['_file_generic_settings_file_directory_validate'],
          '#default_value' => $form['settings']['file_directory'],
        ],
      ],
    ],
  ];
}

/**
 * Process the uploaded files.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface $entity
 *   The entity containing field with the files for processing.
 * @param \Drupal\Core\Field\FieldItemListInterface $field
 *   File field item.
 * @param array $settings
 *   Contains filefield_paths field settings.
 *
 * @see filefield_paths_filefield_paths_process_file()
 */
function hook_filefield_paths_process_file(ContentEntityInterface $entity, FieldItemListInterface $field, array &$settings = []): void {
  // Only process files if Active Updating is on.
  if (empty($settings['active_updating'])) {
    return;
  }
  foreach ($field->referencedEntities() as $file) {
    if ($file instanceof FileInterface) {
      // Process file if this is a new entity with a new file attached.
      $original_field = NULL;
      if (
        DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): bool => $entity->getOriginal() instanceof ContentEntityInterface, fn(): bool => property_exists($entity, 'original') && $entity->original !== NULL)
        && DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): ?ContentEntityInterface => $entity->getOriginal(), fn() => $entity->original) instanceof ContentEntityInterface
        && !$entity->isNew()
      ) {
        $original_field = $entity->{'original'}->{$field->getName()};
      }
      if ($original_field instanceof FileFieldItemList
        && !$original_field->isEmpty()
      ) {
        $original_files = $original_field->referencedEntities();
        foreach ($original_files as $original_file) {
          if ($original_file instanceof FileInterface
            && $original_file->id() != $file->id()
          ) {
            \Drupal::logger('filefield_paths')
              ->notice(t('The file is new, do some processing.'));
          }
        }
      }
    }
  }
}
