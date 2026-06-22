<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;

/**
 * Tests the `filefield_paths_settings` transient entity override.
 *
 * Reproduces https://www.drupal.org/project/filefield_paths/issues/2072237 :
 * exercises real entity saves (rather than mocks) because
 * Drupal\Core\Entity\ContentEntityBase::__set() is what makes assigning an
 * arbitrary `filefield_paths_settings` property safe and transient in the
 * first place.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\EntityWithFileField
 */
#[Group('filefield_paths')]
class EntityWithFileFieldOverrideTest extends KernelTestBase {

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

    foreach (['field_file', 'field_file_two'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => 'file',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'entity_test',
        'field_name' => $field_name,
        'bundle' => 'entity_test',
        'third_party_settings' => [
          'filefield_paths' => [
            'enabled' => TRUE,
            'file_path' => ['value' => 'new-dir', 'options' => ['transliterate' => FALSE]],
            'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
          ],
        ],
      ])->save();
    }
  }

  /**
   * Creates a permanent file at the given public:// URI.
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
   * With no override, a new entity's file is processed as configured.
   */
  public function testNoOverrideProcessesFile(): void {
    $file = $this->createFile('public://original/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->save();

    $this->assertFileExists('public://new-dir/example.txt');
  }

  /**
   * A non-array override is ignored rather than crashing the save.
   */
  public function testNonArrayOverrideIsIgnored(): void {
    $file = $this->createFile('public://original/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->filefield_paths_settings = 'not-an-array';
    $entity->save();

    $this->assertFileExists('public://new-dir/example.txt');
  }

  /**
   * A whole-entity override suppresses processing for every field.
   */
  public function testWholeEntityOverrideSuppressesProcessing(): void {
    $file = $this->createFile('public://original/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $entity->filefield_paths_settings = ['enabled' => FALSE];
    $entity->save();

    $this->assertFileExists('public://original/example.txt');
    $this->assertFileDoesNotExist('public://new-dir/example.txt');
  }

  /**
   * A field-keyed override only suppresses processing for that field.
   */
  public function testFieldSpecificOverrideSuppressesOnlyThatField(): void {
    $suppressed_file = $this->createFile('public://original/suppressed.txt');
    $processed_file = $this->createFile('public://original/processed.txt');

    $entity = EntityTest::create([
      'field_file' => [['target_id' => $suppressed_file->id()]],
      'field_file_two' => [['target_id' => $processed_file->id()]],
    ]);
    $entity->filefield_paths_settings = ['field_file' => ['enabled' => FALSE]];
    $entity->save();

    $this->assertFileExists('public://original/suppressed.txt');
    $this->assertFileDoesNotExist('public://new-dir/suppressed.txt');
    $this->assertFileExists('public://new-dir/processed.txt');
  }

  /**
   * A field-specific override takes precedence over a flat override.
   */
  public function testFieldSpecificOverrideTakesPrecedenceOverFlatOverride(): void {
    $file = $this->createFile('public://original/example.txt');

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    // Whole-entity override disables, field-specific override re-enables.
    $entity->filefield_paths_settings = [
      'enabled' => FALSE,
      'field_file' => ['enabled' => TRUE],
    ];
    $entity->save();

    $this->assertFileExists('public://new-dir/example.txt');
  }

}
