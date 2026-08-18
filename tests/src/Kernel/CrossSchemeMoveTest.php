<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy;

/**
 * Tests moving a file between stream wrapper schemes.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class CrossSchemeMoveTest extends KernelTestBase {

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
    mkdir($this->siteDirectory . '/private', 0775);
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['uri_scheme' => 'public'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Core only registers the private:// stream wrapper if a file path is
    // set at container-build time; register it directly so the private://
    // scheme resolves in this test regardless of when settings are altered.
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * A file at public:// moves to private:// after the field's scheme changes.
   */
  public function testFileMovesFromPublicToPrivate(): void {
    $file_system = $this->container->get('file_system');
    $directory = 'public://cross-scheme-test';
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    $uri = "$directory/example.txt";
    file_put_contents($uri, 'contents');
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();

    // Change the field's upload destination scheme after data already
    // exists, as an admin would via drush or config import, since core
    // disables this in the storage settings UI once a field has data.
    // Field definitions are statically cached, so clear them afterward or
    // a freshly built entity would still see the old scheme.
    $field_storage = FieldStorageConfig::loadByName('entity_test', 'field_file');
    $field_storage->setSetting('uri_scheme', 'private');
    $field_storage->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $entity = EntityTest::create(['field_file' => [['target_id' => $file->id()]]]);
    $field = $entity->get('field_file');
    assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'cross-scheme-test', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => '', 'options' => ['transliterate' => FALSE]],
      'active_updating' => TRUE,
    ];
    $service = $this->container->get(FileFieldPathsProcessFileLegacy::class);
    $service->fileFieldPathsProcessFile($entity, $field, $settings);

    $this->assertFileExists('private://cross-scheme-test/example.txt');
    $this->assertFileDoesNotExist($uri);
  }

}
