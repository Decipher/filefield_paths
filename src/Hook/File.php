<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileInterface;

/**
 * File relate hook implementations.
 */
final class File {

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
