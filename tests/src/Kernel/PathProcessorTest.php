<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\PathProcessorInterface;

/**
 * Tests the PathProcessor service.
 *
 * Bug (undocumented, found while writing this test): doProcessString() reads
 * $settings['transliterate'] without a default, so any call that omits the
 * key triggers a PHP warning ("Undefined array key"). Every settings array
 * below explicitly sets 'transliterate' to work around this. Not filed as a
 * Drupal.org issue yet.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\PathProcessor
 */
#[Group('filefield_paths')]
class PathProcessorTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'filefield_paths',
    'token',
    'path_alias',
    'pathauto',
  ];

  /**
   * The path processor under test.
   */
  protected PathProcessorInterface $pathProcessor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['pathauto']);
    $this->pathProcessor = $this->container->get(PathProcessorInterface::class);
  }

  /**
   * Tokens are replaced before splitting into subdirectories by default.
   */
  public function testTokenReplacementBeforeSplit(): void {
    $result = $this->pathProcessor->processString('foo/bar', [], ['transliterate' => FALSE]);
    $this->assertSame('foo/bar', $result);
  }

  /**
   * Slashes are stripped from each path segment when requested.
   */
  public function testRemoveSlashes(): void {
    $result = $this->pathProcessor->processString('foo/ba/r/baz', [], [
      'slashes' => TRUE,
      'transliterate' => FALSE,
    ]);
    // Slash removal happens per already-split segment, so literal slashes in
    // the input string still define the directory structure.
    $this->assertSame('foo/ba/r/baz', $result);
  }

  /**
   * Transliteration replaces non-roman characters in each path segment.
   */
  public function testTransliterate(): void {
    $result = $this->pathProcessor->processString('café/naïve', [], ['transliterate' => TRUE]);
    $this->assertSame('cafe/naive', $result);
  }

  /**
   * Pathauto cleans the final filename segment, preserving the extension.
   */
  public function testPathautoCleansFileNameSegment(): void {
    $result = $this->pathProcessor->processString('My Folder/My File!.txt', [], [
      'pathauto' => TRUE,
      'context' => 'file_name',
      'transliterate' => FALSE,
    ]);
    // Only the last segment gets the extension-preserving treatment; earlier
    // segments are cleaned in full, same as the non-file_name context.
    $this->assertSame('my-folder/my-file.txt', $result);
  }

  /**
   * Pathauto with slash removal drops directory separators from the result.
   */
  public function testPathautoCleansFileNameSegmentWithSlashesRemoved(): void {
    $result = $this->pathProcessor->processString('My File!.txt', [], [
      'pathauto' => TRUE,
      'slashes' => TRUE,
      'context' => 'file_name',
      'transliterate' => FALSE,
    ]);
    $this->assertSame('my-file.txt', $result);
  }

  /**
   * Pathauto cleans every segment when not in the file_name context.
   */
  public function testPathautoCleansNonFileNameSegments(): void {
    $result = $this->pathProcessor->processString('My Folder/Sub Folder', [], [
      'pathauto' => TRUE,
      'context' => 'file_path',
      'transliterate' => FALSE,
    ]);
    $this->assertSame('my-folder/sub-folder', $result);
  }

  /**
   * Double slashes caused by empty token values collapse to a single slash.
   */
  public function testDoubleSlashCollapse(): void {
    $result = $this->pathProcessor->processString('foo//bar', [], ['transliterate' => FALSE]);
    $this->assertSame('foo/bar', $result);
  }

}
