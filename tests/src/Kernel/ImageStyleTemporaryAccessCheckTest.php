<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\Access\ImageStyleTemporaryAccessCheck;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the temporary image style access checker in isolation.
 *
 * The functional test (FileFieldPathsImageStyleTemporaryTest) already
 * exercises this checker over HTTP, but that request is served by a separate
 * PHP process, so it never registers in code coverage. This kernel test
 * calls access() directly so every branch is covered in-process.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Access\ImageStyleTemporaryAccessCheck
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class ImageStyleTemporaryAccessCheckTest extends KernelTestBase {

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
   * The access checker under test.
   */
  protected ImageStyleTemporaryAccessCheck $accessCheck;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->accessCheck = $this->container->get('filefield_paths.access_checker.image_style_temporary');
  }

  /**
   * Sets the FFP temp location config.
   */
  protected function setTempLocation(string $temp_location): void {
    $this->config('filefield_paths.settings')->set('temp_location', $temp_location)->save();
  }

  /**
   * An empty ?file= parameter is forbidden.
   */
  public function testEmptyFileIsForbidden(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $result = $this->accessCheck->access(Request::create('/'));
    $this->assertTrue($result->isForbidden());
    if (!$result instanceof CacheableDependencyInterface) {
      $this->fail('Expected a cacheable access result.');
    }
    $this->assertSame(['url.query_args:file'], $result->getCacheContexts());
    $this->assertSame(['config:filefield_paths.settings'], $result->getCacheTags());
  }

  /**
   * A non-temporary temp_location forbids access regardless of ?file=.
   */
  public function testNonTemporarySchemeIsForbidden(): void {
    $this->setTempLocation('private://filefield_paths');
    $result = $this->accessCheck->access(Request::create('/', 'GET', ['file' => 'filefield_paths/image.png']));
    $this->assertTrue($result->isForbidden());
  }

  /**
   * An empty subdirectory in temp_location forbids access.
   */
  public function testEmptySubdirIsForbidden(): void {
    $this->setTempLocation('temporary://');
    $result = $this->accessCheck->access(Request::create('/', 'GET', ['file' => 'anything.png']));
    $this->assertTrue($result->isForbidden());
  }

  /**
   * A path traversal sequence in ?file= is forbidden.
   */
  public function testPathTraversalIsForbidden(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $result = $this->accessCheck->access(Request::create('/', 'GET', ['file' => 'filefield_paths/../etc/passwd']));
    $this->assertTrue($result->isForbidden());
  }

  /**
   * A file outside the configured subdirectory is not allowed.
   *
   * AllowedIf(FALSE) returns a neutral result rather than an explicit forbid,
   * so this asserts isAllowed() rather than isForbidden() (unlike the other
   * negative cases above, which return AccessResult::forbidden() directly).
   */
  public function testFileOutsideSubdirIsNotAllowed(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $result = $this->accessCheck->access(Request::create('/', 'GET', ['file' => 'other_module/image.png']));
    $this->assertFalse($result->isAllowed());
  }

  /**
   * A file inside the configured subdirectory is allowed.
   */
  public function testFileInsideSubdirIsAllowed(): void {
    $this->setTempLocation('temporary://filefield_paths');
    $result = $this->accessCheck->access(Request::create('/', 'GET', ['file' => 'filefield_paths/image.png']));
    $this->assertTrue($result->isAllowed());
  }

}
