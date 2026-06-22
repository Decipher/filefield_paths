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
   * Tests that unsupported widgets are left untouched.
   */
  public function testUnsupportedWidgetIsUntouched(): void {
    $hook = new FieldWidgetSingleElementForm(static fn () => throw new \LogicException('Config factory should not be called.'));

    $element = ['#type' => 'textfield'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), []);

    $this->assertArrayNotHasKey('#upload_location', $element);
  }

  /**
   * Tests that the field-level temp location wins when set.
   */
  public function testUsesFieldLevelTempLocation(): void {
    $hook = new FieldWidgetSingleElementForm(static fn () => throw new \LogicException('Config factory should not be called.'));

    $items = $this->buildFileFieldItemList(['enabled' => TRUE, 'temp_location' => 'private://custom']);
    $element = ['#type' => 'managed_file'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), ['items' => $items]);

    $this->assertSame('private://custom', $element['#upload_location']);
  }

  /**
   * Tests that the global setting is used when no field-level value is set.
   */
  public function testFallsBackToGlobalTempLocation(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('temp_location')->willReturn('temporary://filefield_paths');
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('filefield_paths.settings')->willReturn($config);
    $hook = new FieldWidgetSingleElementForm(static fn (): MockObject => $config_factory);

    $items = $this->buildFileFieldItemList(['enabled' => TRUE]);
    $element = ['#type' => 'managed_file'];
    $hook->formAlter($element, $this->createMock(FormStateInterface::class), ['items' => $items]);

    $this->assertSame('temporary://filefield_paths', $element['#upload_location']);
  }

  /**
   * Tests that a disabled field is left untouched.
   */
  public function testDisabledFieldIsUntouched(): void {
    $hook = new FieldWidgetSingleElementForm(static fn () => throw new \LogicException('Config factory should not be called.'));

    $items = $this->buildFileFieldItemList(['enabled' => FALSE]);
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
