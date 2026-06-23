<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests on-demand image style delivery for temporary:// staged files.
 *
 * @group filefield_paths
 */
#[Group('filefield_paths')]
class FileFieldPathsImageStyleTemporaryTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filefield_paths', 'image'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Image style used across tests.
   */
  protected ImageStyle $style;

  /**
   * URI of the test image staged in temporary://.
   */
  protected string $imageUri;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Use temporary:// as the FFP temp location so the URL alter hook fires.
    \Drupal::configFactory()
      ->getEditable('filefield_paths.settings')
      ->set('temp_location', 'temporary://filefield_paths')
      ->save();

    // Create a simple image style.
    $this->style = ImageStyle::create(['name' => 'ffp_test', 'label' => 'FFP test']);
    $this->style->save();

    // Copy a core test image into the FFP temp location.
    $temp_location = 'temporary://filefield_paths';
    \Drupal::service('file_system')->prepareDirectory(
      $temp_location,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );
    $source = \Drupal::root() . '/core/tests/fixtures/files/image-1.png';
    $this->imageUri = \Drupal::service('file_system')
      ->copy($source, $temp_location . '/image-1.png', FileExists::Replace);
  }

  /**
   * Tests that derivative URLs are rewritten to the FFP route.
   */
  public function testUrlIsRewritten(): void {
    $url = $this->style->buildUrl($this->imageUri);
    $this->assertStringContainsString('/filefield_paths/image-style/ffp_test/temporary', $url);
    $this->assertStringContainsString('file=filefield_paths/', $url);
    $this->assertStringContainsString('itok=', $url);
  }

  /**
   * Tests that image style derivatives are served for temporary:// files.
   */
  public function testDerivativeIsServed(): void {
    $url = $this->style->buildUrl($this->imageUri);

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'image/');
  }

  /**
   * Tests that a missing or invalid itok returns 404.
   */
  public function testMissingTokenReturns404(): void {
    $url = $this->style->buildUrl($this->imageUri);

    // Strip the itok parameter.
    $url_no_token = preg_replace('/[?&]itok=[^&]+/', '', $url);

    $this->drupalGet($url_no_token);
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests that ?file= outside the FFP subdirectory returns 403.
   */
  public function testFileOutsideSubdirReturns403(): void {
    $url = $this->style->buildUrl($this->imageUri);

    // Replace the FFP subdirectory prefix with a different path.
    $url_outside = preg_replace('#(\?|&)file=filefield_paths/#', '$1file=other_module/', $url);

    $this->drupalGet($url_outside);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that accessing the route without a file parameter returns 403.
   */
  public function testEmptyFileParamReturns403(): void {
    $this->drupalGet('/filefield_paths/image-style/ffp_test/temporary');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that a path traversal attempt in ?file= returns 403.
   */
  public function testPathTraversalReturns403(): void {
    $url = $this->style->buildUrl($this->imageUri);
    $url_traversal = preg_replace('#(\?|&)file=filefield_paths/#', '$1file=filefield_paths/../', $url);
    $this->drupalGet($url_traversal);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that non-temporary temp_location does not trigger URL rewriting.
   *
   * When temp_location uses private://, the alter hook must not intercept
   * derivative URLs, leaving them on the standard private delivery route.
   */
  public function testPrivateTempLocationUnaffected(): void {
    \Drupal::configFactory()
      ->getEditable('filefield_paths.settings')
      ->set('temp_location', 'private://filefield_paths')
      ->save();

    $url = $this->style->buildUrl($this->imageUri);
    $this->assertStringNotContainsString('/filefield_paths/image-style/', $url);
  }

}
