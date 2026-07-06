<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\redirect\Entity\Redirect;

/**
 * Test redirect module integration.
 *
 * @group filefield_paths
 * @runTestsInSeparateProcesses
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class FileFieldPathsRedirectTest extends FileFieldPathsTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'filefield_paths_test',
    'file_test',
    'image',
    'redirect',
    'token',
  ];

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test File (Field) Paths Redirect UI.
   */
  public function testUi(): void {
    // Create a File field.
    $field_name = mb_strtolower($this->randomMachineName());
    $this->createFileField($field_name, 'node', $this->contentType);

    // Ensure File (Field) Paths Pathauto settings are present and available.
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    // Redirect checkbox is present in File (Field) Path settings.
    $this->assertSession()
      ->fieldExists('third_party_settings[filefield_paths][redirect]');

    $element = $this->xpath('//input[@name=:name]/@disabled', [':name' => 'third_party_settings[filefield_paths][redirect]']);
    $this->assertEmpty($element, 'Redirect checkbox is not disabled.');
  }

  /**
   * Test File (Field) Paths Redirect functionality.
   */
  public function testRedirect(): void {
    // Get the public file path.
    $public_path = PublicStream::basePath();

    // Create a File field with a random File path.
    $source_dir = $this->randomMachineName();
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = $source_dir;
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $this->uploadNodeFile($test_file, $field_name, $this->contentType);

    // Update file path and create redirect.
    $destination_dir = $this->randomMachineName();
    $edit = [
      'third_party_settings[filefield_paths][file_path][value]' => $destination_dir,
      'third_party_settings[filefield_paths][redirect]' => TRUE,
      'third_party_settings[filefield_paths][retroactive_update]' => TRUE,
    ];
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    $this->submitForm($edit, 'Save settings');
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));

    // Check if a redirect has been created.
    $expected_redirect_source = $public_path . '/' . $source_dir . '/text-0.txt';
    $expected_redirect_destination = 'internal:/' . $public_path . '/' . $destination_dir . '/text-0.txt';
    $redirects = redirect_repository()->findBySourcePath($expected_redirect_source);
    $redirect = reset($redirects);
    $this->assertInstanceOf(Redirect::class, $redirect);
    $this->assertSame($expected_redirect_destination, $redirect->getRedirect()['uri'], 'Redirect created for relocated file.');
  }

}
