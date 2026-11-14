<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\filefield_paths\Batch\BatchUpdaterInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a retroactive update on an entity with several revisions.
 *
 * @see https://www.drupal.org/i/1115740
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Batch\Updater
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class RetroactiveUpdateRevisionsTest extends KernelTestBase {

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
    'node',
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
    $this->installEntitySchema('node');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filefield_paths']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => ['uri_scheme' => 'public'],
    ])->save();
    // The files are created in this directory. Saving a node leaves them
    // where they are, so only the retroactive update moves anything.
    $this->setPathPattern('original');

    $this->updater = $this->container->get(BatchUpdaterInterface::class);
  }

  /**
   * Sets the field's File (Field) Paths path pattern.
   *
   * @param string $pattern
   *   The path pattern. It can hold tokens.
   */
  private function setPathPattern(string $pattern): void {
    $field = FieldConfig::loadByName('node', 'article', 'field_file');
    $field ??= FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_file',
      'bundle' => 'article',
    ]);
    $options = ['slashes' => FALSE, 'pathauto' => FALSE, 'transliterate' => FALSE];
    $field->setThirdPartySetting('filefield_paths', 'enabled', TRUE);
    $field->setThirdPartySetting('filefield_paths', 'file_path', [
      'value' => $pattern,
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'file_name', [
      'value' => '',
      'options' => $options,
    ]);
    $field->setThirdPartySetting('filefield_paths', 'active_updating', FALSE);
    $field->setThirdPartySetting('filefield_paths', 'redirect', FALSE);
    $field->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Creates a permanent file in the original directory.
   */
  private function createFile(string $name): FileInterface {
    $file_system = $this->container->get('file_system');
    $directory = 'public://original';
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY);
    file_put_contents("$directory/$name", 'contents');
    $file = File::create(['uri' => "$directory/$name"]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Creates a node with a first revision.
   *
   * @param string $title
   *   The title of the first revision.
   * @param \Drupal\file\FileInterface[] $files
   *   The files the first revision references.
   */
  private function createNode(string $title, array $files): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => $title,
      'field_file' => array_map(static fn(FileInterface $file): array => ['target_id' => $file->id()], $files),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Saves a new default revision of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $title
   *   The title of the new revision.
   * @param \Drupal\file\FileInterface[] $files
   *   The files the new revision references.
   */
  private function addRevision(NodeInterface $node, string $title, array $files): void {
    $node->setNewRevision(TRUE);
    $node->setTitle($title);
    $node->set('field_file', array_map(static fn(FileInterface $file): array => ['target_id' => $file->id()], $files));
    $node->save();
  }

  /**
   * Runs the batch operations the updater set, the way the Batch API does.
   *
   * @return array
   *   The results the operations collected.
   */
  private function runBatch(): array {
    $batch = &batch_get();
    $results = [];
    foreach ($batch['sets'][0]['operations'] as [$callback, [$ids, $field_config]]) {
      $context = ['sandbox' => [], 'results' => $results];
      do {
        $context['finished'] = 1;
        match ($callback[1]) {
          'batchProcess' => $this->updater->batchProcess($ids, $field_config, $context),
          'batchProcessRevisions' => $this->updater->batchProcessRevisions($ids, $field_config, $context),
          default => $this->fail('Unexpected batch operation.'),
        };
      } while ($context['finished'] < 1);
      $results = $context['results'];
    }
    return $results;
  }

  /**
   * Returns the current URI of a file.
   */
  private function uri(FileInterface $file): string {
    $this->container->get('entity_type.manager')->getStorage('file')->resetCache();
    return File::load($file->id())->getFileUri();
  }

  /**
   * Files only in an old revision stay put when revisions are skipped.
   */
  public function testOldRevisionsAreSkippedOnRequest(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = $this->createNode('first', [$file_a]);
    $this->addRevision($node, 'second', [$file_b]);

    $this->setPathPattern('moved');
    $this->assertTrue($this->updater->batchUpdate(FieldConfig::loadByName('node', 'article', 'field_file'), FALSE));
    $results = $this->runBatch();

    $this->assertSame('public://moved/b.txt', $this->uri($file_b));
    $this->assertSame('public://original/a.txt', $this->uri($file_a));
    $this->assertSame(['updated' => 1], $results);
  }

  /**
   * Files only in an old revision move by default.
   */
  public function testOldRevisionFilesMoveByDefault(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = $this->createNode('first', [$file_a]);
    $this->addRevision($node, 'second', [$file_b]);

    $this->setPathPattern('moved');
    $this->assertTrue($this->updater->batchUpdate(FieldConfig::loadByName('node', 'article', 'field_file')));
    $results = $this->runBatch();

    $this->assertSame('public://moved/b.txt', $this->uri($file_b));
    $this->assertSame('public://moved/a.txt', $this->uri($file_a));
    $this->assertSame(['updated' => 2], $results);
  }

  /**
   * Each file follows the newest revision that references it.
   *
   * A file the current revision still uses goes where the current revision
   * says. It is not moved again for an old revision. A file only old
   * revisions use goes where the newest of them says, and counts once.
   */
  public function testEachFileFollowsItsNewestRevision(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = $this->createNode('one', [$file_a, $file_b]);
    $this->addRevision($node, 'two', [$file_a, $file_b]);
    $this->addRevision($node, 'three', [$file_b]);

    $this->setPathPattern('moved/[node:title]');
    $this->assertTrue($this->updater->batchUpdate(FieldConfig::loadByName('node', 'article', 'field_file')));
    $results = $this->runBatch();

    $this->assertSame('public://moved/three/b.txt', $this->uri($file_b));
    $this->assertSame('public://moved/two/a.txt', $this->uri($file_a));
    $this->assertSame(['updated' => 2], $results);
  }

  /**
   * Old revisions are found when the current revision has no files.
   */
  public function testOldRevisionsAreFoundWhenCurrentRevisionHasNoFiles(): void {
    $file_a = $this->createFile('a.txt');
    $node = $this->createNode('first', [$file_a]);
    $this->addRevision($node, 'second', []);

    $this->setPathPattern('moved');
    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    $this->assertFalse($this->updater->batchUpdate($field_config, FALSE));
    $this->assertTrue($this->updater->batchUpdate($field_config));
    $this->runBatch();

    $this->assertSame('public://moved/a.txt', $this->uri($file_a));
  }

  /**
   * The revision pass changes no revision and leaves the setting alone.
   */
  public function testRevisionPassSavesNoRevision(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = $this->createNode('first', [$file_a]);
    $this->addRevision($node, 'second', [$file_b]);
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $revision_ids = $storage->getQuery()->accessCheck(FALSE)->allRevisions()->execute();

    $this->setPathPattern('moved');
    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    $this->updater->batchUpdate($field_config);
    $this->runBatch();

    $storage->resetCache();
    $this->assertSame($revision_ids, $storage->getQuery()->accessCheck(FALSE)->allRevisions()->execute());
    $first = $storage->loadRevision(array_key_first($revision_ids));
    $this->assertInstanceOf(NodeInterface::class, $first);
    $this->assertSame((string) $file_a->id(), (string) $first->get('field_file')->target_id);
    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    $this->assertFalse($field_config->getThirdPartySetting('filefield_paths', 'active_updating'));
  }

  /**
   * Old revisions of a field with File (Field) Paths off are left alone.
   *
   * The field still holds a path pattern from when it was on.
   */
  public function testDisabledFieldIsLeftAlone(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = $this->createNode('first', [$file_a]);
    $this->addRevision($node, 'second', [$file_b]);

    $this->setPathPattern('moved');
    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    $field_config->setThirdPartySetting('filefield_paths', 'enabled', FALSE);
    $field_config->save();
    $this->updater->batchUpdate($field_config);
    $results = $this->runBatch();

    $this->assertSame('public://original/a.txt', $this->uri($file_a));
    $this->assertSame('public://original/b.txt', $this->uri($file_b));
    // Nothing is counted for a field the module does not process.
    $this->assertSame([], $results);
  }

  /**
   * Old revisions of a field with no File (Field) Paths settings are skipped.
   *
   * Drush updates every file field, so it reaches fields like this one.
   */
  public function testFieldWithoutSettingsIsSkipped(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = $this->createNode('first', [$file_a]);
    $this->addRevision($node, 'second', [$file_b]);

    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    foreach ($field_config->getThirdPartySettings('filefield_paths') as $key => $value) {
      $field_config->unsetThirdPartySetting('filefield_paths', $key);
    }
    $field_config->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->updater->batchUpdate($field_config);
    $results = $this->runBatch();

    $this->assertSame('public://original/a.txt', $this->uri($file_a));
    $this->assertSame('public://original/b.txt', $this->uri($file_b));
    // Nothing is counted for a field the module does not process.
    $this->assertSame([], $results);
  }

}
