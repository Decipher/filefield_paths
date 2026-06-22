<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Tests LegacyHook delegation functions in filefield_paths.module.
 *
 * Each function should delegate to the corresponding service method.
 *
 * @group filefield_paths
 */
#[Group('filefield_paths')]
class ModuleLegacyHooksTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'field',
    'entity_test',
    'filefield_paths',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filefield_paths']);

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests that entity_base_field_info adds origname to file entities.
   */
  public function testEntityBaseFieldInfoForFileEntityType(): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('file');
    $fields = filefield_paths_entity_base_field_info($entity_type);
    $this->assertArrayHasKey('origname', $fields);
  }

  /**
   * Tests that entity_base_field_info returns empty for non-file entities.
   */
  public function testEntityBaseFieldInfoForNonFileEntityType(): void {
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('user');
    $fields = filefield_paths_entity_base_field_info($entity_type);
    $this->assertSame([], $fields);
  }

  /**
   * Tests that token_info returns filefield_paths tokens.
   */
  public function testTokenInfo(): void {
    $info = filefield_paths_token_info();
    $this->assertArrayHasKey('tokens', $info);
    $this->assertArrayHasKey('ffp-name-only', $info['tokens']['file']);
    $this->assertArrayHasKey('ffp-name-only-original', $info['tokens']['file']);
    $this->assertArrayHasKey('ffp-extension-original', $info['tokens']['file']);
  }

  /**
   * Tests that tokens() returns correct replacements for file tokens.
   */
  public function testTokens(): void {
    $file = File::create([
      'uri' => 'public://document.pdf',
      'filename' => 'document.pdf',
      'origname' => 'original-name.pdf',
    ]);

    $replacements = filefield_paths_tokens(
      'file',
      [
        'ffp-name-only' => '[file:ffp-name-only]',
        'ffp-name-only-original' => '[file:ffp-name-only-original]',
        'ffp-extension-original' => '[file:ffp-extension-original]',
      ],
      ['file' => $file],
      [],
      new BubbleableMetadata(),
    );

    $this->assertSame('document', $replacements['[file:ffp-name-only]']);
    $this->assertSame('original-name', $replacements['[file:ffp-name-only-original]']);
    $this->assertSame('pdf', $replacements['[file:ffp-extension-original]']);
  }

  /**
   * Tests that tokens() handles filenames without an extension.
   */
  public function testTokensNoExtension(): void {
    $file = File::create([
      'uri' => 'public://readme',
      'filename' => 'readme',
      'origname' => 'readme',
    ]);

    $replacements = filefield_paths_tokens(
      'file',
      [
        'ffp-name-only' => '[file:ffp-name-only]',
        'ffp-name-only-original' => '[file:ffp-name-only-original]',
        'ffp-extension-original' => '[file:ffp-extension-original]',
      ],
      ['file' => $file],
      [],
      new BubbleableMetadata(),
    );

    $this->assertSame('readme', $replacements['[file:ffp-name-only]']);
    $this->assertSame('readme', $replacements['[file:ffp-name-only-original]']);
    $this->assertSame('readme', $replacements['[file:ffp-extension-original]']);
  }

  /**
   * Tests that tokens() returns empty for non-file token types.
   */
  public function testTokensNonFileType(): void {
    $replacements = filefield_paths_tokens(
      'node',
      ['ffp-name-only' => '[node:ffp-name-only]'],
      [],
      [],
      new BubbleableMetadata(),
    );

    $this->assertSame([], $replacements);
  }

  /**
   * Tests that file_presave does not override an existing origname.
   */
  public function testFilePresaveDoesNotOverrideExistingOrigname(): void {
    $file = File::create([
      'uri' => 'public://test.txt',
      'filename' => 'changed.txt',
      'origname' => 'original.txt',
    ]);

    $this->assertFalse($file->origname->isEmpty());

    filefield_paths_file_presave($file);

    $this->assertSame('original.txt', $file->origname->value);
  }

  /**
   * Tests that filefield_paths_field_settings returns expected form elements.
   */
  public function testFileFieldPathsFieldSettings(): void {
    $form = [
      'settings' => [
        'file_directory' => [
          '#default_value' => '[date:custom:Y-m]',
          '#element_validate' => [],
        ],
      ],
    ];
    $settings = filefield_paths_filefield_paths_field_settings($form);
    $this->assertArrayHasKey('file_path', $settings);
    $this->assertArrayHasKey('file_name', $settings);
  }

  /**
   * Tests that file_presave sets origname from filename for new files.
   */
  public function testFilePresaveSetsOrigname(): void {
    $file = File::create([
      'uri' => 'public://test.txt',
      'filename' => 'test.txt',
    ]);

    $this->assertTrue($file->origname->isEmpty());

    filefield_paths_file_presave($file);

    $this->assertSame('test.txt', $file->origname->value);
  }

  /**
   * Tests that local_tasks_alter returns early when filesystem route exists.
   */
  public function testLocalTasksAlterEarlyReturn(): void {
    $local_tasks = [
      'system.file_system_settings' => [
        'route_name' => 'system.file_system_settings',
      ],
    ];
    filefield_paths_local_tasks_alter($local_tasks);
    $this->assertCount(1, $local_tasks);
  }

  /**
   * Tests that local_tasks_alter adds the filesystem settings tab.
   */
  public function testLocalTasksAlterAddsTask(): void {
    $local_tasks = [
      'filefield_paths.admin_settings' => [
        'route_name' => 'filefield_paths.admin_settings',
        'base_route' => 'filefield_paths.admin_settings',
        'title' => 'File (Field) Paths',
        'id' => 'filefield_paths.admin_settings',
      ],
    ];
    filefield_paths_local_tasks_alter($local_tasks);
    $this->assertArrayHasKey('system.file_system_settings', $local_tasks);
  }

  /**
   * Tests that form_field_config_edit_form_alter delegates to the service.
   *
   * With a non-EntityFormInterface form object the service returns early.
   */
  public function testFormFieldConfigEditFormAlterEarlyReturn(): void {
    $form_state = new FormState();
    $form_state->setFormObject($this->createMock(FormInterface::class));
    $form = [];

    filefield_paths_form_field_config_edit_form_alter($form, $form_state);

    $this->assertArrayNotHasKey('settings', $form);
  }

  /**
   * Tests that field_widget_single_element_form_alter delegates.
   *
   * With an unsupported element type the service returns early.
   */
  public function testFieldWidgetSingleElementFormAlterEarlyReturn(): void {
    $element = [];
    filefield_paths_field_widget_single_element_form_alter(
      $element,
      new FormState(),
      []
    );

    $this->assertArrayNotHasKey('#upload_location', $element);
  }

  /**
   * Tests that entity_insert delegates to the service.
   */
  public function testEntityInsertDelegates(): void {
    $entity = EntityTest::create([]);
    filefield_paths_entity_insert($entity);
  }

  /**
   * Tests that entity_update delegates to the service.
   */
  public function testEntityUpdateDelegates(): void {
    $entity = EntityTest::create([]);
    $entity->save();
    filefield_paths_entity_update($entity);
  }

  /**
   * Tests that filefield_paths_process_file delegates to the service.
   *
   * With no files attached the service iterates an empty list and returns.
   */
  public function testFileFieldPathsProcessFileDelegates(): void {
    $entity = EntityTest::create([]);
    $entity->save();
    $field = $entity->get('field_file');
    \assert($field instanceof FileFieldItemList);

    $settings = [];
    filefield_paths_filefield_paths_process_file($entity, $field, $settings);
  }

}
