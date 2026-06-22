<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy;
use Drupal\filefield_paths\PathProcessorInterface;
use Drupal\filefield_paths\RedirectInterface;

/**
 * Tests the legacy hook_filefield_paths_process_file() implementation.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
#[Group('filefield_paths')]
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
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'new-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);

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
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'new-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);

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
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'same-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);

    $this->assertSame($original_changed, $file->getChangedTime());
    $this->assertFileExists('public://same-dir/example.txt');
  }

  /**
   * Returns the hook service under test.
   */
  protected function getService(): FileFieldPathsProcessFileLegacy {
    return $this->container->get(FileFieldPathsProcessFileLegacy::class);
  }

  /**
   * Constructs the service with optional dependency overrides.
   */
  protected function constructService(?FileSystemInterface $fileSystem = NULL, ?FileRepositoryInterface $fileRepository = NULL): FileFieldPathsProcessFileLegacy {
    return new FileFieldPathsProcessFileLegacy(
      $fileSystem ?? $this->container->get('file_system'),
      $fileRepository ?? $this->container->get('file.repository'),
      $this->container->get('stream_wrapper_manager'),
      $this->container->get('module_handler'),
      $this->container->get('config.factory'),
      $this->container->get(PathProcessorInterface::class),
      $this->container->get('logger.channel.filefield_paths'),
      fn (): RedirectInterface => $this->container->get(RedirectInterface::class),
    );
  }

  /**
   * A file with an unexpected source scheme is silently skipped.
   */
  public function testUnexpectedSourceSchemeSkipped(): void {
    $file = File::create(['uri' => 'bogus://dir/file.txt']);
    $file->setPermanent();
    $file->save();

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    \assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'new-dir', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];

    // Should skip the file without error.
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);
    $this->addToAssertionCount(1);
  }

  /**
   * Directory creation failure logs a notice and continues.
   */
  public function testDirectoryCreationFailure(): void {
    $file = $this->createFile('public://dir-fail/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    \assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'new-dir-fail', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];

    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileSystem->method('dirname')->willReturn('public://new-dir-fail');
    $fileSystem->method('prepareDirectory')->willReturn(FALSE);

    $service = $this->constructService($fileSystem);
    $service->fileFieldPathsProcessFile($entity, $field, $settings);

    $this->assertFileExists('public://dir-fail/example.txt');
  }

  /**
   * A move exception is caught, logged, and the file stays in place.
   */
  public function testMoveException(): void {
    $file = $this->createFile('public://move-fail/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    \assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'new-move-fail', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];

    $fileRepository = $this->createMock(FileRepositoryInterface::class);
    $fileRepository->method('move')->willThrowException(new \Exception('Move failed'));

    $service = $this->constructService(NULL, $fileRepository);
    $service->fileFieldPathsProcessFile($entity, $field, $settings);

    $this->assertFileExists('public://move-fail/example.txt');
  }

}
