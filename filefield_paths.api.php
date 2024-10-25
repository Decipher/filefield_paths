<?php

/**
 * @file
 * Hooks provided by the File (Field) Paths module.
 */

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
function hook_filefield_paths_field_settings(array $form) {
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
function hook_filefield_paths_process_file(ContentEntityInterface $entity, \Drupal\Core\Field\FieldItemListInterface $field, array $settings = []) {
  // Only process files if Active Updating is on.
  if (empty($settings['active_updating'])) {
    return;
  }
  foreach ($field->referencedEntities() as $file) {
    if ($file instanceof FileInterface) {
      // Process file if this is a new entity with a new file attached.
      $original_field = NULL;
      if (
        isset($entity->original)
        && $entity->original instanceof ContentEntityInterface
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
