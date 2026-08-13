<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\Hook\FileUrlHooks;

/**
 * Tests the file_url_alter and file_download hook implementations.
 *
 * The functional test (FileFieldPathsImageStyleTemporaryTest) already
 * exercises these hooks via ImageStyle::buildUrl() and an HTTP derivative
 * request, but that runs in a separate PHP process so it never registers in
 * code coverage. This kernel test isolates each branch directly against
 * fileUrlAlter() and fileDownload().
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FileUrlHooks
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
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

  /**
   * Non-temporary schemes receive no opinion from fileDownload().
   */
  public function testFileDownloadNonTemporarySchemeReturnsNull(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $this->assertNull($this->fileUrlHooks->fileDownload('public://filefield_paths/image.png'));
  }

  /**
   * Non-temporary temp_location receives no opinion from fileDownload().
   */
  public function testFileDownloadNonTemporaryTempLocationReturnsNull(): void {
    $this->setTempLocation('private://filefield_paths');
    $this->assertNull($this->fileUrlHooks->fileDownload('temporary://filefield_paths/image.png'));
  }

  /**
   * Files outside the FFP subdirectory receive no opinion from fileDownload().
   */
  public function testFileDownloadOutsideSubdirReturnsNull(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $this->assertNull($this->fileUrlHooks->fileDownload('temporary://other_module/image.png'));
  }

  /**
   * Paths inside the FFP subdirectory are granted access by fileDownload().
   */
  public function testFileDownloadInsideSubdirGrantsAccess(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $result = $this->fileUrlHooks->fileDownload('temporary://filefield_paths/image.png');
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame('inline', $result['Content-Disposition']);
  }

  /**
   * Nested paths inside the FFP subdirectory are also granted access.
   */
  public function testFileDownloadNestedPathGrantsAccess(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $result = $this->fileUrlHooks->fileDownload('temporary://filefield_paths/sub/dir/image.png');
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
  }

  /**
   * A different temporary subdir is honoured when temp_location is changed.
   */
  public function testFileDownloadCustomSubdir(): void {
    $this->setTempLocation('temporary://custom_staging');
    // Outside the configured subdir.
    $this->assertNull($this->fileUrlHooks->fileDownload('temporary://filefield_paths/image.png'));
    // Inside the configured subdir.
    $result = $this->fileUrlHooks->fileDownload('temporary://custom_staging/image.png');
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
  }

  /**
   * Derivative URIs for files outside the FFP subdir are not rewritten.
   */
  public function testUrlAlterOutsideSubdirIsUnaffected(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $uri = 'temporary://styles/thumbnail/temporary/other_module/image.png';
    $this->fileUrlHooks->fileUrlAlter($uri);
    $this->assertSame('temporary://styles/thumbnail/temporary/other_module/image.png', $uri);
  }

  /**
   * Path traversal in fileDownload() is rejected.
   */
  public function testFileDownloadTraversalReturnsNull(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $this->assertNull($this->fileUrlHooks->fileDownload('temporary://filefield_paths/../other_module/image.png'));
  }

}
