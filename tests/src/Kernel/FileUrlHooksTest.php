<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\Hook\FileUrlHooks;

/**
 * Tests the file_url_alter hook implementation in isolation.
 *
 * The functional test (FileFieldPathsImageStyleTemporaryTest) already
 * exercises this hook via ImageStyle::buildUrl(), but that runs in the same
 * process so it is partially covered there too; this kernel test isolates
 * each branch directly against fileUrlAlter().
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileUrlHooks
 */
#[Group('filefield_paths')]
class FileUrlHooksTest extends KernelTestBase {

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
  ];

  /**
   * The hook implementation under test.
   */
  protected FileUrlHooks $fileUrlHooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fileUrlHooks = $this->container->get(FileUrlHooks::class);
  }

  /**
   * Sets the FFP temp location config.
   */
  protected function setTempLocation(string $temp_location): void {
    $this->config('filefield_paths.settings')->set('temp_location', $temp_location)->save();
  }

  /**
   * Non-temporary temp_location leaves the URI untouched.
   */
  public function testNonTemporarySchemeIsUnaffected(): void {
    $this->setTempLocation('private://filefield_paths');
    $uri = 'temporary://styles/thumbnail/temporary/filefield_paths/image.png';
    $this->fileUrlHooks->fileUrlAlter($uri);
    $this->assertSame('temporary://styles/thumbnail/temporary/filefield_paths/image.png', $uri);
  }

  /**
   * A URI that does not match the temporary style pattern is unaffected.
   */
  public function testNonMatchingUriIsUnaffected(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $uri = 'temporary://filefield_paths/image.png';
    $this->fileUrlHooks->fileUrlAlter($uri);
    $this->assertSame('temporary://filefield_paths/image.png', $uri);
  }

  /**
   * A matching temporary style derivative URI is rewritten to the FFP route.
   */
  public function testMatchingUriIsRewritten(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $uri = 'temporary://styles/thumbnail/temporary/filefield_paths/image.png';
    $this->fileUrlHooks->fileUrlAlter($uri);
    $this->assertStringContainsString('/filefield_paths/image-style/thumbnail/temporary', $uri);
    $this->assertStringContainsString('file=', $uri);
    $this->assertStringContainsString('filefield_paths', $uri);
  }

}
