<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use PHPUnit\Framework\Attributes\Group;

/**
 * Test update functionality.
 *
 * @group filefield_paths
 * @runTestsInSeparateProcesses
 */
#[Group('filefield_paths')]
class FileFieldPathsUpdateTest extends FileFieldPathsTestBase {

  /**
   * Test behavior of Retroactive updates when no updates are needed.
   */
  public function testRetroEmpty(): void {
    // Create a File field.
    $field_name = mb_strtolower($this->randomMachineName());
    $this->createFileField($field_name, 'node', $this->contentType);

    // Trigger retroactive updates.
    $edit = [
      'third_party_settings[filefield_paths][retroactive_update]' => TRUE,
    ];
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    $this->submitForm($edit, 'Save settings');

    // Ensure that no errors are thrown.
    // No errors were found.
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error.');
    $this->assertSession()->pageTextContains(sprintf('Saved %s configuration.', $field_name));
  }

  /**
   * Test basic Retroactive updates functionality.
   */
  public function testRetroBasic(): void {
    // Create an Image field.
    $field_name = mb_strtolower($this->randomMachineName());
    $this->createImageField($field_name, $this->contentType, []);

    // Modify display settings.
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load(sprintf('node.%s.default', $this->contentType));
    $display->setComponent($field_name, [
      'settings' => [
        'image_style' => 'thumbnail',
        'image_link'  => 'content',
      ],
    ])->save();

    $this->drupalGet(sprintf('admin/structure/types/manage/%s/display', $this->contentType));
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $original_display */
    $original_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load(sprintf('node.%s.default', $this->contentType));

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('image');
    $nid = $this->uploadNodeFile($test_file, $field_name, $this->contentType);
    $this->submitForm([$field_name . '[0][alt]' => $this->randomString()], 'Save');

    // Ensure that the file is in the default path.
    $this->drupalGet('node/' . $nid);
    $date = date('Y-m');
    // The File is in the default path.
    $this->assertSession()->responseContains(sprintf('%s/styles/thumbnail/public/%s/%s', $this->publicFilesDirectory, $date, $test_file->getFilename()));

    // Trigger retroactive updates.
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    $edit['third_party_settings[filefield_paths][retroactive_update]'] = TRUE;
    $edit['third_party_settings[filefield_paths][file_path][value]'] = 'node/[node:nid]';
    $this->submitForm($edit, 'Save settings');

    // Ensure display settings haven't changed.
    // @see https://www.drupal.org/node/2276435
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load(sprintf('node.%s.default', $this->contentType));
    $this->assertSame($original_display->getComponent($field_name), $display->getComponent($field_name), 'Display settings have not changed.');

    // Ensure that the file path has been retroactively updated.
    $this->drupalGet('node/' . $nid);
    // The File path has been retroactively updated.
    $this->assertSession()->responseContains(sprintf('%s/styles/thumbnail/public/node/%d/%s', $this->publicFilesDirectory, $nid, $test_file->getFilename()));
  }

}
