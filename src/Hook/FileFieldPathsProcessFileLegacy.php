<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Component\Utility\DeprecationHelper;
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
use Drupal\filefield_paths\ProcessOutcomeInterface;
use Drupal\filefield_paths\RedirectInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Implements hook_filefield_paths_process_file().
 *
 * @todo Convert this to a plugin.
 */
final readonly class FileFieldPathsProcessFileLegacy {

  public function __construct(
    private FileSystemInterface $fileSystem,
    private FileRepositoryInterface $fileRepository,
    private StreamWrapperManagerInterface $streamWrapperManager,
    private ModuleHandlerInterface $moduleHandler,
    private PathProcessorInterface $pathProcessor,
    private ProcessOutcomeInterface $processOutcome,
    #[Autowire(service: 'logger.channel.filefield_paths')]
    private LoggerChannelInterface $loggerChannel,
    #[AutowireServiceClosure(RedirectInterface::class)]
    private \Closure $redirectClosure,
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

    // Check that the destination is writeable and the source is readable.
    // Any registered, readable scheme is an acceptable move source: the
    // temporary staging scheme, the field's own destination scheme, or any
    // other scheme (e.g. moving public:// files to private:// after a
    // uri_scheme setting change).
    $wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE);
    $readable_wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::READ);

    $destination_scheme_name = $field_storage->getSetting('uri_scheme');

    /** @var \Drupal\file\Entity\File $file */
    foreach ($field->referencedEntities() as $file) {
      $source_scheme_name = $this->streamWrapperManager::getScheme($file->getFileUri());
      if (empty($wrappers[$destination_scheme_name]) || empty($readable_wrappers[$source_scheme_name])) {
        // Unexpected source or destination scheme.
        $this->loggerChannel->notice('The file %uri was skipped because its scheme (%source) or the destination scheme (%destination) is not a registered, accessible stream wrapper.', [
          '%uri' => $file->getFileUri(),
          '%source' => $source_scheme_name,
          '%destination' => $destination_scheme_name,
        ]);
        $this->processOutcome->recordSkipped($file->id());
        continue;
      }
      // Process file if this is a new entity, 'Active updating' is set or
      // file wasn't previously attached to the entity.
      if (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): bool => $entity->getOriginal() instanceof ContentEntityInterface, fn(): bool => property_exists($entity, 'original') && $entity->original !== NULL) && empty($settings['active_updating']) && !$entity->isNew() && !DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): ?ContentEntityInterface => $entity->getOriginal(), fn() => $entity->original)->{$field->getName()}->isEmpty()) {
        /** @var \Drupal\file\Entity\File $original_file */
        foreach (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): ?ContentEntityInterface => $entity->getOriginal(), fn() => $entity->original)->{$field->getName()}->referencedEntities() as $original_file) {
          if ((string) $original_file->id() === (string) $file->id()) {
            $this->loggerChannel->notice('The file %uri was skipped because it was already attached before this save and active updating is not enabled.', [
              '%uri' => $file->getFileUri(),
            ]);
            $this->processOutcome->recordSkipped($file->id());
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
        $this->loggerChannel->info('File path was truncated');
        $pathinfo = pathinfo($destination);
        $ext = $pathinfo['extension'] ?? '';
        $prefix_max = $ext !== '' ? 254 - mb_strlen($ext) : 255;
        $destination = mb_substr($destination, 0, $prefix_max) . ($ext !== '' ? '.' . $ext : '');
      }

      // Finalize file if necessary.
      // Check the file is on disk first. A record can point at the correct
      // destination while the file itself is gone, and that is a failure,
      // not a file that is already in place.
      if (!file_exists($file->getFileUri())) {
        $this->loggerChannel->notice('The file %uri was skipped because it does not exist on disk.', [
          '%uri' => $file->getFileUri(),
        ]);
        $this->processOutcome->recordSkipped($file->id());
        continue;
      }
      if ($file->getFileUri() === $destination) {
        // File is already in the right place.
        $this->processOutcome->recordUpdated($file->id());
        continue;
      }
      $dirname = $this->fileSystem->dirname($destination);
      $dir_exists = $this->fileSystem->prepareDirectory($dirname, $this->fileSystem::CREATE_DIRECTORY);
      if (!$dir_exists) {
        $this->loggerChannel->notice('The directory %directory could not be created.', ['%directory' => $dirname]);
        $this->processOutcome->recordSkipped($file->id());
        continue;
      }

      $file->setPermanent();

      try {
        $new_file = $this->fileRepository->move($file, $destination);
      }
      catch (\Exception) {
        $this->loggerChannel->notice('The file %old could not be moved to the destination of %new. Ensure your permissions are set correctly.', [
          '%old' => $file->getFileUri(),
          '%new' => $destination,
        ]);
        $this->processOutcome->recordSkipped($file->id());
        continue;
      }
      $this->processOutcome->recordUpdated($file->id());

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
      $paths = explode('/', str_replace($source_scheme_name . '://', '', $this->fileSystem->dirname($file->getFileUri())));
      while ($paths) {
        if (!@$this->fileSystem->rmdir($source_scheme_name . '://' . implode('/', $paths))) {
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
