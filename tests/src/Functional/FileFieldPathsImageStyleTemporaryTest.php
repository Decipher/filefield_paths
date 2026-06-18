<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests on-demand image style delivery for temporary:// staged files.
 *
 * @group filefield_paths
 */
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
   * Tests private:// temp location leaves private delivery intact.
   *
   * The alter hook only matches temporary:// derivative URIs, so switching
   * the temp location to private:// must not trigger the FFP temporary route.
   */
  public function testPrivateTempLocationUnaffected(): void {
    \Drupal::configFactory()
      ->getEditable('filefield_paths.settings')
      ->set('temp_location', 'private://filefield_paths')
      ->save();

    $private_uri = 'private://filefield_paths/image-1.png';
    $derivative_uri = $this->style->buildUri($private_uri);
    $this->assertStringStartsWith('private://', $derivative_uri);
  }

}
