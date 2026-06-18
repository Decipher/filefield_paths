<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use Drupal\Component\Utility\Unicode;

/**
 * Test transliteration functionality.
 *
 * @group filefield_paths
 * @runTestsInSeparateProcesses
 */
class FileFieldPathsTransliterationTest extends FileFieldPathsTestBase {

  /**
   * Test File (Field) Paths Transliteration UI.
   */
  public function testUi(): void {
    // Create a File field.
    $field_name = mb_strtolower($this->randomMachineName());
    $this->createFileField($field_name, 'node', $this->contentType);

    // Ensure File (Field) Paths Transliteration settings are present and
    // available.
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    foreach (['path', 'name'] as $field) {
      // Transliteration checkbox is present in File settings.
      $this->assertSession()->fieldExists(sprintf('third_party_settings[filefield_paths][file_%s][options][transliterate]', $field));

      $element = $this->xpath('//input[@name=:name]/@disabled', [':name' => sprintf('third_party_settings[filefield_paths][file_%s][options][transliterate]', $field)]);
      $this->assertEmpty($element, 'Transliteration checkbox is not disabled in File ' . Unicode::ucfirst($field) . ' settings.');
    }
  }

  /**
   * Test Transliteration cleanup in File (Field) Paths.
   */
  public function testTransliteration(): void {
    // Create a File field.
    $field_name = mb_strtolower($this->randomMachineName());

    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:title]';
    $third_party_settings['filefield_paths']['file_path']['options']['transliterate'] = TRUE;
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:title].[file:ffp-extension-original]';
    $third_party_settings['filefield_paths']['file_name']['options']['transliterate'] = TRUE;

    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $edit['title[0][value]'] = 'тест';

    $edit['files[' . $field_name . '_0]'] = \Drupal::service('file_system')
      ->realpath($test_file->getFileUri());
    $this->drupalGet('node/add/' . $this->contentType);
    $this->submitForm($edit, 'Save');

    // Get created Node ID.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches);
    $nid = $matches[1];

    // Ensure that file path/name have been processed correctly by
    // Transliteration.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $this->assertEquals("public://node/test/test.txt", $node->{$field_name}[0]->entity->getFileUri(), 'File path/name has been processed correctly by Transliteration');
  }

}
