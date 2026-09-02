<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;

/**
 * Tests the removal of empty staging directories on file delete.
 *
 * Every upload is staged in a directory of its own. A file that is never
 * saved to an entity is deleted by cron, and its directory must go with it.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\File
 * @see https://www.drupal.org/i/3277844
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class StagingDirectoryCleanupTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'entity_test',
    'filefield_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filefield_paths']);
  }

  /**
   * An empty staging directory is removed with its last file.
   */
  public function testEmptyStagingDirectoryIsRemoved(): void {
    $file = $this->createFile('public://filefield_paths/ffp-abc123/example.txt');

    $file->delete();

    $this->assertDirectoryDoesNotExist('public://filefield_paths/ffp-abc123');
    $this->assertDirectoryExists('public://filefield_paths');
  }

  /**
   * A staging directory that still holds a file is kept.
   */
  public function testStagingDirectoryWithFilesIsKept(): void {
    $file = $this->createFile('public://filefield_paths/ffp-abc123/example.txt');
    $this->createFile('public://filefield_paths/ffp-abc123/other.txt');

    $file->delete();

    $this->assertFileExists('public://filefield_paths/ffp-abc123/other.txt');
  }

  /**
   * A directory the module did not create is left alone, even when empty.
   */
  public function testOtherDirectoriesAreKept(): void {
    $file = $this->createFile('public://other/example.txt');

    $file->delete();

    $this->assertFileDoesNotExist('public://other/example.txt');
    $this->assertDirectoryExists('public://other');
  }

  /**
   * A staging name outside every staging location proves nothing.
   *
   * Another module or a site can name a directory the same way. Only a
   * directory inside a configured staging location belongs to this module.
   */
  public function testStagingNameOutsideTheStagingLocationIsKept(): void {
    $file = $this->createFile('public://other/ffp-abc123/example.txt');

    $file->delete();

    $this->assertFileDoesNotExist('public://other/ffp-abc123/example.txt');
    $this->assertDirectoryExists('public://other/ffp-abc123');
  }

  /**
   * A field can stage its uploads in a location of its own.
   */
  public function testFieldStagingDirectoryIsRemoved(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'third_party_settings' => [
        'filefield_paths' => ['temp_location' => 'public://custom-staging'],
      ],
    ])->save();
    $file = $this->createFile('public://custom-staging/ffp-abc123/example.txt');

    $file->delete();

    $this->assertDirectoryDoesNotExist('public://custom-staging/ffp-abc123');
    $this->assertDirectoryExists('public://custom-staging');
  }

  /**
   * Writes a file to disk and saves a temporary file entity for it.
   */
  private function createFile(string $uri): File {
    $file_system = $this->container->get('file_system');
    $directory = $file_system->dirname($uri);
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($uri, 'contents');
    $file = File::create(['uri' => $uri]);
    $file->setTemporary();
    $file->save();
    return $file;
  }

}
