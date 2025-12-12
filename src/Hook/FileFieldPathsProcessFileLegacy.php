<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\PathProcessorInterface;
use Drupal\filefield_paths\RedirectInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Implements hook_filefield_paths_process_file().
 *
 * @todo Convert this to a plugin.
 */
final class FileFieldPathsProcessFileLegacy {

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly StreamWrapperManagerInterface $streamWrapperManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly PathProcessorInterface $pathProcessor,
    #[Autowire(service: 'logger.channel.filefield_paths')]
    private readonly LoggerChannelInterface $logger,
    #[AutowireServiceClosure(RedirectInterface::class)]
    private readonly \Closure $redirectClosure,
  ) {}

  /**
   * Implements hook_filefield_paths_process_file().
   */
  // @phpstan-ignore-next-line
  #[Hook('filefield_paths_process_file')]
  public function fileFieldPathsProcessFile(ContentEntityInterface $entity, FileFieldItemList $field, array &$settings = []): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $field->getFieldDefinition();
    /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
    $field_storage = $field_config->getFieldStorageDefinition();

    $config = $this->configFactory->get('filefield_paths.settings');

    // Check that the destination is writeable.
    $wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE);

    $destination_scheme_name = $field_storage->getSetting('uri_scheme');
    $temp_location = !empty($settings['temp_location']) ? $settings['temp_location'] : $config->get('temp_location');
    $temporary_scheme_name = $this->streamWrapperManager::getScheme($temp_location);
    $schemas = [$temporary_scheme_name, $destination_scheme_name];

    /** @var \Drupal\file\Entity\File $file */
    foreach ($field->referencedEntities() as $file) {
      $source_scheme_name = $this->streamWrapperManager::getScheme($file->getFileUri());
      if (!(!empty($wrappers[$destination_scheme_name]) && in_array($source_scheme_name, $schemas, TRUE))) {
        // Unexpected source scheme.
        continue;
      }
      // Process file if this is a new entity, 'Active updating' is set or
      // file wasn't previously attached to the entity.
      if (isset($entity->original) && empty($settings['active_updating']) && !$entity->isNew() && !$entity->original->{$field->getName()}->isEmpty()) {
        /** @var \Drupal\file\Entity\File $original_file */
        foreach ($entity->original->{$field->getName()}->referencedEntities() as $original_file) {
          if ((string) $original_file->id() === (string) $file->id()) {
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
        $name = $this->pathProcessor->processString($settings['file_name']['value'], $token_data, $settings['file_name']['options']);
      }

      // Process filepath.
      $settings['file_path']['options']['context'] = 'file_path';
      $path = $this->pathProcessor->processString($settings['file_path']['value'], $token_data, $settings['file_path']['options']);

      $destination = $this->streamWrapperManager->normalizeUri($field_storage->getSetting('uri_scheme') . '://' . $path . '/' . $name);

      // Ensure file uri is no more than 255 characters.
      if (mb_strlen($destination) > 255) {
        $this->logger->info('File path was truncated');
        $pathinfo = pathinfo($destination);
        $destination = mb_substr($destination, 0, 254 - mb_strlen($pathinfo['extension'])) . ".{$pathinfo['extension']}";
      }

      // Finalize file if necessary.
      if (!($file->getFileUri() !== $destination && file_exists($file->getFileUri()))) {
        // File is already in the right place.
        continue;
      }
      $dirname = $this->fileSystem->dirname($destination);
      $dir_exists = $this->fileSystem->prepareDirectory($dirname, $this->fileSystem::CREATE_DIRECTORY);
      if (!$dir_exists) {
        $this->logger->notice('The directory %directory could not be created.', ['%directory' => $dirname]);
        continue;
      }

      $file->setPermanent();

      try {
        $new_file = $this->fileRepository->move($file, $destination);
      }
      catch (\Exception $e) {
        $this->logger->notice('The file %old could not be moved to the destination of %new. Ensure your permissions are set correctly.', [
          '%old' => $file->getFileUri(),
          '%new' => $destination,
        ]);
        continue;
      }

      // Create redirect from old location.
      if (
        !empty($settings['redirect']) && $settings['active_updating'] &&
        $this->moduleHandler->moduleExists('redirect')
      ) {
        $redirect = $this->getRedirect();
        $redirect->createRedirect($file->getFileUri(), $new_file->getFileUri(), $file->language());
      }

      // Remove any old empty directories.
      // @todo Fix problem of missing test for the line below here.
      $paths = explode('/', str_replace("{$source_scheme_name}://", '', $this->fileSystem->dirname($file->getFileUri())));
      while ($paths) {
        if (!@$this->fileSystem->rmdir("{$source_scheme_name}://" . implode('/', $paths))) {
          // No dirs was removed, so we're done.
          break;
        }
        array_pop($paths);
      }
    }
  }

  /**
   * Returns redirect service.
   *
   * @return \Drupal\filefield_paths\RedirectInterface
   *   The redirect service.
   */
  private function getRedirect(): RedirectInterface {
    return ($this->redirectClosure)();
  }

}
