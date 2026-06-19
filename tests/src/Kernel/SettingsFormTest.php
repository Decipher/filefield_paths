<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\filefield_paths\Form\SettingsForm;

/**
 * Tests the settings form.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Form\SettingsForm
 */
class SettingsFormTest extends KernelTestBase {

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
    'filefield_paths',
  ];

  /**
   * The form under test.
   */
  protected SettingsForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filefield_paths']);
    $this->form = SettingsForm::create($this->container);
  }

  /**
   * Tests the form ID.
   */
  public function testGetFormId(): void {
    $this->assertSame('filefield_paths_settings_form', $this->form->getFormId());
  }

  /**
   * Tests that buildForm sets the default temp_location value.
   */
  public function testBuildFormSetsDefaultTempLocation(): void {
    $form = $this->form->buildForm([], new FormState());
    $this->assertArrayHasKey('temp_location', $form);
    $this->assertNotEmpty($form['temp_location']['#default_value']);
    $this->assertStringContainsString('://', $form['temp_location']['#default_value']);
  }

  /**
   * Tests that buildForm uses the stored config value when set.
   */
  public function testBuildFormUsesStoredConfig(): void {
    $this->config('filefield_paths.settings')
      ->set('temp_location', 'private://custom')
      ->save();

    $form = $this->form->buildForm([], new FormState());
    $this->assertSame('private://custom', $form['temp_location']['#default_value']);
  }

  /**
   * Tests that submitForm persists the value.
   */
  public function testSubmitFormSavesValue(): void {
    $form_state = new FormState();
    $form_state->setValues(['temp_location' => 'temporary://my-path']);

    $form = [];
    $this->form->submitForm($form, $form_state);

    $this->assertSame('temporary://my-path', $this->config('filefield_paths.settings')->get('temp_location'));
  }

  /**
   * Tests that validateForm rejects a missing scheme.
   */
  public function testValidateRejectsMissingScheme(): void {
    $form_state = new FormState();
    $form_state->setValues(['temp_location' => 'no-scheme-here']);

    $form = [];
    $this->form->validateForm($form, $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * Tests that validateForm accepts a valid scheme.
   */
  public function testValidateAcceptsValidScheme(): void {
    $form_state = new FormState();
    $form_state->setValues(['temp_location' => 'temporary://filefield_paths']);

    $form = [];
    $this->form->validateForm($form, $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
  }

  /**
   * Tests that validateForm rejects an unregistered scheme.
   */
  public function testValidateRejectsInvalidScheme(): void {
    $form_state = new FormState();
    $form_state->setValues(['temp_location' => 'unknown://path']);

    $form = [];
    $this->form->validateForm($form, $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
  }

}
