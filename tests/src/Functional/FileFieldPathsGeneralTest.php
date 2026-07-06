<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Test general functionality.
 *
 * @group filefield_paths
 * @runTestsInSeparateProcesses
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class FileFieldPathsGeneralTest extends FileFieldPathsTestBase {

  /**
   * Test that the File (Field) Paths UI works as expected.
   */
  public function testAddField(): void {
    $session = $this->assertSession();
    // Create a File field.
    $field_name = mb_strtolower($this->randomMachineName());
    $field_settings = ['file_directory' => 'fields/' . $field_name];
    $this->createFileField($field_name, 'node', $this->contentType, [], $field_settings);

    // Ensure File (Field) Paths settings are present.
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    // File (Field) Path settings are present.
    $session->responseContains('Enable File (Field) Paths?');

    // Ensure that 'Enable File (Field) Paths?' is a direct sibling of
    // 'File (Field) Path settings'.
    /** @var \Behat\Mink\Element\NodeElement[] $element */
    $element = $this->xpath("//div[contains(@class, :class)]/following-sibling::*[1][@id='edit-third-party-settings-filefield-paths--2']", [':class' => 'form-item-third-party-settings-filefield-paths-enabled']);
    $this->assertNotEmpty($element, 'Enable checkbox is next to settings fieldset.');

    // Ensure that the File path used the File directory as it's default value.
    $session->fieldValueEquals('third_party_settings[filefield_paths][file_path][value]', 'fields/' . $field_name);
  }

  /**
   * Test File (Field) Paths works as normal when no file uploaded.
   *
   * This test is simply to prove that there are no exceptions/errors when
   * submitting a form with no File (Field) Paths affected files attached.
   */
  public function testNoFile(): void {
    // Create a File field.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node without a file attached.
    $this->drupalGet('node/add/' . $this->contentType);
    $this->submitForm(
      ['title[0][value]' => $this->randomMachineName(8)],
      'Save'
    );
  }

  /**
   * Test a basic file upload with File (Field) Paths.
   */
  public function testUploadFile(): void {
    $session = $this->assertSession();
    $file_system = \Drupal::service('file_system');

    // Create a File field with 'node/[node:nid]' as the File path and
    // '[node:nid].[file:ffp-extension-original]' as the File name.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $this->drupalGet('node/add/' . $this->contentType);
    $edit['title[0][value]'] = $this->randomMachineName();
    $edit[sprintf('files[%s_0]', $field_name)] = $file_system->realpath($test_file->getFileUri());
    $this->submitForm($edit, 'Upload');

    // Ensure that the file was put into the Temporary file location.
    $config = $this->config('filefield_paths.settings');
    $session->responseContains(\Drupal::service('file_url_generator')->generateString(sprintf('%s/%s', $config->get('temp_location'), $test_file->getFilename())));

    // Save the node.
    $this->submitForm([], 'Save');

    // Get created Node ID.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches);
    $nid = $matches[1];

    // Ensure that the File path has been processed correctly.
    $session->responseContains(sprintf('%s/node/%s/%s.txt', $this->publicFilesDirectory, $nid, $nid));
  }

  /**
   * Test a file upload using a custom temporary location set on the field.
   */
  public function testUploadFileWithCustomTempLocation(): void {
    $session = $this->assertSession();
    $custom_dir = 'private://filefield_paths_custom';
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');

    // Create a File field with 'node/[node:nid]' as the File path and
    // '[node:nid].[file:ffp-extension-original]' as the File name.
    // Additionally, set a custom temporary upload location on the field
    // configuration to ensure it overrides the global setting.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $third_party_settings['filefield_paths']['temp_location'] = $custom_dir;
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Ensure the custom temporary directory exists and is writable.
    $file_system->prepareDirectory($custom_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $this->drupalGet('node/add/' . $this->contentType);
    $edit['title[0][value]'] = $this->randomMachineName();
    $edit[sprintf('files[%s_0]', $field_name)] = $file_system->realpath($test_file->getFileUri());
    $this->submitForm($edit, 'Upload');

    // Ensure that the file was put into the custom Temporary file location
    // defined on the field configuration (not the global setting).
    $generated_url = \Drupal::service('file_url_generator')->generateString($custom_dir . '/' . $test_file->getFilename());
    $session->responseContains($generated_url);

    // Save the node.
    $this->submitForm([], 'Save');

    // Get created Node ID.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches);
    $nid = $matches[1];

    // Ensure that the File path has been processed correctly after save.
    $session->responseContains(sprintf('%s/node/%s/%s.txt', $this->publicFilesDirectory, $nid, $nid));
  }

  /**
   * Tests a multivalue file upload with File (Field) Paths.
   */
  public function testUploadFileMultivalue(): void {
    $file_system = \Drupal::service('file_system');

    // Create a multivalue File field with 'node/[node:nid]' as the File path
    // and '[file:fid].txt' as the File name.
    $field_name = mb_strtolower($this->randomMachineName());
    $storage_settings['cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[file:fid].txt';
    $this->createFileField($field_name, 'node', $this->contentType, $storage_settings, [], [], $third_party_settings);

    // Create a node with three (3) test files.
    $text_files = $this->drupalGetTestFiles('text');
    $this->drupalGet('node/add/' . $this->contentType);
    $this->submitForm([sprintf('files[%s_0][]', $field_name) => $file_system->realpath($text_files[0]->uri)], 'Upload');
    $this->submitForm([sprintf('files[%s_1][]', $field_name) => $file_system->realpath($text_files[1]->uri)], 'Upload');
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      sprintf('files[%s_2][]', $field_name) => $file_system->realpath($text_files[1]->uri),
    ];
    $this->submitForm($edit, 'Save');

    // Get created Node ID.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches);
    $nid = $matches[1];

    $session = $this->assertSession();
    // Ensure that the File path has been processed correctly.
    $session->responseContains(sprintf('%s/node/%s/1.txt', $this->publicFilesDirectory, $nid));
    $session->responseContains(sprintf('%s/node/%s/2.txt', $this->publicFilesDirectory, $nid));
    $session->responseContains(sprintf('%s/node/%s/3.txt', $this->publicFilesDirectory, $nid));
  }

  /**
   * Tests that the field item delta is passed in token data.
   *
   * Ensures the [file:delta] token resolves to each file's zero-based position
   * within a multi-value field when used in a File (Field) Paths filename
   * pattern.
   *
   * @see https://www.drupal.org/project/filefield_paths/issues/3608361
   */
  public function testUploadFileDeltaToken(): void {
    $file_system = \Drupal::service('file_system');

    // Create a multivalue File field whose filename pattern uses [file:delta].
    $field_name = mb_strtolower($this->randomMachineName());
    $storage_settings['cardinality'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid]-[file:delta].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, $storage_settings, [], [], $third_party_settings);

    // Create a node with three (3) test files.
    $text_files = $this->drupalGetTestFiles('text');
    $this->drupalGet('node/add/' . $this->contentType);
    $this->submitForm([sprintf('files[%s_0][]', $field_name) => $file_system->realpath($text_files[0]->uri)], 'Upload');
    $this->submitForm([sprintf('files[%s_1][]', $field_name) => $file_system->realpath($text_files[1]->uri)], 'Upload');
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      sprintf('files[%s_2][]', $field_name) => $file_system->realpath($text_files[2]->uri),
    ];
    $this->submitForm($edit, 'Save');

    // Get created Node ID.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches);
    $nid = $matches[1];

    // Ensure each file received its zero-based delta in the filename.
    $session = $this->assertSession();
    $session->responseContains(sprintf('%s/node/%s/%s-0.txt', $this->publicFilesDirectory, $nid, $nid));
    $session->responseContains(sprintf('%s/node/%s/%s-1.txt', $this->publicFilesDirectory, $nid, $nid));
    $session->responseContains(sprintf('%s/node/%s/%s-2.txt', $this->publicFilesDirectory, $nid, $nid));
  }

  /**
   * Test File (Field) Paths with a very long path.
   */
  public function testLongPath(): void {
    // Create a File field with 'node/[random:hash:sha256]' as the File path.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[random:hash:sha512]/[random:hash:sha512]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $nid = $this->uploadNodeFile($test_file, $field_name, $this->contentType);

    // Ensure file path is no more than 255 characters.
    $node = $this->reloadNode((int) $nid);
    $this->assertLessThanOrEqual(255, mb_strlen((string) $node->{$field_name}[0]->entity->getFileUri()), 'File path is no more than 255 characters');
  }

  /**
   * Test File (Field) Paths on a programmatically added file.
   */
  public function testProgrammaticAttach(): void {
    // Create a File field with 'node/[node:nid]' as the File path and
    // '[node:nid].[file:ffp-extension-original]' as the File name.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node without an attached file.
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->drupalCreateNode(['type' => $this->contentType]);

    // Create a file object.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $test_file->setPermanent();
    $test_file->save();

    // Attach the file to the node.
    $node->{$field_name}->setValue([
      'target_id' => $test_file->id(),
    ]);
    $node->save();

    // Ensure that the File path has been processed correctly.
    $node = $this->reloadNode((int) $node->id());
    $this->assertSame(sprintf('public://node/%s/%s.txt', $node->id(), $node->id()), $node->{$field_name}[0]->entity->getFileUri(), 'The File path has been processed correctly.');
  }

  /**
   * Verifies URI update on programmatically saved files with timestamped names.
   *
   * This reproduces a scenario where the originally saved file has a name
   * containing a timestamp that differs from the timestamp used by
   * File (Field) Paths tokens at entity save time. The file should be moved to
   * the processed destination and the referenced file entity's URI should
   * reflect the new location.
   */
  public function testProgrammaticAttachWithTimestampedFilename(): void {
    // Configure a File field so that both path and filename are based on the
    // current timestamp at processing time.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[date:custom:YmdHis]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[date:custom:YmdHis].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node without an attached file.
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->drupalCreateNode(['type' => $this->contentType]);

    // Programmatically create and save a file whose name contains an older
    // timestamp to ensure it differs from the token-evaluated timestamp.
    $older_ts = (string) (\Drupal::time()->getRequestTime() - 5);
    $original_filename = sprintf('manual-%s.txt', $older_ts);
    $original_uri = 'public://' . $original_filename;
    // Ensure the directory exists and write the file.
    /** @var \Drupal\Core\File\FileSystemInterface $fs */
    $fs = \Drupal::service('file_system');
    $public_dir = 'public://';
    $fs->prepareDirectory($public_dir, FileSystemInterface::CREATE_DIRECTORY);
    $realpath = $fs->realpath($original_uri);
    $this->assertNotFalse($realpath);
    file_put_contents($realpath, 'Test content');

    /** @var \Drupal\file\Entity\File $file */
    $file = File::create([
      'uri' => $original_uri,
    ]);
    $file->setPermanent();
    $file->save();

    // Attach the pre-saved file to the node and save the node to trigger
    // File (Field) Paths processing.
    $node->{$field_name}->setValue([
      'target_id' => $file->id(),
    ]);
    $node->save();

    // Reload and verify that the file was moved and the URI updated to match
    // the processed timestamped path and filename.
    $node = $this->reloadNode((int) $node->id());
    $moved_uri = $node->{$field_name}[0]->entity->getFileUri();

    // It should be under public://node/<timestamp>/<timestamp>.txt and not the
    // original location.
    $this->assertNotSame($original_uri, $moved_uri, 'File URI changed from the original programmatic save.');

    $this->assertMatchesRegularExpression(
      '/^public:\/\/node\/(\d{14})\/\1\.txt$/',
      $moved_uri,
      'File moved to a timestamped directory and filename with matching timestamps.'
    );
  }

  /**
   * Test File (Field) Paths slashes cleanup functionality.
   */
  public function testSlashes(): void {
    $file_system = \Drupal::service('file_system');
    $etm = \Drupal::entityTypeManager();

    // Create a File field with 'node/[node:title]' as the File path and
    // '[node:title].[file:ffp-extension-original]' as the File name.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:title]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:title].[file:ffp-extension-original]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');

    $title = sprintf('%s/%s', $this->randomMachineName(), $this->randomMachineName());
    $edit['title[0][value]'] = $title;
    $edit["body[0][value]"] = '';
    $edit[sprintf('files[%s_0]', $field_name)] = $file_system->realpath($test_file->getFileUri());
    $this->drupalGet('node/add/' . $this->contentType);
    $this->submitForm($edit, 'Save');

    // Get created Node ID.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches);
    $nid = $matches[1];

    // Ensure slashes are present in file path and name.
    $node = $etm->getStorage('node')->load($nid);
    $this->assertSame(sprintf('public://node/%s/%s.txt', $title, $title), $node->get($field_name)->referencedEntities()[0]->getFileUri());

    // Remove slashes.
    $edit = [
      'third_party_settings[filefield_paths][file_path][options][slashes]' => TRUE,
      'third_party_settings[filefield_paths][file_name][options][slashes]' => TRUE,
      'third_party_settings[filefield_paths][retroactive_update]' => TRUE,
    ];
    $this->drupalGet(sprintf('admin/structure/types/manage/%s/fields/node.%s.%s', $this->contentType, $this->contentType, $field_name));
    $this->submitForm($edit, 'Save settings');
    $etm->getStorage('file')
      ->resetCache([$node->{$field_name}->target_id]);
    $node_storage = $etm->getStorage('node');
    $node_storage->resetCache([$nid]);

    // Ensure slashes are not present in file path and name.
    $node = $node_storage->load($nid);
    $title = str_replace('/', '', $title);
    $this->assertSame(sprintf('public://node/%s/%s.txt', $title, $title), $node->{$field_name}[0]->entity->getFileUri());
  }

  /**
   * Test a file usage of a basic file upload with File (Field) Paths.
   */
  public function testFileUsage(): void {
    /** @var \Drupal\node\NodeStorage $node_storage */
    $node_storage = \Drupal::service('entity_type.manager')
      ->getStorage('node');
    /** @var \Drupal\file\FileUsage\FileUsageInterface $file_usage */
    $file_usage = \Drupal::service('file.usage');

    // Create a File field with 'node/[node:nid]' as the File path.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a node with a test file.
    /** @var \Drupal\file\Entity\File $test_file */
    $test_file = $this->getTestFile('text');
    $nid = $this->uploadNodeFile($test_file, $field_name, $this->contentType);

    // Get file usage for uploaded file.
    $node_storage->resetCache([$nid]);
    $node = $node_storage->load($nid);
    $file = $node->{$field_name}->entity;
    $usage = $file_usage->listUsage($file);

    // Ensure file usage count for new node is correct.
    $this->assertNotEmpty($usage['file']['node'][$nid]);
    $this->assertSame(1, (int) $usage['file']['node'][$nid], 'File usage count for new node is correct.');

    // Update node.
    $this->drupalGet(sprintf('node/%d/edit', $nid));
    $this->submitForm(['revision' => FALSE], 'Save');
    $usage = $file_usage->listUsage($file);

    // Ensure file usage count for updated node is correct.
    $this->assertNotEmpty($usage['file']['node'][$nid]);
    $this->assertSame(1, (int) $usage['file']['node'][$nid], 'File usage count for updated node is correct.');

    // Update node with revision.
    $this->drupalGet(sprintf('node/%d/edit', $nid));
    $this->submitForm(['revision' => TRUE], 'Save');
    $usage = $file_usage->listUsage($file);

    // Ensure file usage count for updated node with revision is correct.
    $this->assertNotEmpty($usage['file']['node'][$nid]);
    $this->assertSame(2, (int) $usage['file']['node'][$nid], 'File usage count for updated node with revision is correct.');
  }

  /**
   * Test File (Field) Paths works with read-only stream wrappers.
   */
  public function testReadOnly(): void {
    $this->markTestIncomplete('A readonly stream wrapper is no longer a valid choice as upload destination. See ' . FileItem::class . '::storageSettingsForm().');
  }

  /**
   * Test case that creates an entity with a pre-uploaded file.
   */
  public function testPreUploadedFile(): void {
    // Enable file field paths functionality.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['enabled'] = TRUE;
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $third_party_settings['filefield_paths']['active_updating'] = TRUE;
    $this->createFileField($field_name, 'node', $this->contentType, [], [], [], $third_party_settings);

    // Create a temporary test file (simulating a pre-uploaded file).
    $test_file = $this->getTestFile('text');
    $test_file->setPermanent();
    $test_file->save();

    // Now that the file is saved, we use its ID to associate it with the node.
    // create a node with the pre-uploaded file.
    $node = $this->drupalCreateNode([
      'type' => $this->contentType,
      'title' => $this->randomMachineName(),
      $field_name => [
        'target_id' => $test_file->id(),
        'display' => 1,
      ],
    ]);

    // Reload the node to verify the file path.
    $node = $this->reloadNode((int) $node->id());
    $expected_uri = sprintf('public://node/%s/%s.txt', $node->id(), $node->id());
    $this->assertSame($expected_uri, $node->{$field_name}[0]->entity->getFileUri(), 'The pre-uploaded file path has been processed correctly.');

    // Update the node to ensure the file remains in the correct location.
    $node->setTitle($this->randomMachineName());
    $node->save();

    // Reload the node again and verify the file path remains the same.
    $node = $this->reloadNode((int) $node->id());
    $this->assertSame($expected_uri, $node->{$field_name}[0]->entity->getFileUri(), 'The file path remains correct after node update.');
  }

  /**
   * Test that an image style derivative is generated for an uploaded image.
   */
  public function testImageStyleDerivativeGeneration(): void {
    $file_system = \Drupal::service('file_system');

    // Create a simple image style programmatically to avoid relying on
    // pre-existing configuration.
    $style_id = 'ffp_test_style';
    if (!ImageStyle::load($style_id)) {
      $style = ImageStyle::create([
        'name' => $style_id,
        'label' => 'FFP Test style',
      ]);
      $style->addImageEffect([
        'id' => 'image_scale',
        'data' => ['width' => 50, 'height' => 50, 'upscale' => FALSE],
        'weight' => 0,
      ]);
      $style->save();
    }

    // Create an Image field and enable File (Field) Paths to move the file on
    // save, to ensure derivatives work with the finalized URI.
    $field_name = mb_strtolower($this->randomMachineName());
    $third_party_settings['filefield_paths']['enabled'] = TRUE;
    $third_party_settings['filefield_paths']['file_path']['value'] = 'node/[node:nid]';
    $third_party_settings['filefield_paths']['file_name']['value'] = '[node:nid].[file:ffp-extension-original]';
    $this->createImageField($field_name, $this->contentType, [], [], $third_party_settings);

    // Configure the view display to use the created image style.
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $edr */
    $edr = \Drupal::service('entity_display.repository');
    $edr->getViewDisplay('node', $this->contentType, 'default')
      ->setComponent($field_name, [
        'type' => 'image',
        'settings' => [
          'image_style' => $style_id,
        ],
      ])
      ->save();

    // Upload an image via the node add form and save the node.
    /** @var \Drupal\file\Entity\File $test_image */
    $test_image = $this->getTestFile('image');
    $this->drupalGet('node/add/' . $this->contentType);
    $edit = [
      'title[0][value]' => $this->randomMachineName(),
      sprintf('files[%s_0]', $field_name) => $file_system->realpath($test_image->getFileUri()),
    ];
    $this->submitForm($edit, 'Upload');
    // Provide required ALT text for the uploaded image to allow saving.
    $this->submitForm([
      $field_name . '[0][alt]' => 'Test alt',
    ], 'Save');

    // Load the node and resolve the original image URI.
    $matches = [];
    preg_match('/node\/(\d+)/', $this->getUrl(), $matches);
    $this->assertNotEmpty($matches[1] ?? NULL, 'A node was created and its ID could be detected from the URL: ' . $this->getUrl());
    $nid = (int) $matches[1];
    $node = $this->reloadNode($nid);
    $image_file = $node->{$field_name}[0]->entity;
    $original_uri = $image_file->getFileUri();

    // Build the derivative URL and request it to trigger generation.
    $style = ImageStyle::load($style_id);
    $derivative_url = $style->buildUrl($original_uri);
    $this->drupalGet($derivative_url);
    $this->assertSession()->statusCodeEquals(200);

    $derivative_uri = $style->buildUri($original_uri);
    // Ensure the derivative file exists on disk.
    $this->assertFileExists($derivative_uri, 'Image style derivative has been generated.');
  }

  /**
   * Loads the node from the database.
   *
   * On the node storage, caches are cleared to ensure the data is loaded from
   * the database instead of from memory.
   *
   * @param int $nid
   *   The ID from the node to load.
   *
   * @return \Drupal\node\NodeInterface
   *   The loaded node.
   */
  protected function reloadNode(int $nid): NodeInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache();
    $node = $storage->load($nid);
    $this->assertInstanceOf(NodeInterface::class, $node);
    return $node;
  }

}
