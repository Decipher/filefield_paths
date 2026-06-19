<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\FieldConfigEditForm;

/**
 * Tests the field config edit form alter hook.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FieldConfigEditForm
 * @covers \Drupal\filefield_paths\Hook\FileFieldPathsFieldSettingsLegacy
 */
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
    $form = ['settings' => ['file_directory' => []], 'actions' => ['submit' => []]];

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
  }

  /**
   * Returns the field config edit form alter service.
   */
  protected function getFormAlterService(): FieldConfigEditForm {
    return $this->container->get(FieldConfigEditForm::class);
  }

}
