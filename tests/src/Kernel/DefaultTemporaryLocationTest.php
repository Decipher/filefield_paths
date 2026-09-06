<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the temporary location a fresh install starts with.
 *
 * The staged upload directory must never sit under public://. The shipped
 * default and the installed value are both checked, so neither can drift
 * back on its own.
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class DefaultTemporaryLocationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file'];

  /**
   * The temporary location a fresh install must use.
   */
  private const SECURE_DEFAULT = 'temporary://filefield_paths';

  /**
   * Tests that the shipped default config is not under public://.
   */
  public function testShippedDefaultIsNotPublic(): void {
    $path = $this->container->get('extension.list.module')
      ->getPath('filefield_paths') . '/config/install';
    $shipped = (new FileStorage($path))->read('filefield_paths.settings');

    $this->assertIsArray($shipped);
    $this->assertSame(self::SECURE_DEFAULT, $shipped['temp_location']);
  }

  /**
   * Tests that a real install ends with the secure temporary location.
   */
  public function testInstallEndsWithSecureLocation(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    $this->container->get('module_installer')->install(['filefield_paths']);

    $this->assertSame(self::SECURE_DEFAULT, $this->config('filefield_paths.settings')->get('temp_location'));
  }

}
