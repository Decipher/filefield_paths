<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\FieldWidgetSingleElementForm;

/**
 * Tests the field widget single element form alter hook.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\FieldWidgetSingleElementForm
 */
#[Group('filefield_paths')]
class FieldWidgetSingleElementFormTest extends UnitTestCase {

  /**
   * Builds a hook instance with a mocked `filefield_paths.settings` config.
   *
   * @param bool $global_enabled
   *   The value the mocked `enabled` config key returns.
   * @param string|null $temp_location
   *   The value the mocked `temp_location` config key returns.
   */
  private function createHook(bool $global_enabled = TRUE, ?string $temp_location = NULL): FieldWidgetSingleElementForm {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['enabled', $global_enabled],
      ['temp_location', $temp_location],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('filefield_paths.settings')->willReturn($config);

    return new FieldWidgetSingleElementForm(static fn (): MockObject => $config_factory);
  }

  /**
   * Tests that unsupported widgets are left untouched.
   */
  public function testUnsupportedWidgetIsUntouched(): void {
    $hook = $this->createHook();

    $element = ['#type' => 'textfield'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), []);

    $this->assertArrayNotHasKey('#upload_location', $element);
  }

  /**
   * Tests that the field-level temp location wins when set.
   */
  public function testUsesFieldLevelTempLocation(): void {
    $hook = $this->createHook();

    $items = $this->buildFileFieldItemList(['enabled' => TRUE, 'temp_location' => 'private://custom']);
    $element = ['#type' => 'managed_file'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), ['items' => $items]);

    $this->assertSame('private://custom', $element['#upload_location']);
  }

  /**
   * Tests that the global setting is used when no field-level value is set.
   */
  public function testFallsBackToGlobalTempLocation(): void {
    $hook = $this->createHook(temp_location: 'temporary://filefield_paths');

    $items = $this->buildFileFieldItemList(['enabled' => TRUE]);
    $element = ['#type' => 'managed_file'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), ['items' => $items]);

    $this->assertSame('temporary://filefield_paths', $element['#upload_location']);
  }

  /**
   * Tests that a disabled field is left untouched.
   */
  public function testDisabledFieldIsUntouched(): void {
    $hook = $this->createHook();

    $items = $this->buildFileFieldItemList(['enabled' => FALSE]);
    $element = ['#type' => 'managed_file'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), ['items' => $items]);

    $this->assertArrayNotHasKey('#upload_location', $element);
  }

  /**
   * Tests that disabling File (Field) Paths site-wide skips the redirect.
   *
   * Otherwise an upload would be staged in the temporary location and never
   * moved out of it, since EntityWithFileField::handleProcessFile() also
   * skips processing entirely when disabled site-wide.
   */
  public function testGloballyDisabledIsUntouched(): void {
    $hook = $this->createHook(global_enabled: FALSE);

    $items = $this->buildFileFieldItemList(['enabled' => TRUE, 'temp_location' => 'private://custom']);
    $element = ['#type' => 'managed_file'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), ['items' => $items]);

    $this->assertArrayNotHasKey('#upload_location', $element);
  }

  /**
   * Builds a mocked FileFieldItemList with the given filefield_paths settings.
   */
  protected function buildFileFieldItemList(array $settings): FileFieldItemList {
    $definition = $this->createMock(FieldConfigInterface::class);
    $definition->method('getThirdPartySettings')->with('filefield_paths')->willReturn($settings);

    $items = $this->createMock(FileFieldItemList::class);
    $items->method('getFieldDefinition')->willReturn($definition);
    return $items;
  }

}
