<?php

declare(strict_types=1);

namespace Drupal\filefield_paths;

use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Resolves the directory uploads are staged in.
 *
 * Every upload is staged in a directory of its own inside the staging
 * location, so two files with one name never share a staged path. The widget
 * builds that directory, and the file delete hook removes it once it is
 * empty. Both must agree on what the staging location is.
 */
final class StagingLocation {

  /**
   * The staging location when none is configured.
   *
   * Core stages an upload with no destination at the root of the temporary
   * scheme. Do the same, so a site whose settings lack a location still
   * works.
   */
  public const DEFAULT = 'temporary://';

  /**
   * The prefix of a staging directory name.
   */
  public const PREFIX = 'ffp-';

  /**
   * Normalises a configured staging location to a directory URI.
   *
   * @param mixed $location
   *   The configured value. It may be unset or empty.
   *
   * @return string
   *   The location with exactly one trailing slash, for example
   *   "temporary://filefield_paths/", or "temporary://" for a bare scheme
   *   root. The default when the value is not a URI with a scheme.
   */
  public static function directory(mixed $location): string {
    if (!is_string($location) || $location === '') {
      return self::DEFAULT;
    }
    $scheme = StreamWrapperManager::getScheme($location);
    if ($scheme === FALSE) {
      return self::DEFAULT;
    }
    $target = trim((string) StreamWrapperManager::getTarget($location), '/');
    return $target === '' ? $scheme . '://' : $scheme . '://' . $target . '/';
  }

  /**
   * Checks whether a directory is a staging directory inside a location.
   *
   * @param string $directory
   *   The directory URI, without a trailing slash.
   * @param string $location
   *   A staging location as returned by directory().
   *
   * @return bool
   *   TRUE if the directory sits directly inside the location and carries a
   *   staging directory name.
   */
  public static function isStagingDirectory(string $directory, string $location): bool {
    if (!str_starts_with($directory, $location)) {
      return FALSE;
    }
    return (bool) preg_match('#^' . preg_quote(self::PREFIX, '#') . '[A-Za-z0-9_-]+$#', substr($directory, strlen($location)));
  }

}
