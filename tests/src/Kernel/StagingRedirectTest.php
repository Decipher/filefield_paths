<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests which moves earn a redirect.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class StagingRedirectTest extends KernelTestBase {

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
    'path_alias',
    'redirect',
    'link',
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
    $this->installEntitySchema('redirect');
    $this->installEntitySchema('path_alias');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filefield_paths']);
    // Pin the staging location. The install default moved to
    // temporary://filefield_paths in 8.x-1.0-rc2, and the files these tests
    // attach must sit inside the staging location whatever the default is.
    $this->config('filefield_paths.settings')->set('temp_location', 'public://filefield_paths')->save();
    $this->config('redirect.settings')->set('default_status_code', 301)->save();

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['uri_scheme' => 'public'],
    ])->save();

    $options = ['slashes' => FALSE, 'pathauto' => FALSE, 'transliterate' => FALSE];
    $field = FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ]);
    $field->setThirdPartySetting('filefield_paths', 'enabled', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'file_path', [
      'value' => 'sorted',
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'file_name', [
      'value' => '',
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'active_updating', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'redirect', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'retroactive_update', FALSE);
    $field->save();
  }

  /**
   * Saves a new entity with a file at the given URI.
   *
   * @param string $uri
   *   Where the file sits before the entity is saved.
   */
  private function createEntityWithFileAt(string $uri): void {
    EntityTest::create([
      'name' => 'test',
      'field_file' => [['target_id' => $this->createFileAt($uri)->id()]],
    ])->save();
  }

  /**
   * Saves an entity without a file, then saves it again with one.
   *
   * @param string $uri
   *   Where the file sits before the second save.
   */
  private function updateEntityWithFileAt(string $uri): void {
    $entity = EntityTest::create(['name' => 'test']);
    $entity->save();
    $entity = EntityTest::load($entity->id());
    $entity->set('field_file', [['target_id' => $this->createFileAt($uri)->id()]]);
    $entity->save();
  }

  /**
   * Writes a file to disk and saves a permanent file entity for it.
   */
  private function createFileAt(string $uri): File {
    $file_system = $this->container->get('file_system');
    $directory = $file_system->dirname($uri);
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents($uri, 'contents');
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Counts the redirects that exist.
   */
  private function redirectCount(): int {
    return count($this->container->get('entity_type.manager')
      ->getStorage('redirect')
      ->loadMultiple());
  }

  /**
   * A new entity earns no redirect, wherever its file came from.
   *
   * Nothing can link to a file's path before the entity's first save.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testNoRedirectWhenTheEntityIsNew(): void {
    $this->createEntityWithFileAt('public://published/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(0, $this->redirectCount(), 'A new entity should not leave a redirect behind.');
  }

  /**
   * A file leaving the staging area earns no redirect.
   *
   * The staging path only ever existed between the upload and the save, so
   * nothing can be linking to it, even when the entity already existed.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testNoRedirectWhenTheFileComesFromStaging(): void {
    $this->updateEntityWithFileAt('public://filefield_paths/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(0, $this->redirectCount(), 'A staged upload should not leave a redirect behind.');
  }

  /**
   * A file that was already published still earns a redirect.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testRedirectWhenThePublishedFileMovesOnUpdate(): void {
    $this->updateEntityWithFileAt('public://published/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(1, $this->redirectCount(), 'A published file that moves should leave a redirect.');
  }

  /**
   * A bare scheme root is not a staging location.
   *
   * The settings form accepts "public://" with no directory. Used as a
   * staging prefix it would match every file on the scheme and quietly stop
   * redirects being created at all.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testBareSchemeRootIsNotTreatedAsStaging(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'public://')
      ->save();

    $this->updateEntityWithFileAt('public://published/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(1, $this->redirectCount(), 'A bare scheme root must not suppress redirects.');
  }

  /**
   * The field's own staging location wins over the global one.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testFieldStagingLocationTakesPrecedence(): void {
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_file');
    \assert($field instanceof FieldConfig);
    $field->setThirdPartySetting('filefield_paths', 'temp_location', 'public://custom_stage');
    $field->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->updateEntityWithFileAt('public://custom_stage/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(0, $this->redirectCount(), 'The field level staging location should suppress the redirect.');
  }

}
