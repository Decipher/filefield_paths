<?php

namespace Drupal\filefield_paths;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\file\FileInterface;

class FileFieldPathsManager {
  /**
   * String cleaning service.
   *
   * @var FileFieldPathsClean
   */
  protected $cleanService;

  /**
   * Token handling service.
   *
   * @var FileFieldPathsToken
   */
  protected $tokenService;

  /**
   * Transliteration service.
   *
   * @var FileFieldPathsTransliterate
   */
  protected $transliterateService;

  /**
   * Content entity being processed.
   *
   * @var ContentEntityInterface
   */
  protected $contentEntity;

  /**
   * Holds the settings for the field being processed.
   *
   * @var Array
   */
  protected $fieldPathSettings;

  public function __construct(FileFieldPathsClean $clean,
                              FileFieldPathsToken $token,
                              FileFieldPathsTransliterate $transliterate) {
    $this->cleanService = $clean;
    $this->tokenService = $token;
    $this->transliterateService = $transliterate;
  }
  
  /**
   * Sets the property that holds the settings for the field in processing.
   *
   * @param array $settings
   */
  protected function setFieldPathSettings(array $settings) {
    $this->fieldPathSettings = $settings;
  }

  /**
   * Finds all the file based fields on a content entity and sends them off
   * to be processed.
   */
  public function processContentEntity(ContentEntityInterface $container_entity) {
    if ($container_entity instanceof ContentEntityInterface) {
      // Get a list of the types of fields that have files. (File, image, video)
      $field_types = _filefield_paths_get_field_types();

      // Get a list of the fields on this entity.
      $fields = $container_entity->getFields();

      // Iterate through all the fields looking for ones in our list.
      foreach ($fields as $key => $field) {
        // Get the field definition which holds the type and our settings.
        $field_definition = $field->getFieldDefinition();
        // Fields based on BaseFieldDefinition don't have third party settings.
        if (in_array('Drupal\Core\Config\Entity\ThirdPartySettingsInterface', class_implements($field_definition))) {
          // Check the field type against our list of fields.
          if (in_array($field_definition->getType(), $field_types)) {
            $this->processField($container_entity, $field_definition);
          }
        }
      }
    }
  }

  /**
   * Finds all the files on the field and sends them to be processed.
   *
   * @param ContentEntityInterface $container_entity
   *   The entity containing the field to process.
   * @param ThirdPartySettingsInterface $field_definition
   *   The field definition to process implementing ThirdPartySettings.
   */
  protected function processField(ContentEntityInterface $container_entity, ThirdPartySettingsInterface $field_definition) {
    $update_field = FALSE;

    // Retrieve the settings we added to the field.
    $this->setFieldPathSettings($field_definition->getThirdPartySettings('filefield_paths'));

    // If FFP is enabled on this field, process it.
    if ($this->fieldPathSettings['enabled']) {
      $translated_files = array();
      // Get the machine name of the field.
      $field_name = $field_definition->getName();

      // Pre-load all translated file entities.
      $languages = $container_entity->getTranslationLanguages();
      foreach ($languages as $language) {
        $langcode = $language->getId();
        $translated_files[$langcode] = $container_entity->getTranslation($langcode)->{$field_name}->referencedEntities();
      }

      // Get the file entities associated with the item.
      /** @var FileInterface $file_entity */
      $files = array();
      foreach ($container_entity->{$field_name}->referencedEntities() as $index => $file_entity) {
        // If the field is a duplicate of an existing translated file entity
        // we'll want to make a copy of the file if the path is changed instead
        // of moving to prevent renaming the translation's file as well.
        // This is because a translated Field does not make a copy of the File
        // entity by default.
        $copy_if_translation = FALSE;
        foreach ($languages as $language) {
          $langcode = $language->getId();
          if ($container_entity->language()->getId() != $langcode && $container_entity->hasTranslation($langcode)) {
            if (isset($translated_files[$langcode][$index]) && $file_entity->id() == $translated_files[$langcode][$index]->id()) {
              $copy_if_translation = TRUE;
              break;
            }
          }
        }

        // Process the file.
        // If the process returns a new file, assign that to the field.
        /** @var FileItem $file_updated_entity */
        $file_updated_entity = $this->processFile($container_entity, $file_entity, $copy_if_translation);
        if ($file_updated_entity && $file_updated_entity->get('uri') != $file_entity->get('uri')) {
          $update_field = TRUE;
          $files[$index] = $file_updated_entity;
        }
        else {
          $files[$index] = $file_entity;
        }
      }
      if ($update_field) {
        $container_entity->set($field_name, $files, FALSE);
      }
    }
  }

