<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * File URL hook implementations.
 */
final class FileUrlHooks {

  /**
   * Implements hook_file_url_alter().
   */
  // @phpstan-ignore-next-line
  #[Hook('file_url_alter')]
  public function fileUrlAlter(string &$uri): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    if (preg_match('#^temporary://styles/([^/]+)/temporary/(.+)$#', $uri, $m)) {
      $uri = Url::fromRoute(
        'filefield_paths.image_style_temporary',
        ['image_style' => $m[1]],
        ['query' => ['file' => $m[2]], 'absolute' => TRUE],
      )->toString();
    }
  }

}
