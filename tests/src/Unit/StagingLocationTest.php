<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\filefield_paths\StagingLocation;

/**
 * Tests the staging location helper.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\StagingLocation
 */
#[Group('filefield_paths')]
class StagingLocationTest extends UnitTestCase {

  /**
   * A configured location becomes a directory URI with one trailing slash.
   *
   * @dataProvider directoryProvider
   */
  #[DataProvider('directoryProvider')]
  public function testDirectory(mixed $location, string $expected): void {
    $this->assertSame($expected, StagingLocation::directory($location));
  }

  /**
   * Provides configured values and the directory each resolves to.
   *
   * @return array<string, array{mixed, string}>
   *   The configured value and the expected directory.
   */
  public static function directoryProvider(): array {
    return [
      'subdirectory' => ['temporary://filefield_paths', 'temporary://filefield_paths/'],
      'trailing slash' => ['temporary://filefield_paths/', 'temporary://filefield_paths/'],
      'nested' => ['public://custom/staging', 'public://custom/staging/'],
      'bare scheme root' => ['temporary://', 'temporary://'],
      'bare private root' => ['private://', 'private://'],
      'unset' => [NULL, 'temporary://'],
      'empty' => ['', 'temporary://'],
      'not a uri' => ['staging', 'temporary://'],
      'false' => [FALSE, 'temporary://'],
    ];
  }

  /**
   * Only a staging directory directly inside the location is recognised.
   *
   * @dataProvider isStagingDirectoryProvider
   */
  #[DataProvider('isStagingDirectoryProvider')]
  public function testIsStagingDirectory(string $directory, string $location, bool $expected): void {
    $this->assertSame($expected, StagingLocation::isStagingDirectory($directory, $location));
  }

  /**
   * Provides directories, locations and whether the directory is staging.
   *
   * @return array<string, array{string, string, bool}>
   *   The directory, the location and the expected result.
   */
  public static function isStagingDirectoryProvider(): array {
    return [
      'inside a subdirectory location' => ['temporary://filefield_paths/ffp-abc123', 'temporary://filefield_paths/', TRUE],
      'inside a scheme root' => ['temporary://ffp-abc123', 'temporary://', TRUE],
      'inside a private root' => ['private://ffp-abc123', 'private://', TRUE],
      'another location' => ['public://other/ffp-abc123', 'temporary://filefield_paths/', FALSE],
      'one level too deep' => ['temporary://filefield_paths/sub/ffp-abc123', 'temporary://filefield_paths/', FALSE],
      'not a staging name' => ['temporary://filefield_paths/uploads', 'temporary://filefield_paths/', FALSE],
      'the location itself' => ['temporary://filefield_paths', 'temporary://filefield_paths/', FALSE],
      'a slash in the name' => ['temporary://ffp-abc/123', 'temporary://', FALSE],
    ];
  }

}
