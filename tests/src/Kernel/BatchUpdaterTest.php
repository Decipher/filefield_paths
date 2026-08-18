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
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the Batch Updater service.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Batch\Updater
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class BatchUpdaterTest extends KernelTestBase {

  use UserCreationTrait;

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
    'dblog',
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
    // The dblog logger writes to this table whenever a file is skipped.
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['filefield_paths']);
    // Build the router so Url::fromRoute() can resolve the dblog route.
    $this->container->get('router.builder')->rebuild();

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    $field_config = FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ]);
    // Turn on File (Field) Paths for the field, so the batch actually
    // processes files instead of leaving them where they are.
    $field_config->setThirdPartySetting('filefield_paths', 'enabled', TRUE);
    $field_config->setThirdPartySetting('filefield_paths', 'file_path', [
      'value' => 'processed',
      'options' => ['transliterate' => FALSE],
    ]);
    $field_config->setThirdPartySetting('filefield_paths', 'file_name', [
      'value' => '',
      'options' => ['transliterate' => FALSE],
    ]);
    $field_config->save();

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

  /**
   * Tests that batchProcess() tallies a file that reaches its destination.
   */
  public function testBatchProcessTalliesUpdatedFiles(): void {
    $file = $this->createFile('public://tally/example.txt');
    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->save();

    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $context = [];
    $this->updater->batchProcess([(int) $entity->id()], $field_config, $context);

    $this->assertSame(1, $context['results']['updated']);
    $this->assertArrayNotHasKey('skipped', $context['results']);
  }

  /**
   * Tests that batchProcess() tallies a file with no valid scheme as skipped.
   */
  public function testBatchProcessTalliesSkippedFiles(): void {
    $file = File::create(['uri' => 'bogus://tally/example.txt']);
    $file->setPermanent();
    $file->save();
    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->save();

    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $context = [];
    $this->updater->batchProcess([(int) $entity->id()], $field_config, $context);

    $this->assertSame(1, $context['results']['skipped']);
    $this->assertArrayNotHasKey('updated', $context['results']);
  }

  /**
   * Tests that a file missing from disk is tallied as skipped.
   *
   * The source and destination schemes are both public://, so a scheme
   * comparison would see no change and count this as updated.
   */
  public function testBatchProcessTalliesMissingSourceFileAsSkipped(): void {
    $file = $this->createFile('public://missing/example.txt');
    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->save();

    // The insert hook has already moved the file, so remove it from
    // wherever it is now. The file record stays and still points at it.
    $file = File::load($file->id());
    $this->container->get('file_system')->delete($file->getFileUri());

    $field_config = FieldConfig::load('entity_test.entity_test.field_file');

    $context = [];
    $this->updater->batchProcess([(int) $entity->id()], $field_config, $context);

    $this->assertSame(1, $context['results']['skipped']);
    $this->assertArrayNotHasKey('updated', $context['results']);
  }

  /**
   * Tests batchFinished() reports an error when the batch was unsuccessful.
   */
  public function testBatchFinishedErrorMessageWhenUnsuccessful(): void {
    $this->updater->batchFinished(FALSE, ['updated' => 2, 'skipped' => 1], []);

    $messages = $this->container->get('messenger')->all();
    $this->assertArrayHasKey('error', $messages);
    $this->assertStringContainsString('The update did not complete.', (string) $messages['error'][0]);
    $this->assertArrayNotHasKey('status', $messages);
    $this->assertArrayNotHasKey('warning', $messages);
  }

  /**
   * Tests batchFinished() reports a status message when nothing was skipped.
   */
  public function testBatchFinishedStatusMessageWhenNoSkips(): void {
    $this->updater->batchFinished(TRUE, ['updated' => 3], []);

    $messages = $this->container->get('messenger')->all();
    $this->assertArrayHasKey('status', $messages);
    $this->assertStringContainsString('Updated 3 files.', (string) $messages['status'][0]);
  }

  /**
   * Tests batchFinished() reports a warning message when files were skipped.
   *
   * The current user cannot see the log, so the message has no link.
   */
  public function testBatchFinishedWarningMessageWhenSkipped(): void {
    $this->updater->batchFinished(TRUE, ['updated' => 2, 'skipped' => 1], []);

    $messages = $this->container->get('messenger')->all();
    $this->assertArrayHasKey('warning', $messages);
    $this->assertStringContainsString('Updated 2 files.', (string) $messages['warning'][0]);
    $this->assertStringContainsString('1 file could not be moved automatically', (string) $messages['warning'][0]);
    $this->assertStringNotContainsString('<a href', (string) $messages['warning'][0]);
  }

  /**
   * Tests the skipped warning links to the log for a user who can read it.
   */
  public function testBatchFinishedWarningLinksToLogWhenPermitted(): void {
    $this->setUpCurrentUser([], ['access site reports']);

    $this->updater->batchFinished(TRUE, ['updated' => 2, 'skipped' => 1], []);

    $messages = $this->container->get('messenger')->all();
    $this->assertArrayHasKey('warning', $messages);
    $message = (string) $messages['warning'][0];
    $this->assertStringContainsString('1 file could not be moved automatically', $message);
    // The link must be real markup, not escaped text.
    $this->assertStringContainsString('<a href="/admin/reports/dblog', $message);
    $this->assertStringNotContainsString('&lt;a href', $message);
  }

}
