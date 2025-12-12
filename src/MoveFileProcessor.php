<?php

namespace Drupal\filefield_paths;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Processes file operations and manages moving files across different schemes.
 */
class MoveFileProcessor implements MoveFileProcessorInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   */
  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
    #[Autowire(service: 'cache.static')]
    protected readonly CacheBackendInterface $cacheBackend,
  ) {}

  /**
   * Get the recommended file scheme based on which file systems are enabled.
   *
   * @return string
   *   The recommended file scheme.
   */
  public function recommendedTemporaryScheme(): string {
    $cache = $this->cacheBackend->get(self::TEMP_SCHEME_CID);
    if (isset($cache->data)) {
      return $cache->data;
    }
    $wrappers = $this->streamWrapperManager->getWrappers();
    $cache_data = $this->detectRecommendedScheme($wrappers);
    $this->cacheBackend->set(self::TEMP_SCHEME_CID, $cache_data);
    return $cache_data;
  }

  /**
   * Detect the recommended file scheme based on which file systems are enabled.
   *
   * @param array $wrappers
   *   The stream wrappers.
   *
   * @return string
   *   The recommended file scheme.
   */
  public function detectRecommendedScheme(array $wrappers): string {
    $recommended = 'public://';
    foreach (['temporary', 'private'] as $scheme) {
      if (isset($wrappers[$scheme])) {
        $path = $scheme . '://';
        if ($this->isWritable($path)) {
          $recommended = $path;
          break;
        }
      }
    }
    return $recommended;
  }

  /**
   * Check if a path is writable.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if the path is writable, FALSE otherwise.
   */
  protected function isWritable(string $path): bool {
    return is_writable($path);
  }

}
