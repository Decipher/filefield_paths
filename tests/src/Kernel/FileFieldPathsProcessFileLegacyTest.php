<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy;

/**
 * Tests the legacy hook_filefield_paths_process_file() implementation.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
class FileFieldPathsProcessFileLegacyTest extends KernelTestBase {

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

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Creates a permanent file at the given public:// URI with the given body.
   */
  protected function createFile(string $uri, string $body = 'contents'): File {
    $file_system = $this->container->get('file_system');
    $directory = dirname($uri);
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents($uri, $body);
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Moving a file out of a directory removes the now-empty old directory.
   */
  public function testMoveRemovesEmptyOldDirectory(): void {
    $file = $this->createFile('public://old-dir/old-sub/example.txt');
    $this->assertDirectoryExists('public://old-dir/old-sub');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);

    $settings = [
      'file_path' => ['value' => 'new-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $entity->field_file, $settings);

    $this->assertFileExists('public://new-dir/example.txt');
    $this->assertDirectoryDoesNotExist('public://old-dir/old-sub');
    $this->assertDirectoryDoesNotExist('public://old-dir');
  }

  /**
   * A non-empty old directory is left in place after the move.
   */
  public function testMoveLeavesNonEmptyOldDirectoryInPlace(): void {
    $file = $this->createFile('public://shared-dir/example.txt');
    file_put_contents('public://shared-dir/sibling.txt', 'kept');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);

    $settings = [
      'file_path' => ['value' => 'new-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $entity->field_file, $settings);

    $this->assertFileExists('public://new-dir/example.txt');
    $this->assertFileExists('public://shared-dir/sibling.txt');
  }

  /**
   * A file already at its destination is left untouched.
   */
  public function testFileAlreadyAtDestinationIsSkipped(): void {
    $file = $this->createFile('public://same-dir/example.txt');
    $original_changed = $file->getChangedTime();

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);

    $settings = [
      'file_path' => ['value' => 'same-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $entity->field_file, $settings);

    $this->assertSame($original_changed, $file->getChangedTime());
    $this->assertFileExists('public://same-dir/example.txt');
  }

  /**
   * Returns the hook service under test.
   */
  protected function getService(): FileFieldPathsProcessFileLegacy {
    return $this->container->get(FileFieldPathsProcessFileLegacy::class);
  }

}
