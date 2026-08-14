<?php

declare(strict_types=1);

namespace Drupal\filefield_paths;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for creating file redirects.
 */
class Redirect implements RedirectInterface {

  /**
   * Constructs a new redirect service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected StreamWrapperManagerInterface $streamWrapperManager, protected ConfigFactoryInterface $configFactory, protected LoggerInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public function createRedirect($source, $path, LanguageInterface $language): void {
    $this->logger->debug('Creating redirect from @source to @path.', [
      '@source' => $source,
      '@path'   => $path,
    ]);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('redirect');

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $storage->create([]);

    $parsed_source = $this->getPath($source);
    $parsed_path = $this->getPath($path);

    $redirect->setSource($parsed_source);
    $redirect->setRedirect($parsed_path);
    $redirect->setStatusCode($this->configFactory->get('redirect.settings')->get('default_status_code'));

    // Check if the redirect doesn't already exist before saving.
    $hash = $redirect->generateHash($parsed_path, [], $language->getId());
    $redirects = $storage->loadByProperties(['hash' => $hash]);
    if (empty($redirects)) {
      // Redirect does not exist yet, save as new one.
      $redirect->save();
    }
  }

  /**
   * Returns the path to the file, starting from the Drupal root.
   *
   * For public:// this is a directory path relative to the Drupal root, which
   * Redirect::setRedirect() turns into an internal path. Other schemes
   * (e.g. private://, temporary://) are not served directly from their
   * directory path, so the wrapper's own external URL is used instead to
   * resolve the route Drupal actually serves the file through.
   *
   * @param string $file_uri
   *   The file url to get the path for.
   *
   * @return string|null
   *   The file path, if found. Null otherwise.
   */
  protected function getPath($file_uri): ?string {
    $wrapper = $this->streamWrapperManager->getViaUri($file_uri);
    if (!$wrapper instanceof StreamWrapperInterface) {
      return NULL;
    }
    if (StreamWrapperManager::getScheme($file_uri) === 'public') {
      return $wrapper->getDirectoryPath() . '/' . StreamWrapperManager::getTarget($file_uri);
    }
    return $wrapper->getExternalUrl();
  }

}
