<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Redirect service against real stream wrappers and entities.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Redirect
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class RedirectTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'path_alias',
    'redirect',
    'link',
    'filefield_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('redirect');
    $this->installEntitySchema('path_alias');
    $this->config('redirect.settings')->set('default_status_code', 301)->save();
    $this->setSetting('file_private_path', 'private_files');
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Core only registers the private:// stream wrapper if a file path is
    // set at container-build time; register it directly so the private://
    // scheme resolves in this test regardless of when settings are altered.
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * Tests that a redirect is created with the public:// scheme.
   */
  public function testCreateRedirectForPublicScheme(): void {
    /** @var \Drupal\filefield_paths\RedirectInterface $redirect_service */
    $redirect_service = $this->container->get('filefield_paths.redirect');

    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', new Language(['id' => 'en']));

    $redirects = $this->container->get('entity_type.manager')->getStorage('redirect')->loadMultiple();
    $this->assertCount(1, $redirects);
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = reset($redirects);
    $this->assertStringEndsWith('old/source.txt', $redirect->getSourceUrl());
    $this->assertStringEndsWith('new/destination.txt', $redirect->getRedirectUrl()->toUriString());
  }

  /**
   * Tests that duplicate redirects are silently skipped, not thrown.
   *
   * Until #3045063 was fixed, the pre-check hashed the destination path while
   * Redirect::preSave() hashed the source, so the two values never matched
   * and a second identical call crashed on the redirect table's unique hash
   * index instead of being skipped.
   *
   * @see https://www.drupal.org/i/3045063
   */
  public function testCreateRedirectTwiceWithSameArgumentsDoesNotThrow(): void {
    /** @var \Drupal\filefield_paths\RedirectInterface $redirect_service */
    $redirect_service = $this->container->get('filefield_paths.redirect');
    $language = new Language(['id' => 'en']);

    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', $language);

    // Second call with identical arguments should be silently skipped,
    // not throw a database unique-constraint exception.
    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', $language);

    $redirects = $this->container->get('entity_type.manager')->getStorage('redirect')->loadMultiple();
    $this->assertCount(1, $redirects, 'Duplicate redirect should be skipped, not stored twice.');
  }

  /**
   * The same skip happens when the file's language is not the site default.
   *
   * The stored hash is built in Redirect::preSave() from the entity's own
   * language. Until #3045063 was fixed the pre-check used the language passed
   * in by the caller, which is the file's and often not the one the entity
   * carries, so the two hashes disagreed and the duplicate was missed.
   *
   * @see https://www.drupal.org/i/3045063
   */
  public function testCreateRedirectTwiceWithNonDefaultLanguageDoesNotThrow(): void {
    /** @var \Drupal\filefield_paths\RedirectInterface $redirect_service */
    $redirect_service = $this->container->get('filefield_paths.redirect');
    $language = new Language(['id' => 'de']);

    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', $language);
    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', $language);

    $redirects = $this->container->get('entity_type.manager')->getStorage('redirect')->loadMultiple();
    $this->assertCount(1, $redirects, 'Duplicate redirect should be skipped for a non-default language too.');
  }

  /**
   * Tests that a private:// destination produces a web-accessible redirect.
   *
   * @see https://www.drupal.org/project/filefield_paths/issues/3269636
   *
   * Bug: Redirect::getPath() calls LocalStream::getDirectoryPath(), which for
   * private:// returns the raw filesystem base path (e.g. "private_files/...")
   * rather than the web-accessible "/system/files/..." route built by
   * PrivateStream::getExternalUrl(). This test currently fails against
   * src/Redirect.php and documents the expected (correct) behaviour; it
   * should start passing once #3269636 is fixed.
   */
  public function testCreateRedirectForPrivateSchemeUsesWebAccessiblePath(): void {
    /** @var \Drupal\filefield_paths\RedirectInterface $redirect_service */
    $redirect_service = $this->container->get('filefield_paths.redirect');

    $redirect_service->createRedirect('public://old/source.txt', 'private://new/destination.txt', new Language(['id' => 'en']));

    $redirects = $this->container->get('entity_type.manager')->getStorage('redirect')->loadMultiple();
    $this->assertCount(1, $redirects);
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = reset($redirects);

    // Expected: the redirect destination resolves to the Drupal-managed
    // private file download route, not the raw filesystem path.
    $this->assertStringContainsString('/system/files/new/destination.txt', $redirect->getRedirectUrl()->toUriString());
  }

}
