<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;

/**
 * File relate hook implementations.
 */
final class File {

  use StringTranslationTrait;

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
    // Store the original filename in the database.
    if (
      isset($file->origname, $file->filename) &&
      $file->origname->isEmpty() &&
      !$file->filename->isEmpty()
    ) {
      $file->origname = $file->filename;
    }
  }

}
