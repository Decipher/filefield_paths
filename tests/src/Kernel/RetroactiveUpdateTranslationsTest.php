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
use Drupal\filter\Entity\FilterFormat;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests a retroactive update on an entity with translations.
 *
 * @see https://www.drupal.org/i/3149644
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Batch\Updater
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsProcessFileLegacy
 * @covers \Drupal\filefield_paths\PathProcessor
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class RetroactiveUpdateTranslationsTest extends KernelTestBase {

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
    'filter',
    'text',
    'language',
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
    $this->installConfig(['language', 'filefield_paths']);
    ConfigurableLanguage::createFromLangcode('fr')->save();

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
   * @param bool $translatable
   *   Whether each translation has its own files.
   * @param array $options
   *   Path options that replace the defaults, such as 'slashes'.
   */
  private function setPathPattern(string $pattern, bool $translatable = TRUE, array $options = []): void {
    $field = FieldConfig::loadByName('node', 'article', 'field_file');
    $field ??= FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_file',
      'bundle' => 'article',
    ]);
    $options += ['slashes' => FALSE, 'pathauto' => FALSE, 'transliterate' => FALSE];
    $field->set('translatable', $translatable);
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
   * Returns field values for a list of files.
   *
   * @param \Drupal\file\FileInterface[] $files
   *   The files.
   *
   * @return array
   *   The field values.
   */
  private function items(array $files): array {
    return array_map(static fn(FileInterface $file): array => ['target_id' => $file->id()], $files);
  }

  /**
   * Creates an English node with a French translation.
   *
   * @param \Drupal\file\FileInterface[] $english
   *   The files the English node references.
   * @param \Drupal\file\FileInterface[] $french
   *   The files the French translation references.
   * @param string $title
   *   The English title.
   */
  private function createNode(array $english, array $french, string $title = 'english'): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'langcode' => 'en',
      'title' => $title,
      'field_file' => $this->items($english),
    ]);
    $node->save();
    $node->addTranslation('fr', [
      'title' => 'french',
      'field_file' => $this->items($french),
    ])->save();
    return $node;
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
   * Runs a retroactive update of the file field.
   *
   * @param bool $include_revisions
   *   (optional) Whether to include old revisions. Defaults to TRUE, like the
   *   updater.
   *
   * @return array
   *   The results the batch collected.
   */
  private function update(bool $include_revisions = TRUE): array {
    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    $this->assertTrue($this->updater->batchUpdate($field_config, $include_revisions));
    return $this->runBatch();
  }

  /**
   * Returns the current URI of a file.
   */
  private function uri(FileInterface $file): string {
    $this->container->get('entity_type.manager')->getStorage('file')->resetCache();
    return File::load($file->id())->getFileUri();
  }

  /**
   * The files of each translation move to that translation's path.
   */
  public function testTranslationFilesMove(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $this->createNode([$file_a], [$file_b]);

    $this->setPathPattern('moved/[node:title]');
    $results = $this->update();

    $this->assertSame('public://moved/english/a.txt', $this->uri($file_a));
    $this->assertSame('public://moved/french/b.txt', $this->uri($file_b));
    $this->assertSame(['updated' => 2], $results);
  }

  /**
   * Translation files of a field with File (Field) Paths off are left alone.
   */
  public function testDisabledFieldIsLeftAlone(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $this->createNode([$file_a], [$file_b]);

    $this->setPathPattern('moved/[node:title]');
    $field_config = FieldConfig::loadByName('node', 'article', 'field_file');
    $field_config->setThirdPartySetting('filefield_paths', 'enabled', FALSE);
    $field_config->save();
    $this->updater->batchUpdate($field_config);
    $this->runBatch();

    $this->assertSame('public://original/a.txt', $this->uri($file_a));
    $this->assertSame('public://original/b.txt', $this->uri($file_b));
  }

  /**
   * A node is updated when only a translation has files.
   */
  public function testOnlyTranslationHasFiles(): void {
    $file_b = $this->createFile('b.txt');
    $this->createNode([], [$file_b]);

    $this->setPathPattern('moved');
    $results = $this->update();

    $this->assertSame('public://moved/b.txt', $this->uri($file_b));
    $this->assertSame(['updated' => 1], $results);
  }

  /**
   * A file that two translations use moves once and counts once.
   */
  public function testSharedFileCountsOnce(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $this->createNode([$file_a], [$file_a, $file_b]);

    $this->setPathPattern('moved');
    $results = $this->update();

    $this->assertSame('public://moved/a.txt', $this->uri($file_a));
    $this->assertSame('public://moved/b.txt', $this->uri($file_b));
    $this->assertSame(['updated' => 2], $results);
  }

  /**
   * A field that is not translatable is processed once.
   */
  public function testUntranslatableFieldIsProcessedOnce(): void {
    $this->setPathPattern('original', FALSE);
    $file_a = $this->createFile('a.txt');
    $this->createNode([$file_a], [$file_a]);

    $this->setPathPattern('moved', FALSE);
    $results = $this->update();

    $this->assertSame('public://moved/a.txt', $this->uri($file_a));
    $this->assertSame(['updated' => 1], $results);
  }

  /**
   * The files of an old revision's translations move with revisions included.
   */
  public function testOldRevisionTranslationFilesMove(): void {
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $file_c = $this->createFile('c.txt');
    $node = $this->createNode([$file_a], [$file_b]);
    $french = $node->getTranslation('fr');
    $french->setNewRevision(TRUE);
    $french->set('field_file', $this->items([$file_c]));
    $french->save();

    $this->setPathPattern('moved');
    $results = $this->update();

    $this->assertSame('public://moved/a.txt', $this->uri($file_a));
    $this->assertSame('public://moved/b.txt', $this->uri($file_b));
    $this->assertSame('public://moved/c.txt', $this->uri($file_c));
    $this->assertSame(['updated' => 3], $results);
  }

  /**
   * An old revision does not move a file the current translation uses.
   *
   * The path holds the title. The current French translation uses the file.
   * An old English revision titled "one" also uses it, but must not move it.
   */
  public function testOldRevisionLeavesCurrentTranslationFile(): void {
    $file_a = $this->createFile('a.txt');
    $node = $this->createNode([$file_a], [], 'one');
    $node->setNewRevision(TRUE);
    $node->set('field_file', []);
    $french = $node->getTranslation('fr');
    $french->set('field_file', $this->items([$file_a]));
    $node->save();

    $this->setPathPattern('moved/[node:title]');
    $results = $this->update();

    $this->assertSame('public://moved/french/a.txt', $this->uri($file_a));
    $this->assertSame(['updated' => 1], $results);
  }

  /**
   * Adds a translatable body field to the article type.
   *
   * The body uses a text format with no filters. So the [node:body] token
   * gives the plain value, which is safe in a path.
   */
  private function addBodyField(): void {
    FilterFormat::create(['format' => 'raw', 'name' => 'Raw', 'filters' => []])->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'translatable' => TRUE,
    ])->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Tokens use the language of the translation that is processed.
   *
   * The [node:title] token reads the translation object. The [node:body]
   * token looks up a translation by language. Without a language it gets the
   * default translation.
   */
  public function testTokensUseTranslationLanguage(): void {
    $this->assertTokensUseTranslationLanguage([]);
  }

  /**
   * Tokens use the translation's language when slashes are removed.
   *
   * With "Remove slashes from tokens" on, tokens are replaced one path segment
   * at a time. That is a second call, which needs the language too.
   */
  public function testTokensUseTranslationLanguageWithSlashRemoval(): void {
    $this->assertTokensUseTranslationLanguage(['slashes' => TRUE]);
  }

  /**
   * Moves a file on each translation and checks the body token's language.
   *
   * @param array $options
   *   Path options that replace the defaults.
   */
  private function assertTokensUseTranslationLanguage(array $options): void {
    $this->addBodyField();
    $file_a = $this->createFile('a.txt');
    $file_b = $this->createFile('b.txt');
    $node = Node::create([
      'type' => 'article',
      'langcode' => 'en',
      'title' => 'english',
      'body' => ['value' => 'english-body', 'format' => 'raw'],
      'field_file' => $this->items([$file_a]),
    ]);
    $node->save();
    $node->addTranslation('fr', [
      'title' => 'french',
      'body' => ['value' => 'french-body', 'format' => 'raw'],
      'field_file' => $this->items([$file_b]),
    ])->save();

    $this->setPathPattern('moved/[node:body]', TRUE, $options);
    $results = $this->update();

    $this->assertSame('public://moved/english-body/a.txt', $this->uri($file_a));
    $this->assertSame('public://moved/french-body/b.txt', $this->uri($file_b));
    $this->assertSame(['updated' => 2], $results);
  }

  /**
   * A normal save of a translation uses that translation's language.
   *
   * This is not a retroactive update. A new file on the French translation
   * goes to the French body's path.
   */
  public function testSavingTranslationUsesItsLanguage(): void {
    $this->addBodyField();
    $this->setPathPattern('moved/[node:body]');
    $node = Node::create([
      'type' => 'article',
      'langcode' => 'en',
      'title' => 'english',
      'body' => ['value' => 'english-body', 'format' => 'raw'],
    ]);
    $node->save();
    $file_b = $this->createFile('b.txt');
    $node->addTranslation('fr', [
      'title' => 'french',
      'body' => ['value' => 'french-body', 'format' => 'raw'],
      'field_file' => $this->items([$file_b]),
    ])->save();

    $this->assertSame('public://moved/french-body/b.txt', $this->uri($file_b));
  }

}
