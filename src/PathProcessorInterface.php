<?php

namespace Drupal\filefield_paths;

/**
 * Defines an interface for path processors.
 */
interface PathProcessorInterface {

  /**
   * Processes and cleans strings.
   *
   * @param string $value
   *   The string to clean, can contain tokens.
   * @param array $data
   *   An array of keyed objects. This data is passed to the token service when
   *   replacing tokens. See \Drupal\Core\Utility\Token::replace() for more
   *   information.
   * @param array $settings
   *   (optional) A keyed array of settings to control the cleanup process.
   *   Supported options are:
   *   - transliterate: A boolean flag indicating that non-roman characters
   *     should be replaced.
   *   - pathauto: A boolean flag indicating that the string should be cleaned
   *     using Pathauto's Alias cleaner service.
   *   - slashes: A boolean flag indicating that any slashes should be removed
   *     from the string.
   *
   * @return string
   *   The cleaned string, in which tokens are replaced and other alterations
   *   may have been applied, depending on the settings.
   */
  public function processString(string $value, array $data, array $settings = []): string;

}
