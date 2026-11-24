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
 * Tests the 'Active updating' setting on an entity update.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class ActiveUpdatingTest extends KernelTestBase {

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
      'settings' => ['uri_scheme' => 'public'],
    ])->save();
    $this->setPathPattern('one');
  }

  /**
   * Points the field's File (Field) Paths path pattern at a directory.
   *
   * @param string $directory
   *   The directory to file uploads under.
   */
  private function setPathPattern(string $directory): void {
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_file');
    $field ??= FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ]);
    $options = ['slashes' => FALSE, 'pathauto' => FALSE, 'transliterate' => FALSE];
    $field->setThirdPartySetting('filefield_paths', 'enabled', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'file_path', [
      'value' => $directory,
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'file_name', [
      'value' => '',
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'active_updating', FALSE);
    $field->setThirdPartySetting('filefield_paths', 'redirect', FALSE);
    $field->setThirdPartySetting('filefield_paths', 'retroactive_update', FALSE);
    $field->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Attaches a staged file to a new entity and saves it.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The saved entity, with its file already processed.
   */
  private function createEntityWithStagedFile(): EntityTest {
    $file_system = $this->container->get('file_system');
    $staging = 'public://staging';
    $file_system->prepareDirectory($staging, $file_system::CREATE_DIRECTORY);
    file_put_contents("$staging/example.txt", 'contents');
    $file = File::create(['uri' => "$staging/example.txt"]);
    $file->setPermanent();
    $file->save();

    $entity = EntityTest::create([
      'name' => 'first',
      'field_file' => [['target_id' => $file->id()]],
    ]);
    $entity->save();

    return $entity;
  }

  /**
   * Reloads an entity and saves it again, as a second edit would.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The entity to save again.
   */
  private function saveAgain(EntityTest $entity): void {
    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->loadUnchanged((int) $entity->id());
    \assert($reloaded instanceof EntityTest);
    $reloaded->set('name', 'second');
    $reloaded->save();
  }

  /**
   * A file already on the entity stays put when 'Active updating' is off.
   *
   * The setting promises that files are only processed as they are attached.
   * Saving the entity again must not move a file that was already there,
   * however the path pattern has changed since.
   *
   * @see https://www.drupal.org/i/3616606
   */
  public function testFileIsNotMovedOnUpdateWhenActiveUpdatingIsOff(): void {
    $entity = $this->createEntityWithStagedFile();
    $this->assertFileExists('public://one/example.txt');

    // An admin changes the pattern after the file is already attached.
    $this->setPathPattern('two');
    $this->saveAgain($entity);

    $this->assertFileExists('public://one/example.txt');
    $this->assertFileDoesNotExist('public://two/example.txt');
  }

  /**
   * The same flow moves the file when 'Active updating' is on.
   *
   * This is the control for the test above. Without it, a passing skip
   * assertion could mean the hook never ran at all.
   *
   * @see https://www.drupal.org/i/3616606
   */
  public function testFileIsMovedOnUpdateWhenActiveUpdatingIsOn(): void {
    $entity = $this->createEntityWithStagedFile();
    $this->assertFileExists('public://one/example.txt');

    $this->setPathPattern('two');
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_file');
    \assert($field instanceof FieldConfig);
    $field->setThirdPartySetting('filefield_paths', 'active_updating', TRUE);
    $field->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->saveAgain($entity);

    $this->assertFileExists('public://two/example.txt');
  }

}
