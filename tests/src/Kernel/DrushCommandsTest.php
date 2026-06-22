<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filefield_paths\Drush\Commands\Commands;

/**
 * Tests the Drush integration.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Drush\Commands\Commands
 */
#[Group('filefield_paths')]
class DrushCommandsTest extends KernelTestBase {

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
   * The Drush commands instance.
   */
  protected Commands $commands;

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
      'label' => 'Test File',
    ])->save();

    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->commands = new Commands(
      $this->container->get('filefield_paths.batch.updater'),
      $this->container->get('entity_field.manager'),
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Tests that buildInfo() discovers file fields with correct structure.
   */
  public function testBuildInfoDiscoversFileFields(): void {
    $info = $this->callProtected('buildInfo');

    $this->assertArrayHasKey('entity_test', $info);
    $this->assertStringEndsWith('(entity_test)', $info['entity_test']['#label']);
    $this->assertArrayHasKey('entity_test', $info['entity_test']);
    $this->assertStringEndsWith('(entity_test)', $info['entity_test']['entity_test']['#label']);
    $this->assertSame('Test File (field_file)', $info['entity_test']['entity_test']['field_file']);
  }

  /**
   * Tests that non-file fields are excluded from buildInfo().
   */
  public function testBuildInfoExcludesNonFileFields(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_label',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_label',
      'bundle' => 'entity_test',
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $info = $this->callProtected('buildInfo');

    $this->assertArrayNotHasKey('field_label', $info['entity_test']['entity_test']);
    $this->assertArrayHasKey('field_file', $info['entity_test']['entity_test']);
  }

  /**
   * Tests the --all path processes all entity types without error.
   *
   * No entities with files exist, so batchUpdate() returns FALSE and the
   * Drush-specific batch processing is never invoked.
   */
  public function testUpdateAllEntityTypesWithNoEntities(): void {
    $this->commands->updateFileFieldPaths(NULL, NULL, NULL, ['all' => TRUE]);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests the direct field path with specific arguments.
   */
  public function testProcessSpecificFieldWithNoEntities(): void {
    $this->commands->updateFileFieldPaths('entity_test', 'entity_test', 'field_file');
    $this->addToAssertionCount(1);
  }

  /**
   * Tests processAllEntityBundles iterates bundles without error.
   */
  public function testProcessAllEntityBundlesWithNoEntities(): void {
    $info = $this->callProtected('buildInfo');
    $this->callProtected('processAllEntityBundles', [$info, 'entity_test']);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests processAllBundleFields iterates fields without error.
   */
  public function testProcessAllBundleFieldsWithNoEntities(): void {
    $info = $this->callProtected('buildInfo');
    $this->callProtected('processAllBundleFields', [$info, 'entity_test', 'entity_test']);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests processField skips a non-existent field gracefully.
   */
  public function testProcessFieldWithNonExistentField(): void {
    $this->callProtected('processField', ['entity_test', 'entity_test', 'non_existent_field']);
    $this->addToAssertionCount(1);
  }

  /**
   * Calls a protected method on the Commands instance via reflection.
   */
  protected function callProtected(string $method, array $args = []): mixed {
    $ref = new \ReflectionMethod($this->commands, $method);
    return $ref->invokeArgs($this->commands, $args);
  }

}
