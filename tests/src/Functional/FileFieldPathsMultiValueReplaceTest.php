<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Replaces every image on a multi-value field from the node edit form.
 *
 * Reproduction for https://www.drupal.org/i/3277844. The reporter attached
 * three images to a node, then edited the node, removed all three and
 * uploaded three new ones. Images ended up in the wrong order, on the wrong
 * item, or as a copy of another image.
 *
 * @group filefield_paths
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class FileFieldPathsMultiValueReplaceTest extends FileFieldPathsTestBase {

  /**
   * The image field name.
   */
  private const FIELD = 'field_gallery';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filefield_paths_test',
    'file_test',
    'image',
    'redirect',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The field settings from the issue report, without Pathauto cleaning.
    // The bug is in the staging path, which Pathauto never touches, and
    // Pathauto trips a PHP 8.5 deprecation on every node form.
    // See https://www.drupal.org/project/pathauto/issues/3579655.
    $options = [
      'slashes' => TRUE,
      'transliterate' => TRUE,
    ];
    $third_party_settings['filefield_paths'] = [
      'file_path' => [
        'value' => '[date:custom:Y]-[date:custom:m]',
        'options' => $options,
      ],
      'file_name' => [
        'value' => '[node:title].[file:ffp-extension-original]',
        'options' => $options,
      ],
      'redirect' => TRUE,
      'retroactive_update' => FALSE,
      'active_updating' => FALSE,
    ];
    $storage_settings['cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $this->createImageField(self::FIELD, $this->contentType, $storage_settings, [], $third_party_settings);
  }

  /**
   * Tests that replaced images keep their order, alt text and content.
   */
  public function testReplaceAllImages(): void {
    // Two sets of three images. Both sets use the same file names, the way a
    // camera or an export does. Every file has its own content.
    $first = $this->createImages('first', 10);
    $second = $this->createImages('second', 40);

    // Create the node with the first set.
    $this->drupalGet('node/add/' . $this->contentType);
    $alts = [];
    foreach ($first as $uri) {
      $alts[$this->uploadImage($uri)] = 'first ' . basename($uri);
    }
    $edit = ['title[0][value]' => 'Photo set'];
    foreach ($alts as $delta => $alt) {
      $edit[sprintf('%s[%d][alt]', self::FIELD, $delta)] = $alt;
    }
    $this->submitForm($edit, 'Save');
    $node = $this->drupalGetNodeByTitle('Photo set');
    $this->assertInstanceOf(NodeInterface::class, $node);
    $nid = (int) $node->id();

    $before = $this->assertImages($nid, $first, 'first');

    // Edit the node. Remove every image, then upload the second set.
    $this->drupalGet('node/' . $nid . '/edit');
    $count = count($first);
    for ($i = 0; $i < $count; $i++) {
      $button = $this->assertSession()->elementExists('css', sprintf('input[name^="%s_"][name$="_remove_button"]', self::FIELD));
      $this->submitForm([], $button->getAttribute('name'));
    }
    $this->assertSession()->elementNotExists('css', sprintf('input[name^="%s_"][name$="_remove_button"]', self::FIELD));
    $alts = [];
    foreach ($second as $uri) {
      $alts[$this->uploadImage($uri)] = 'second ' . basename($uri);
    }
    $edit = [];
    foreach ($alts as $delta => $alt) {
      $edit[sprintf('%s[%d][alt]', self::FIELD, $delta)] = $alt;
    }
    $this->submitForm($edit, 'Save');

    $after = $this->assertImages($nid, $second, 'second');

    // The new files did not land on top of the old ones.
    $this->assertEmpty(array_intersect($before, $after), 'No file URI is shared between the old and the new set.');
    foreach ($before as $index => $uri) {
      $this->assertFileExists($this->realpath($uri));
      $this->assertSame(md5_file($this->realpath($first[$index])), md5_file($this->realpath($uri)), 'The old file ' . $uri . ' is unchanged.');
    }
  }

  /**
   * Tests that a staged upload does not reuse the preview URL of another file.
   *
   * The image widget shows a thumbnail of the staged file. Its URL is the
   * staged path plus an itok that is a hash of that path. The upload of a new
   * file with the same name lands on the same staged path once the earlier
   * file has moved out, so it gets the same URL. A browser or a CDN that
   * cached the first thumbnail shows it for the second file.
   */
  public function testStagedPreviewUrlIsUnique(): void {
    $first = $this->createImages('first', 10);
    $second = $this->createImages('second', 40);

    $this->drupalGet('node/add/' . $this->contentType);
    $delta = $this->uploadImage($first[0]);
    $first_preview = $this->getPreviewUrl();
    $this->submitForm([
      'title[0][value]' => 'Preview',
      sprintf('%s[%d][alt]', self::FIELD, $delta) => 'first',
    ], 'Save');
    $node = $this->drupalGetNodeByTitle('Preview');
    $this->assertInstanceOf(NodeInterface::class, $node);

    $this->drupalGet('node/' . $node->id() . '/edit');
    $button = $this->assertSession()->elementExists('css', sprintf('input[name^="%s_"][name$="_remove_button"]', self::FIELD));
    $this->submitForm([], $button->getAttribute('name'));
    $this->uploadImage($second[0]);
    $second_preview = $this->getPreviewUrl();

    $this->assertNotSame($first_preview, $second_preview, 'Two different files never share a preview URL.');
  }

  /**
   * Creates three JPEG images with the same names and different content.
   *
   * @param string $set
   *   The directory name for the set.
   * @param int $size
   *   The width and height of the first image. Each image is 10px larger.
   *
   * @return string[]
   *   The URIs, in upload order.
   */
  private function createImages(string $set, int $size): array {
    $directory = 'public://' . $set;
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $source = NULL;
    foreach ($this->drupalGetTestFiles('image') as $file) {
      if (str_ends_with($file->uri, 'image-test.jpg')) {
        $source = $file->uri;
      }
    }
    $this->assertNotNull($source);

    $uris = [];
    foreach (['one', 'two', 'three'] as $index => $name) {
      $image = \Drupal::service('image.factory')->get($source);
      $image->resize($size + $index * 10, $size + $index * 10);
      $uri = $directory . '/' . $name . '.jpg';
      $this->assertTrue($image->save($uri));
      $uris[] = $uri;
    }
    return $uris;
  }

  /**
   * Uploads an image into the empty slot of the widget on the current page.
   *
   * @param string $uri
   *   The file to upload.
   *
   * @return int
   *   The delta of the slot the file went into.
   */
  private function uploadImage(string $uri): int {
    $input = $this->assertSession()->elementExists('css', sprintf('input[type="file"][name^="files[%s_"]', self::FIELD));
    $name = (string) $input->getAttribute('name');
    $this->assertSame(1, preg_match('/_(\d+)\]\[\]$/', $name, $matches), 'The empty slot has a delta.');
    $this->submitForm([$name => $this->realpath($uri)], 'Upload');
    return (int) $matches[1];
  }

  /**
   * Returns the preview image URL of the only staged item on the current page.
   */
  private function getPreviewUrl(): string {
    $images = $this->getSession()->getPage()->findAll('css', 'img[data-drupal-selector$="-preview"]');
    $this->assertCount(1, $images, 'The form shows one preview thumbnail.');
    $src = reset($images)->getAttribute('src');
    $this->assertNotEmpty($src);
    return $src;
  }

  /**
   * Asserts the node's images match the uploaded set, in order.
   *
   * @param int $nid
   *   The node ID.
   * @param string[] $sources
   *   The uploaded files, in upload order.
   * @param string $set
   *   The set name, used in the alt text.
   *
   * @return string[]
   *   The URIs of the field's files, in delta order.
   */
  private function assertImages(int $nid, array $sources, string $set): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$nid]);
    $node = $storage->load($nid);
    $this->assertInstanceOf(NodeInterface::class, $node);
    $items = $node->get(self::FIELD)->getValue();
    $this->assertCount(count($sources), $items);

    $uris = [];
    foreach ($items as $delta => $item) {
      $file = File::load($item['target_id']);
      $this->assertInstanceOf(File::class, $file);
      $uri = $file->getFileUri();
      $uris[] = $uri;
      $this->assertSame($set . ' ' . basename($sources[$delta]), $item['alt'], 'Delta ' . $delta . ' keeps its alt text.');
      $this->assertMatchesRegularExpression('#^public://' . date('Y-m') . '/Photo set(_\d+)?\.jpg$#', $uri, 'Delta ' . $delta . ' is at the configured path.');
      $this->assertFileExists($this->realpath($uri));
      $this->assertSame(md5_file($this->realpath($sources[$delta])), md5_file($this->realpath($uri)), 'Delta ' . $delta . ' has the content of ' . $sources[$delta] . '.');
    }
    $this->assertCount(count($sources), array_unique($uris), 'Every item has its own file.');
    return $uris;
  }

  /**
   * Returns the local path of a stream wrapper URI.
   */
  private function realpath(string $uri): string {
    $path = \Drupal::service('file_system')->realpath($uri);
    $this->assertIsString($path);
    return $path;
  }

}
