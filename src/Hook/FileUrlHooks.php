<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;

/**
 * File URL hook implementations.
 */
final readonly class FileUrlHooks {

  public function __construct(
    private ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_file_url_alter().
   *
   * Rewrites image style derivative URLs for files staged in temporary:// so
   * that they route through a dedicated delivery controller instead of the
   * core temporary stream wrapper (which cannot serve image derivatives).
   */
  // @phpstan-ignore-next-line
  #[Hook('file_url_alter')]
  public function fileUrlAlter(string &$uri): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    $temp_location = $this->configFactory
      ->get('filefield_paths.settings')
      ->get('temp_location') ?? '';

    if (StreamWrapperManager::getScheme($temp_location) !== 'temporary') {
      return;
    }

    if (preg_match('#^temporary://styles/([^/]+)/temporary/(.+)$#', $uri, $m)) {
      $uri = Url::fromRoute(
        'filefield_paths.image_style_temporary',
        ['image_style' => $m[1]],
        ['query' => ['file' => $m[2]], 'absolute' => TRUE],
      )->toString();
    }
  }

}
