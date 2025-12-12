<?php

namespace Drupal\Tests\filefield_paths\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\filefield_paths\MoveFileProcessor;

/**
 * Tests MoveFileProcessor::detectRecommendedScheme().
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\MoveFileProcessor::detectRecommendedScheme
 */
class MoveFileProcessorDetectRecommendedSchemeTest extends UnitTestCase {

  /**
   * Provides test cases for detectRecommendedScheme().
   *
   * @return array
   *   An array of test cases with: wrappers, writable, expected.
   */
  public static function providerDetectRecommendedScheme(): array {
    return [
      'temporary present and writable (preferred)' => [
        // Wrappers available.
        ['temporary' => ['class' => 'Dummy'], 'private' => ['class' => 'Dummy']],
        // Writable map.
        ['temporary://' => TRUE, 'private://' => TRUE],
        'temporary://',
      ],
      'temporary not writable, private writable' => [
        ['temporary' => ['class' => 'Dummy'], 'private' => ['class' => 'Dummy']],
        ['temporary://' => FALSE, 'private://' => TRUE],
        'private://',
      ],
      'both present but neither writable -> public' => [
        ['temporary' => ['class' => 'Dummy'], 'private' => ['class' => 'Dummy']],
        ['temporary://' => FALSE, 'private://' => FALSE],
        'public://',
      ],
      'only private present and writable' => [
        ['private' => ['class' => 'Dummy']],
        ['temporary://' => FALSE, 'private://' => TRUE],
        'private://',
      ],
      'only temporary present but not writable -> public' => [
        ['temporary' => ['class' => 'Dummy']],
        ['temporary://' => FALSE, 'private://' => FALSE],
        'public://',
      ],
      'no wrappers present -> public' => [
        [],
        ['temporary://' => TRUE, 'private://' => TRUE],
        'public://',
      ],
    ];
  }

  /**
   * Tests detectRecommendedScheme().
   *
   * @dataProvider providerDetectRecommendedScheme
   */
  public function testDetectRecommendedScheme(array $wrappers, array $writable_map, string $expected): void {
    $stream_manager = $this->createMock(StreamWrapperManagerInterface::class);
    $cache_backend = $this->createMock(CacheBackendInterface::class);

    $processor = new class($stream_manager, $cache_backend, $writable_map) extends MoveFileProcessor {

      /**
       * {@inheritdoc}
       */
      public function __construct($stream_manager, $cache_backend, private array $writable_map) {
        parent::__construct($stream_manager, $cache_backend);
      }

      /**
       * {@inheritdoc}
       */
      protected function isWritable(string $path): bool {
        return $this->writable_map[$path] ?? FALSE;
      }

    };

    $this->assertSame($expected, $processor->detectRecommendedScheme($wrappers));
  }

}
