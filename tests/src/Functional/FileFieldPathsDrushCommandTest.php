<?php

namespace Drupal\Tests\filefield_paths\Functional;

use Drush\TestTraits\DrushTestTrait;

/**
 * Functional tests for the File (Field) Paths Drush command.
 *
 * @group filefield_paths
 * @runTestsInSeparateProcesses
 */
class FileFieldPathsDrushCommandTest extends FileFieldPathsTestBase {

  use DrushTestTrait;

  /**
   * Tests running the Drush command for a specific field instance.
   */
  public function testUpdateForSpecificField(): void {
    // Create a File field with simple File (Field) Paths settings so that
    // there is at least one entity with a file to be processed by the batch.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with an attached file so Updater::batchUpdate() finds
    // content to process.
    $file_system = \Drupal::service('file_system');
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $this->drupalGet("node/add/{$this->contentType}");
    $edit['title[0][value]'] = $this->randomMachineName();
    $edit["files[{$field_name}_0]"] = $file_system->realpath($test_file->getFileUri());
    $this->submitForm($edit, 'Upload');
    $this->submitForm([], 'Save');

    // Run the Drush command targeting the exact instance.
    $this->drush('ffpu', ['node', $this->contentType, $field_name]);

    // Ensure the success message from the command was produced.
    // The exact label formatting varies; assert the stable suffix.
    $output = $this->getErrorOutput() . "\n" . $this->getOutput();
    $this->assertStringContainsString('File (Field) Paths updated.', $output);
  }

  /**
   * Tests running the Drush command with the --all option.
   */
  public function testUpdateAllOption(): void {
    // Create a File field and one node with a file to ensure there is work.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    $file_system = \Drupal::service('file_system');
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $this->drupalGet("node/add/{$this->contentType}");
    $edit['title[0][value]'] = $this->randomMachineName();
    $edit["files[{$field_name}_0]"] = $file_system->realpath($test_file->getFileUri());
    $this->submitForm($edit, 'Upload');
    $this->submitForm([], 'Save');

    // Run the Drush command for all instances.
    $this->drush('ffpu', [], ['all' => TRUE]);

    $output = $this->getErrorOutput() . "\n" . $this->getOutput();
    $this->assertStringContainsString('File (Field) Paths updated.', $output);
  }

}
