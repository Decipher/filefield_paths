<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the Redirect service against real stream wrappers and entities.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Redirect
 */
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
   * Documents that the existence pre-check does not prevent duplicates.
   *
   * Bug (undocumented, found while writing this test): the pre-check hash in
   * Redirect::createRedirect() is generated from the destination path
   * ($parsed_path), but \Drupal\redirect\Entity\Redirect::preSave() always
   * recomputes the stored hash from the *source* path. The two hashes are
   * never the same value for a real redirect, so the existence check almost
   * never matches, and calling createRedirect() twice with an identical
   * source/destination throws an uncaught database unique-constraint
   * exception instead of being silently skipped. This is not yet filed as a
   * Drupal.org issue; recorded here as a baseline for future investigation.
   */
  public function testCreateRedirectTwiceWithSameArgumentsThrows(): void {
    $redirect_service = $this->container->get('filefield_paths.redirect');
    $language = new Language(['id' => 'en']);

    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', $language);

    $this->expectException(EntityStorageException::class);
    $redirect_service->createRedirect('public://old/source.txt', 'public://new/destination.txt', $language);
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
