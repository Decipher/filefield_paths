<?php

declare(strict_types=1);

namespace Drupal\filefield_paths;

use Drupal\Core\Language\LanguageInterface;

/**
 * Defines a service for creating file redirects.
 */
interface RedirectInterface {

  /**
   * Creates a redirect for a moved File field.
   *
   * @param string $source
   *   The source file URL.
   * @param string $path
   *   The moved file URL.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language of the source file.
   */
  public function createRedirect($source, $path, LanguageInterface $language);

}