  /**
   * Cleans up path and name, moves to new location, and renames.
   *
   * @param ContentEntityInterface $container_entity
   *   The entity containing the file whose field is being processed.
   * @param FileInterface $file_entity
   *   The file whose field is being processed.
   * @param bool $copy
   *   Create a copy of the file entity if the URI's are different.
   *
   * @return FileInterface $file_entity
   *   The updated or new file.
   */
  protected function processFile(ContentEntityInterface $container_entity, FileInterface $file_entity, $copy = FALSE) {
    if ($this->fileNeedsUpdating($file_entity)) {
      // Retrieve the path/name strings with the tokens from settings.
      $tokenized_path = $this->fieldPathSettings['filepath'];
      $tokenized_filename = $this->fieldPathSettings['filename'];

      // Replace tokens.
      $entity_type = $container_entity->getEntityTypeId();
      $data = array($entity_type => $container_entity, 'file' => $file_entity);
      $settings = array(
        'langcode' => $container_entity->language()->getId(),
      );
      $path = $this->tokenService->tokenReplace($tokenized_path, $data, $settings);
      $filename = $this->tokenService->tokenReplace($tokenized_filename, $data, $settings);

      // Transliterate.
      if ($this->fieldPathSettings['path_options']['transliterate_path']) {
        $path = $this->transliterateService->transliterate($path);
      }

      if ($this->fieldPathSettings['name_options']['transliterate_filename']) {
        $filename = $this->transliterateService->transliterate($filename);
      }

      // Clean string to remove URL unfriendly characters.
      if ($this->fieldPathSettings['path_options']['clean_path']) {
        $path_segments = explode("/", $path);
        $cleaned_segments = array();
        foreach ($path_segments as $segment) {
          $cleaned_segments[] = $this->cleanService->cleanString($segment);
        }
        $path = implode("/", $cleaned_segments);
      }

      if ($this->fieldPathSettings['name_options']['clean_filename']) {
        $name_parts = pathinfo($filename);
        $cleaned_base = $this->cleanService->cleanString($name_parts['filename']);
        $cleaned_extension = $this->cleanService->cleanString($name_parts['extension']);

        $filename = $cleaned_base . '.' . $cleaned_extension;
      }

      // @TODO: Sanity check to be sure we don't end up with an empty path or name.
      // If path is empty, just change filename?
      // If filename is empty, use original?
      // Move the file to its new home.
      $destination = file_build_uri($path);
      file_prepare_directory($destination, FILE_CREATE_DIRECTORY);

      if ($copy) {
        $file_entity = file_copy($file_entity, $destination, FILE_EXISTS_RENAME);
      }
      else {
        // Move to a temp directory first to prevent conflicts.
        $tmp_dir = file_stream_wrapper_uri_normalize('temporary://filefield_paths');
        file_prepare_directory($tmp_dir, FILE_CREATE_DIRECTORY);
        $file_entity = file_move($file_entity, $tmp_dir . DIRECTORY_SEPARATOR . $filename);
        // Now move to the new location.
        if ($file_entity) {
          $file_entity = file_move($file_entity, $destination . DIRECTORY_SEPARATOR . $filename);
        }
      }
    }
    return $file_entity;
  }

  /**
   * Determines if a given file should be updated.
   *
   * @param FileInterface $file_entity
   * @return bool
   */
  protected function fileNeedsUpdating(FileInterface $file_entity) {
    // If this field is using active updating, then we always update.
    // If the file is newly uploaded, then we update. Otherwise, leave it alone.
    // Note: $file_entity->isNew() was not accurate for this.
    $file_is_new = ($file_entity->getChangedTime() == REQUEST_TIME);
    return ($this->fieldPathSettings['active_updating'] || $file_is_new);
  }

}
