<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\FieldConfigEditForm;

/**
 * Tests the field config edit form alter hook.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FieldConfigEditForm
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsFieldSettingsLegacy
 */
#[Group('filefield_paths')]
#[RunTestsInSeparateProcesses]
class FieldConfigEditFormTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'filefield_paths',
  ];

  /**
   * Tests that forms for non-EntityFormInterface form objects are untouched.
   */
  public function testFormAlterSkipsNonEntityForm(): void {
    $form_state = new FormState();
    $form_state->setFormObject($this->createMock(FormInterface::class));
    $form = ['settings' => []];

    $this->getFormAlterService()->formAlter($form, $form_state);

    $this->assertArrayNotHasKey('filefield_paths', $form['settings']);
  }

  /**
   * Tests that forms for non-file field types are untouched.
   */
  public function testFormAlterSkipsUnsupportedFieldType(): void {
    $field = $this->createMock(FieldConfig::class);
    $field->method('getClass')->willReturn(\stdClass::class);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($field);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form = ['settings' => []];

    $this->getFormAlterService()->formAlter($form, $form_state);

    $this->assertArrayNotHasKey('filefield_paths', $form['settings']);
  }

  /**
   * Tests the form is built for a supported file field, with no add-ons.
   */
  public function testFormAlterBuildsSettingsForFileField(): void {
    $field = $this->createMock(FieldConfig::class);
    $field->method('getClass')->willReturn(FileFieldItemList::class);
    $field->method('getTargetEntityTypeId')->willReturn('user');
    $field->method('getThirdPartySettings')->with('filefield_paths')->willReturn([
      'enabled' => TRUE,
      'redirect' => TRUE,
      'file_path' => [
        'value' => '[node:title]',
        'options' => ['slashes' => TRUE],
      ],
    ]);

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($field);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form = [
      'settings' => ['file_directory' => []],
      'actions' => ['submit' => ['#ajax' => ['callback' => '::ajaxSubmit']]],
    ];

    $this->getFormAlterService()->formAlter($form, $form_state);

    $details = $form['settings']['filefield_paths']['details'];
    // Provided by FileFieldPathsFieldSettingsLegacy.
    $this->assertArrayHasKey('file_path', $details);
    $this->assertArrayHasKey('file_name', $details);
    $this->assertSame('[node:title]', $details['file_path']['value']['#default_value']);
    $this->assertTrue($details['file_path']['options']['slashes']['#default_value']);

    // Pathauto is not enabled, so the option is present but disabled.
    $this->assertTrue($details['file_path']['options']['pathauto']['#disabled']);

    // Redirect module is not enabled, so the checkbox is disabled regardless
    // of the stored setting.
    $this->assertTrue($details['redirect']['#disabled']);

    $this->assertSame([[FieldConfigEditForm::class, 'submit']], $form['actions']['submit']['#submit']);

    // The AJAX callback is swapped for the batch-aware one.
    $this->assertSame([FieldConfigEditForm::class, 'ajaxSubmit'], $form['actions']['submit']['#ajax']['callback']);
  }

  /**
   * Tests ajaxSubmit() redirects to the batch page when a batch is waiting.
   */
  public function testAjaxSubmitRedirectsToPendingBatch(): void {
    $batch = &batch_get();
    $batch = [
      'id' => 42,
      'progressive' => TRUE,
      'url' => Url::fromUri('base:/batch', ['query' => ['id' => 42, 'op' => 'start']]),
    ];

    $form = [];
    $response = FieldConfigEditForm::ajaxSubmit($form, new FormState());

    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('redirect', $commands[0]['command']);
    $this->assertStringContainsString('/batch?id=42&op=start', $commands[0]['url']);
  }

  /**
   * Tests ajaxSubmit() hands over to core's callback when no batch is set.
   */
  public function testAjaxSubmitDelegatesToCoreWithoutBatch(): void {
    if (version_compare(\Drupal::VERSION, '11.2.0', '<')) {
      // Core added AJAX submission and its ajaxSubmit() callback to the
      // field settings form in 11.2.0. On older versions the form alter
      // never installs our callback, so the delegate path cannot run.
      $this->markTestSkipped('Core field settings form has no AJAX submission before Drupal 11.2.');
    }
    $this->enableModules(['entity_test', 'field_ui']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => 'field_file',
      'entity_type' => 'entity_test',
      'type' => 'file',
    ])->save();
    $field_config = FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_file',
      'bundle' => 'entity_test',
    ]);
    $field_config->save();

    // Field UI derives the field overview route from the entity type's
    // base route. Core's callback builds its redirect from it.
    $this->container->get('router.builder')->rebuild();

    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('field_config', 'edit');
    $form_object->setEntity($field_config);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);

    $form = [];
    $response = FieldConfigEditForm::ajaxSubmit($form, $form_state);

    $commands = $response->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('redirect', $commands[0]['command']);
    $this->assertStringNotContainsString('/batch', $commands[0]['url']);
  }

  /**
   * Returns the field config edit form alter service.
   */
  protected function getFormAlterService(): FieldConfigEditForm {
    return $this->container->get(FieldConfigEditForm::class);
  }

}
