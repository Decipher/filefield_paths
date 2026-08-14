<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;

/**
 * File URL and download hook implementations.
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

    $subdir = StreamWrapperManager::getTarget($temp_location);
    if (is_string($subdir) && $subdir !== ''
      && preg_match('#^temporary://styles/([^/]+)/temporary/(.+)$#', $uri, $m)
      && str_starts_with($m[2], $subdir . '/')
    ) {
      $uri = Url::fromRoute(
        'filefield_paths.image_style_temporary',
        ['image_style' => $m[1]],
        ['query' => ['file' => $m[2]], 'absolute' => TRUE],
      )->toString();
    }
  }

  /**
   * Implements hook_file_download().
   *
   * Grants access to source files staged inside the configured FFP
   * temporary:// subdirectory so that ImageStyleDownloadController can
   * generate and serve derivatives.
   *
   * Drupal 11.4 removed the token-valid shortcut that previously treated
   * temporary:// as a public scheme during image style delivery. The
   * controller now always invokes hook_file_download() for non-public
   * schemes, so without an explicit grant here, derivative requests for
   * temporary:// staged files receive a 403.
   *
   * Security: the image style derivative token (itok) is validated by the
   * controller before this hook runs, and the route access checker
   * (ImageStyleTemporaryAccessCheck) already restricted the request to the
   * FFP temp subdirectory. This hook only needs to confirm the source URI
   * itself lives within that subdirectory.
   *
   * Only the Content-Disposition header is returned: the controller sets
   * Content-Type and Content-Length from the generated derivative via array
   * union, and returning those from the source would mask derivative MIME
   * types for converting image styles.
   *
   * @return array<string, mixed>|null
   *   A non-empty headers array to grant access, or NULL for no opinion.
   */
  #[Hook('file_download')]
  public function fileDownload(string $uri): array|null {
    if (StreamWrapperManager::getScheme($uri) !== 'temporary') {
      return NULL;
    }

    $temp_location = $this->configFactory
      ->get('filefield_paths.settings')
      ->get('temp_location') ?? '';

    if (StreamWrapperManager::getScheme($temp_location) !== 'temporary') {
      return NULL;
    }

    $subdir = StreamWrapperManager::getTarget($temp_location);
    $target = StreamWrapperManager::getTarget($uri);
    if (!is_string($subdir) || !is_string($target)) {
      return NULL;
    }

    $subdir = trim(str_replace('\\', '/', $subdir), '/');
    $target = ltrim(str_replace('\\', '/', $target), '/');
    if ($subdir === ''
      || preg_match('~(^|/)\.\.(/|$)~', $target)
      || !str_starts_with($target, $subdir . '/')
    ) {
      return NULL;
    }

    return ['Content-Disposition' => 'inline'];
  }

}
