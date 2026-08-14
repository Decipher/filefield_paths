<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Restricts the temporary image style route to the FFP staging subdirectory.
 *
 * Prevents the route from serving derivatives for arbitrary temporary:// files
 * outside the configured File (Field) Paths temp location.
 */
final readonly class ImageStyleTemporaryAccessCheck implements AccessInterface {

  public function __construct(
    private ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Checks access for the temporary image style delivery route.
   */
  public function access(Request $request): AccessResultInterface {
    $cacheability = (new CacheableMetadata())
      ->setCacheContexts(['url.query_args:file'])
      ->setCacheTags(['config:filefield_paths.settings']);

    $file = (string) ($request->query->get('file') ?? '');
    if ($file === '') {
      return AccessResult::forbidden()->addCacheableDependency($cacheability);
    }

    $temp_location = $this->configFactory
      ->get('filefield_paths.settings')
      ->get('temp_location') ?? '';

    if (StreamWrapperManager::getScheme($temp_location) !== 'temporary') {
      return AccessResult::forbidden()->addCacheableDependency($cacheability);
    }

    $subdir = StreamWrapperManager::getTarget($temp_location);
    if (!is_string($subdir) || $subdir === '') {
      return AccessResult::forbidden()->addCacheableDependency($cacheability);
    }

    $normalized_file = ltrim(str_replace('\\', '/', $file), '/');
    if (preg_match('~(^|/)\.\.(/|$)~', $normalized_file)) {
      return AccessResult::forbidden()->addCacheableDependency($cacheability);
    }

    $allowed = str_starts_with($normalized_file, $subdir . '/');
    return AccessResult::allowedIf($allowed)->addCacheableDependency($cacheability);
  }

}
