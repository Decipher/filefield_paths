<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filefield_paths\Batch\BatchUpdaterInterface;

/**
 * Tests the Batch Updater service.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Batch\Updater
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class BatchUpdaterTest extends KernelTestBase {

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
   * The batch updater under test.
   */
  protected BatchUpdaterInterface $updater;

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

    $this->updater = $this->container->get(BatchUpdaterInterface::class);
  }

  /**
   * Creates a permanent file at the given URI.
   */
  protected function createFile(string $uri): File {
    $file_system = $this->container->get('file_system');
    $directory = dirname($uri);
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents($uri, 'contents');
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Tests that batchUpdate() returns FALSE when no entities have files.
   */
  public function testReturnsFalseWhenNoEntitiesWithFiles(): void {
    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $result = $this->updater->batchUpdate($field_config);
    $this->assertFalse($result);
  }

  /**
   * Tests that batchUpdate() returns TRUE and sets a batch when entities exist.
   */
  public function testReturnsTrueAndSetsBatchWhenEntitiesExist(): void {
    $file = $this->createFile('public://test/example.txt');
    EntityTest::create(['field_file' => [['target_id' => $file->id()]]])->save();

    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $result = $this->updater->batchUpdate($field_config);
    $this->assertTrue($result);
  }

  /**
   * Tests batchProcess() processes entities and initializes the sandbox.
   */
  public function testBatchProcessWithEntities(): void {
    $file = $this->createFile('public://batch/example.txt');
    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->save();

    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $context = [];
    $this->updater->batchProcess([(int) $entity->id()], $field_config, $context);

    $this->assertSame(1, $context['sandbox']['progress']);
    $this->assertSame(1, $context['sandbox']['max']);
    $this->assertSame([], $context['sandbox']['objects']);
    $this->assertArrayNotHasKey('finished', $context);
  }

  /**
   * Tests batchProcess() sets $context['finished'] when not all processed.
   */
  public function testBatchProcessSetsFinishedWhenIncomplete(): void {
    $file = $this->createFile('public://batch-finished/example.txt');
    $entity_ids = [];
    for ($i = 0; $i < 6; $i++) {
      $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
      $entity->save();
      $entity_ids[] = (int) $entity->id();
    }

    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $context = [];
    $this->updater->batchProcess($entity_ids, $field_config, $context);

    $this->assertSame(5, $context['sandbox']['progress']);
    $this->assertSame(6, $context['sandbox']['max']);
    $this->assertCount(1, $context['sandbox']['objects']);
    $this->assertSame(5 / 6, $context['finished']);
  }

}
