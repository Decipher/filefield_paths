<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\filefield_paths\StagingLocation;

/**
 * File relate hook implementations.
 */
final class File {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  // @phpstan-ignore-next-line
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    $fields = [];
    if ($entity_type->id() === 'file') {
      $fields['origname'] = BaseFieldDefinition::create('string')
        ->setLabel($this->t('Original filename'))
        ->setDescription($this->t('Original name of the file with no path components.'));
    }

    return $fields;
  }

  /**
   * Implements hook_file_presave().
   */
  // @phpstan-ignore-next-line
  #[Hook('file_presave')]
  public function filePresave(FileInterface $file): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    // Store the original filename in the database. A file entity without the
    // origname field, for example on a site whose field storage was not
    // installed, is left alone.
    if (
      $file->hasField('origname') && $file->hasField('filename') &&
      $file->get('origname')->isEmpty() && !$file->get('filename')->isEmpty()
    ) {
      $file->set('origname', $file->get('filename')->value);
    }
  }

  /**
   * Implements hook_file_delete().
   */
  #[Hook('file_delete')]
  public function fileDelete(FileInterface $file): void {
    // Each upload is staged in a directory of its own, inside a staging
    // location. When the file is deleted before it was saved to an entity,
    // remove the directory too. The name alone does not prove this module
    // made the directory, so it must also sit in a configured staging
    // location. rmdir() fails on a directory that still holds files.
    $directory = $this->fileSystem->dirname($file->getFileUri());
    foreach ($this->stagingLocations() as $location) {
      if (StagingLocation::isStagingDirectory($directory, $location)) {
        @$this->fileSystem->rmdir($directory);
        return;
      }
    }
  }

  /**
   * Returns every staging location in use.
   *
   * @return string[]
   *   The global location, or the default when none is set, and every field
   *   level override. Each ends in a slash.
   */
  private function stagingLocations(): array {
    $locations = [
      StagingLocation::directory($this->configFactory->get('filefield_paths.settings')->get('temp_location')),
    ];
    foreach ($this->entityTypeManager->getStorage('field_config')->loadMultiple() as $field) {
      $override = $field->getThirdPartySetting('filefield_paths', 'temp_location');
      if (is_string($override) && $override !== '') {
        $locations[] = StagingLocation::directory($override);
      }
    }
    return array_values(array_unique($locations));
  }

}
