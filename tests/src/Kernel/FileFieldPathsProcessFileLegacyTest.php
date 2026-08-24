<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
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
#[RunTestsInSeparateProcesses]
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
   * The entity's file object is updated to the moved location.
   *
   * A hook_entity_insert() implementation that runs after this module reads
   * the file object cached on the field item. Focal Point does this. If the
   * object still holds the pre-move URI, that module writes its own data
   * against a path that no longer exists.
   *
   * @see https://www.drupal.org/i/3015137
   */
  public function testMoveUpdatesFileObjectHeldByTheEntity(): void {
    $file = $this->createFile('public://stale-old/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    // Resolve the referenced file before the move. ImageItem::preSave() does
    // this to read the image dimensions, so the field item holds the object
    // before any insert hook runs.
    $referenced = $entity->get('field_file')->entity;
    $this->assertInstanceOf(File::class, $referenced);
    $this->assertSame('public://stale-old/example.txt', $referenced->getFileUri());

    $settings = [
      'file_path' => ['value' => 'stale-new', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);

    $this->assertFileExists('public://stale-new/example.txt');

    // A later hook implementation reads the same cached object.
    $after_move = $entity->get('field_file')->entity;
    $this->assertInstanceOf(File::class, $after_move);
    $this->assertSame('public://stale-new/example.txt', $after_move->getFileUri());
  }

  /**
   * The entity's file object matches the stored record after a rename.
   *
   * The move renames around a file that already holds the destination path, so
   * the stored record can differ from the path that was asked for. The
   * in-memory object must still agree with what was written to the database.
   *
   * @see https://www.drupal.org/i/3015137
   */
  public function testMovedFileObjectMatchesStoredRecordOnRename(): void {
    $file = $this->createFile('public://rename-old/example.txt');
    // Occupy the destination so the move has to rename around it.
    $this->createFile('public://rename-new/example.txt', 'occupied');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    $referenced = $entity->get('field_file')->entity;
    $this->assertInstanceOf(File::class, $referenced);

    $settings = [
      'file_path' => ['value' => 'rename-new', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);

    $stored = $this->container->get('entity_type.manager')
      ->getStorage('file')
      ->loadUnchanged((int) $file->id());
    $this->assertInstanceOf(File::class, $stored);
    $this->assertNotSame('public://rename-old/example.txt', $stored->getFileUri());
    $this->assertSame($stored->getFileUri(), $referenced->getFileUri());
    $this->assertSame($stored->getFilename(), $referenced->getFilename());
  }

  /**
   * The field item is updated even when it holds a different file instance.
   *
   * On a real upload the file object cached on the field item is not always the
   * same instance that referencedEntities() returns during processing. Focal
   * Point reads the field item, so updating only the processed instance is not
   * enough. Here the file static cache is reset after the entity caches its
   * reference, so the two diverge, the way they do behind the widget.
   *
   * @see https://www.drupal.org/i/3015137
   */
  public function testMoveUpdatesFieldItemWhenReferencedInstanceDiffers(): void {
    $file = $this->createFile('public://divergent-old/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    // Cache the field item's reference, as ImageItem::preSave() does.
    $cached = $entity->get('field_file')->entity;
    $this->assertInstanceOf(File::class, $cached);
    $this->assertSame('public://divergent-old/example.txt', $cached->getFileUri());

    // Reset the file cache so processing loads a different File instance.
    $this->container->get('entity_type.manager')->getStorage('file')->resetCache([(int) $file->id()]);

    $settings = [
      'file_path' => ['value' => 'divergent-new', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
    ];
    $this->getService()->fileFieldPathsProcessFile($entity, $field, $settings);

    $this->assertFileExists('public://divergent-new/example.txt');

    // What a later hook reads from the field item must be the moved file.
    $after_move = $entity->get('field_file')->entity;
    $this->assertInstanceOf(File::class, $after_move);
    $this->assertSame('public://divergent-new/example.txt', $after_move->getFileUri());
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
