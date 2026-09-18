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

    $parsed_source = $this->getSource($source);
    if ($parsed_source === NULL) {
      $this->logger->warning('No redirect was created from @source, because its scheme has no stream wrapper.', ['@source' => $source]);
      return;
    }
    $parsed_path = $this->getPath($path);

    $redirect->setSource($parsed_source['path'], $parsed_source['query']);
    $redirect->setRedirect($parsed_path);
    $redirect->setStatusCode($this->configFactory->get('redirect.settings')->get('default_status_code'));
    // Redirect::preSave() builds the stored hash from the source path and the
    // entity's own language. Read the language back off the entity so the
    // check below asks for the hash the save will actually produce, rather
    // than one built from a language the entity does not carry.
    $hash = $redirect->generateHash(ltrim($parsed_source['path'], '/'), $parsed_source['query'], $redirect->get('language')->value);
    $redirects = $storage->loadByProperties(['hash' => $hash]);
    if (empty($redirects)) {
      // Redirect does not exist yet, save as new one.
      $redirect->save();
    }
  }

  /**
   * Returns the source path and query for a file URI.
   *
   * The redirect module compares a stored source against the request path,
   * relative to the site root. For public:// that is the directory path plus
   * the file target. Other schemes serve files through a route, and the
   * wrapper only exposes that route as an absolute external URL. Take the
   * path and the query from that URL and drop the base path. Stored as a
   * source, the absolute URL itself could never match a request.
   *
   * The path keeps the encoding the wrapper produced. The redirect module
   * matches a private file request on the raw request path.
   *
   * @param string $file_uri
   *   The file URI.
   *
   * @return array{path: string, query: array<int|string, mixed>}|null
   *   The site-relative path and the query, or NULL if the scheme has no
   *   stream wrapper.
   */
  protected function getSource(string $file_uri): ?array {
    $wrapper = $this->streamWrapperManager->getViaUri($file_uri);
    if (!$wrapper instanceof StreamWrapperInterface) {
      return NULL;
    }
    if (StreamWrapperManager::getScheme($file_uri) === 'public') {
      return [
        'path' => $wrapper->getDirectoryPath() . '/' . StreamWrapperManager::getTarget($file_uri),
        'query' => [],
      ];
    }
    $url = parse_url($wrapper->getExternalUrl()) ?: [];
    $path = (string) ($url['path'] ?? '');
    $base_path = rtrim(base_path(), '/');
    if ($base_path !== '' && str_starts_with($path, $base_path . '/')) {
      $path = substr($path, strlen($base_path));
    }
    $query = [];
    parse_str((string) ($url['query'] ?? ''), $query);
    return ['path' => ltrim($path, '/'), 'query' => $query];
  }

  /**
   * Returns the path to redirect to, starting from the Drupal root.
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
