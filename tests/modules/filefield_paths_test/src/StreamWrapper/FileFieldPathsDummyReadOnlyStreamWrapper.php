<?php

declare(strict_types=1);

namespace Drupal\filefield_paths_test\StreamWrapper;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file_test\StreamWrapper\DummyReadOnlyStreamWrapper;

/**
 * Helper class for testing the stream wrapper registry.
 *
 * Dummy stream wrapper implementation (ffp-dummy-readonly://).
 */
class FileFieldPathsDummyReadOnlyStreamWrapper extends DummyReadOnlyStreamWrapper {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('File (Field) Paths Dummy files (readonly)');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Dummy wrapper for File (Field) Paths simpletest (readonly).');
  }

  /**
   * Override getExternalUrl().
   *
   * Return the HTML URI of a public file.
   */
  public function getExternalUrl(): string {
    $path = str_replace('\\', '/', (string) $this->getTarget());

    return PublicStream::baseUrl() . '/' . UrlHelper::encodePath($path);
  }

}
