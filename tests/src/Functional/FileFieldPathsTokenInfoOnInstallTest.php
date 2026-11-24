<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests that the module's tokens are valid as soon as it is installed.
 *
 * @see https://www.drupal.org/i/1211546
 *
 * @group filefield_paths
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class FileFieldPathsTokenInfoOnInstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * The module under test is left out. The test installs it.
   *
   * @var array<string>
   */
  protected static $modules = [
    'node',
    'file',
    'image',
    'field_ui',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a pattern using the module's tokens saves after install.
   */
  public function testTokensValidAfterInstall(): void {
    $this->drupalCreateContentType(['type' => 'article']);
    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'type' => 'file',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'File',
    ])->save();

    // Build and cache token info before the module is installed. Any page
    // that shows a token browser or checks a token does this.
    $info = \Drupal::token()->getInfo();
    $this->assertArrayNotHasKey('ffp-name-only', $info['tokens']['file']);

    $this->drupalLogin($this->rootUser);

    // Install the module from the Extend page.
    $this->drupalGet('admin/modules');
    $this->submitForm(['modules[filefield_paths][enable]' => TRUE], 'Install');
    $this->assertSession()->pageTextContains('Module File (Field) Paths has been installed.');

    // Save a file name pattern that uses the module's own tokens.
    $pattern = '[file:ffp-name-only-original].[file:ffp-extension-original]';
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.field_file');
    $this->submitForm([
      'third_party_settings[filefield_paths][file_name][value]' => $pattern,
    ], 'Save settings');

    $this->assertSession()->pageTextNotContains('is using the following invalid tokens');
    $this->assertSession()->pageTextContains('Saved File configuration.');

    $field = FieldConfig::loadByName('node', 'article', 'field_file');
    $this->assertSame($pattern, $field->getThirdPartySetting('filefield_paths', 'file_name')['value']);
  }

}
