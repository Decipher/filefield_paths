<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
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
   * {@inheritdoc}
   */
  #[\Override]
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * Attaches a file at the given URI to a new entity and saves it.
   *
   * @param string $uri
   *   Where the file sits before the entity is saved.
   */
  private function attachFileAt(string $uri): void {
    $file_system = $this->container->get('file_system');
    $directory = $file_system->dirname($uri);
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents($uri, 'contents');
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();

    EntityTest::create([
      'name' => 'test',
      'field_file' => [['target_id' => $file->id()]],
    ])->save();
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
   * A file leaving the staging area earns no redirect.
   *
   * The staging path only ever existed between the upload and the save, so
   * nothing can be linking to it.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testNoRedirectWhenTheFileComesFromStaging(): void {
    $this->attachFileAt('public://filefield_paths/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(0, $this->redirectCount(), 'A staged upload should not leave a redirect behind.');
  }

  /**
   * A file moved from a real location still earns a redirect.
   *
   * This is the control. The fix must not stop redirects for files that were
   * genuinely reachable at their old path.
   *
   * @see https://www.drupal.org/i/3494240
   */
  public function testRedirectWhenTheFileWasAlreadyPublished(): void {
    $this->attachFileAt('public://published/example.txt');

    $this->assertFileExists('public://sorted/example.txt');
    $this->assertSame(1, $this->redirectCount(), 'A move from a real location should still leave a redirect.');
  }

}
