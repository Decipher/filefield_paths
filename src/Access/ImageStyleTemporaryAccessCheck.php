<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
    $file = (string) ($request->query->get('file') ?? '');
    if ($file === '') {
      return AccessResult::forbidden();
    }

    $temp_location = $this->configFactory
      ->get('filefield_paths.settings')
      ->get('temp_location') ?? '';

    $subdir = StreamWrapperManager::getTarget($temp_location);
    if (!is_string($subdir) || $subdir === '') {
      return AccessResult::forbidden();
    }

    $allowed = str_starts_with($file, $subdir . '/');

    return ($allowed ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheContexts(['url.query_args:file'])
      ->addCacheTags(['config:filefield_paths.settings']);
  }

}
