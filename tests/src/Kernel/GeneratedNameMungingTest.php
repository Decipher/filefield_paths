<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that a generated file name is sanitised the way core sanitises uploads.
 *
 * The name is built from tokens. A token can carry an extension that core
 * would have munged on upload, so the built name must go through the same
 * sanitising before the file is moved.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class GeneratedNameMungingTest extends KernelTestBase {

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
    'text',
    'filter',
    'node',
    'filefield_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filefield_paths', 'filter', 'node']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['uri_scheme' => 'public'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_file',
      'bundle' => 'article',
      'settings' => ['file_extensions' => 'jpg png'],
    ])->save();
  }

  /**
   * Creates a permanent file on disk under the staging directory.
   */
  protected function createStagedFile(string $filename): File {
    $file_system = $this->container->get('file_system');
    $directory = 'public://filefield_paths';
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    $uri = "$directory/$filename";
    file_put_contents($uri, 'GIF89a');
    $file = File::create(['uri' => $uri, 'filename' => $filename]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Runs the process-file hook for one file with the given name pattern.
   */
  protected function processFile(File $file, string $title, string $file_name_pattern): void {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'field_file' => [['target_id' => $file->id()]],
    ]);
    $field = $node->get('field_file');
    assert($field instanceof FileFieldItemList);

    $settings = [
      'file_path' => ['value' => 'uploads', 'options' => ['transliterate' => FALSE]],
      'file_name' => ['value' => $file_name_pattern, 'options' => ['transliterate' => FALSE]],
    ];
    $service = $this->container->get(FileFieldPathsProcessFileLegacy::class);
    $service->fileFieldPathsProcessFile($node, $field, $settings);
  }

  /**
   * An insecure inner extension from a token is munged, as core does on upload.
   *
   * Core stores an upload of "shell.php.jpg" as "shell.php_.jpg". A pattern
   * that rebuilds the name from the entity title must not restore the
   * unmunged form.
   */
  public function testInsecureInnerExtensionIsMunged(): void {
    $file = $this->createStagedFile('shell.php_.jpg');

    $this->processFile($file, 'shell.php', '[node:title].[file:ffp-extension-original]');

    $this->assertFileExists('public://uploads/shell.php_.jpg');
    $this->assertFileDoesNotExist('public://uploads/shell.php.jpg');
  }

  /**
   * A name whose final extension the field does not allow keeps the original.
   *
   * A pattern with no extension token can end in a title such as "index.php".
   * The file is still the type that passed upload validation, so its own
   * extension is put back and the result is sanitised.
   */
  public function testDisallowedFinalExtensionKeepsTheOriginal(): void {
    $file = $this->createStagedFile('photo.jpg');

    $this->processFile($file, 'index.php', '[node:title]');

    $this->assertFileExists('public://uploads/index.php_.jpg');
    $this->assertFileDoesNotExist('public://uploads/index.php');
  }

}
