<?php

/**
 * @file
 * File (Field) Paths module integration.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Implements hook_filefield_paths_process_file().
 */
function filefield_paths_filefield_paths_process_file(ContentEntityInterface $entity, FileFieldItemList $field, array &$settings = []) {
  /** @var \Drupal\field\Entity\FieldConfig $field_config */
  $field_config = $field->getFieldDefinition();
  /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
  $field_storage = $field_config->getFieldStorageDefinition();

  /** @var \Drupal\Core\Config\ImmutableConfig $config */
  $config = \Drupal::config('filefield_paths.settings');
  /** @var \Psr\Log\LoggerInterface $logger */
  $logger = \Drupal::logger('filefield_paths');
  /** @var \Drupal\Core\File\FileSystemInterface $file_system */
  $file_system = \Drupal::service('file_system');
  /** @var \Drupal\file\FileRepositoryInterface $file_repository */
  $file_repository = \Drupal::service('file.repository');

  // Check that the destination is writeable.
  /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
  $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');
  $wrappers = $stream_wrapper_manager->getWrappers(StreamWrapperInterface::WRITE);

  $destination_scheme_name = $field_storage->getSetting('uri_scheme');
  $temp_location = !empty($settings['temp_location']) ? $settings['temp_location'] : $config->get('temp_location');
  $temporary_scheme_name = StreamWrapperManager::getScheme($temp_location);
  $schemas = [$temporary_scheme_name, $destination_scheme_name];

  /** @var \Drupal\file\Entity\File $file */
  foreach ($field->referencedEntities() as $file) {
    $source_scheme_name = StreamWrapperManager::getScheme($file->getFileUri());
    if (in_array($source_scheme_name, $schemas) && !empty($wrappers[$destination_scheme_name])) {
      // Process file if this is a new entity, 'Active updating' is set or
      // file wasn't previously attached to the entity.
      if (!$entity->isNew() && isset($entity->original) && empty($settings['active_updating']) && !$entity->original->{$field->getName()}->isEmpty()) {
        /** @var \Drupal\file\Entity\File $original_file */
        foreach ($entity->original->{$field->getName()}->referencedEntities() as $original_file) {
          if ($original_file->id() == $file->id()) {
            continue 2;
          }
        }
      }

      $token_data = [
        'file' => $file,
        $entity->getEntityTypeId() => $entity,
      ];

      // Process filename.
      $settings['file_name']['options']['context'] = 'file_name';
      $name = $file->getFilename();
      if (!empty($settings['file_name']['value'])) {
        $name = filefield_paths_process_string($settings['file_name']['value'], $token_data, $settings['file_name']['options']);
      }

      // Process filepath.
      $settings['file_path']['options']['context'] = 'file_path';
      $path = filefield_paths_process_string($settings['file_path']['value'], $token_data, $settings['file_path']['options']);

      $destination = $stream_wrapper_manager->normalizeUri($field_storage->getSetting('uri_scheme') . '://' . $path . '/' . $name);

      // Ensure file uri is no more than 255 characters.
      if (mb_strlen($destination) > 255) {
        $logger->info('File path was truncated');
        $pathinfo = pathinfo($destination);
        $destination = mb_substr($destination, 0, 254 - mb_strlen($pathinfo['extension'])) . ".{$pathinfo['extension']}";
      }

      // Finalize file if necessary.
      if ($file->getFileUri() !== $destination && file_exists($file->getFileUri())) {
        $dirname = $file_system->dirname($destination);
        $dir_exists = $file_system->prepareDirectory($dirname, FileSystem::CREATE_DIRECTORY);
        if (!$dir_exists) {
          $logger->notice('The directory %directory could not be created.', ['%directory' => $dirname]);
          continue;
        }

        $file->setPermanent();

        try {
          $new_file = $file_repository->move($file, $destination);
        }
        catch (\Exception $e) {
          $logger->notice('The file %old could not be moved to the destination of %new. Ensure your permissions are set correctly.', [
            '%old' => $file->getFileUri(),
            '%new' => $destination,
          ]);
          continue;
        }

        // Create redirect from old location.
        if (
          \Drupal::moduleHandler()->moduleExists('redirect')
          && !empty($settings['redirect']) && $settings['active_updating']
        ) {
          /** @var \Drupal\filefield_paths\Redirect $redirect */
          $redirect = \Drupal::service('filefield_paths.redirect');
          $redirect->createRedirect($file->getFileUri(), $new_file->getFileUri(), $file->language());
        }

        // Remove any old empty directories.
        // @todo Fix problem of missing test for the line below here.
        $paths = explode('/', str_replace("{$source_scheme_name}://", '', $file_system->dirname($file->getFileUri())));
        while ($paths) {
          if (@$file_system->rmdir("{$source_scheme_name}://" . implode('/', $paths)) == TRUE) {
            array_pop($paths);
            continue;
          }
          break;
        }
      }
    }
  }
}
