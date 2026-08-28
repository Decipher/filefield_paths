<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests processing a file field on media entities created by a migration.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class MigrateMediaFileTest extends KernelTestBase {

  use MediaTypeCreationTrait;

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
    'image',
    'media',
    'migrate',
    'filefield_paths',
  ];

  /**
   * The name of the media type's source field.
   */
  private string $sourceField;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['field', 'system', 'image', 'file', 'media', 'filefield_paths']);

    $media_type = $this->createMediaType('file', ['id' => 'document']);
    $this->sourceField = $media_type->getSource()->getConfiguration()['source_field'];

    $options = ['slashes' => FALSE, 'pathauto' => FALSE, 'transliterate' => FALSE];
    $field = FieldConfig::loadByName('media', 'document', $this->sourceField);
    $field->setThirdPartySetting('filefield_paths', 'enabled', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'file_path', [
      'value' => 'migrated',
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'file_name', [
      'value' => '',
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'active_updating', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'redirect', FALSE);
    $field->setThirdPartySetting('filefield_paths', 'retroactive_update', FALSE);
    $field->save();
  }

  /**
   * Runs a migration that creates one media entity referencing one file.
   *
   * @param int $fid
   *   The file to reference.
   *
   * @return \Drupal\migrate\MigrateExecutable
   *   The executable, already run.
   */
  private function runMigration(int $fid): MigrateExecutable {
    $definition = [
      'migration_tags' => ['filefield_paths test'],
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [['key' => 1, 'name' => 'Migrated document', 'fid' => $fid]],
        'ids' => ['key' => ['type' => 'integer']],
      ],
      'process' => [
        'name' => 'name',
        $this->sourceField . '/target_id' => 'fid',
      ],
      'destination' => [
        'plugin' => 'entity:media',
        'default_bundle' => 'document',
      ],
    ];
    $migration = $this->container->get('plugin.manager.migration')
      ->createStubMigration($definition);
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    // Assert the import itself, or an unrelated migration failure surfaces
    // only as a missing file and reads like a regression in this module.
    $this->assertSame(
      MigrationInterface::RESULT_COMPLETED,
      $executable->import(),
      'The migration ran to completion.'
    );
    return $executable;
  }

  /**
   * A migrated media entity has its file processed, without a fatal.
   *
   * The reported crash happened here: migrate saves the media entity, the
   * process hook runs, and the old code resolved stream wrappers in a way
   * that died when one could not be found.
   *
   * @see https://www.drupal.org/i/3432653
   */
  public function testMigratedMediaFileIsProcessed(): void {
    $file_system = $this->container->get('file_system');
    $directory = 'public://migrate-source';
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents("$directory/example.txt", 'contents');
    $file = File::create(['uri' => 'public://migrate-source/example.txt']);
    $file->setPermanent();
    $file->save();

    $this->runMigration((int) $file->id());

    $media = $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->load(1);
    $this->assertNotNull($media, 'The migration created the media entity.');
    $this->assertFileExists('public://migrated/example.txt');
  }

  /**
   * The same migration survives a temporary location with no stream wrapper.
   *
   * This is the reporter's environment: the configured temp location had no
   * registered wrapper, and the old code called a method on the FALSE that
   * StreamWrapperManager::getViaUri() returned for it. The current code never
   * resolves the temp location on this path at all, which is why it survives,
   * so this test guards the whole scenario rather than one branch.
   *
   * @see https://www.drupal.org/i/3432653
   */
  public function testMigrationSurvivesAnUnregisteredTemporaryScheme(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'bogus://filefield_paths')
      ->save();

    // The scenario only reproduces the crash while this scheme really has no
    // wrapper. getViaUri() returns FALSE here, and the old code called
    // getType() straight on that.
    $this->assertFalse(
      $this->container->get('stream_wrapper_manager')->getViaUri('bogus://filefield_paths'),
      'The configured temporary location must have no stream wrapper.'
    );

    $file_system = $this->container->get('file_system');
    $directory = 'public://migrate-source';
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents("$directory/example.txt", 'contents');
    $file = File::create(['uri' => 'public://migrate-source/example.txt']);
    $file->setPermanent();
    $file->save();

    $this->runMigration((int) $file->id());

    $this->assertFileExists('public://migrated/example.txt');
  }

}
