<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Utility\FieldItem;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the FieldItem utility class.
 *
 * @group filefield_paths
 * @runTestsInSeparateProcesses
 * @covers \Drupal\filefield_paths\Utility\FieldItem
 */
class FieldItemTest extends UnitTestCase {

  /**
   * Tests the getFromSupportedWidget method.
   *
   * @covers \Drupal\filefield_paths\Utility\FieldItem::getFromSupportedWidget
   * @dataProvider dataProviderGetFromSupportedWidget
   */
  public function testGetFromSupportedWidget(array $element, array $context, bool $expected_result): void {
    if (isset($context['items']) && $context['items'] === 'file_field_item_list') {
      $context['items'] = $this->createMock(FileFieldItemList::class);
    }
    elseif (isset($context['items']) && $context['items'] === 'field_item_list') {
      $context['items'] = $this->createMock(FieldItemListInterface::class);
    }

    $result = FieldItem::getFromSupportedWidget($element, $context);

    if ($expected_result) {
      $this->assertInstanceOf(FileFieldItemList::class, $result);
    }
    else {
      $this->assertNull($result);
    }
  }

  /**
   * Tests getConfiguration() and hasConfigurationEnabled().
   *
   * @covers \Drupal\filefield_paths\Utility\FieldItem::getConfiguration
   * @covers \Drupal\filefield_paths\Utility\FieldItem::hasConfigurationEnabled
   * @dataProvider dataProviderHasConfigurationEnabled
   */
  public function testHasConfigurationEnabled(bool $is_file_field, ?array $third_party_settings, bool $expected): void {
    if ($is_file_field) {
      $definition = $this->createMockForIntersectionOfInterfaces([
        FieldDefinitionInterface::class,
        ThirdPartySettingsInterface::class,
      ]);
      $definition->method('getThirdPartySettings')->with('filefield_paths')->willReturn($third_party_settings ?? []);
      $field = $this->createMock(FileFieldItemList::class);
      $field->method('getFieldDefinition')->willReturn($definition);
    }
    else {
      $field = $this->createMock(FieldItemListInterface::class);
    }

    $this->assertSame($expected, FieldItem::hasConfigurationEnabled($field));
  }

  /**
   * Tests hasConfigurationEnabled() returns false for a null field.
   */
  public function testHasConfigurationEnabledWithNullField(): void {
    $this->assertFalse(FieldItem::hasConfigurationEnabled(NULL));
  }

  /**
   * Data provider for testHasConfigurationEnabled.
   *
   * @return array
   *   Test cases for testHasConfigurationEnabled.
   */
  public static function dataProviderHasConfigurationEnabled(): array {
    return [
      'enabled file field' => [TRUE, ['enabled' => TRUE], TRUE],
      'disabled file field' => [TRUE, ['enabled' => FALSE], FALSE],
      'no settings' => [TRUE, [], FALSE],
      'not a file field' => [FALSE, NULL, FALSE],
    ];
  }

  /**
   * Data provider for testGetFromSupportedWidget.
   *
   * @return array
   *   Test cases for testGetFromSupportedWidget.
   */
  public static function dataProviderGetFromSupportedWidget(): array {
    return [
      'valid case' => [
        ['#type' => 'managed_file'],
        ['items' => 'file_field_item_list'],
        TRUE,
      ],
      'non-managed_file element type' => [
        ['#type' => 'textfield'],
        ['items' => 'file_field_item_list'],
        FALSE,
      ],
      'missing #type' => [
        [],
        ['items' => 'file_field_item_list'],
        FALSE,
      ],
      'missing items in context' => [
        ['#type' => 'managed_file'],
        [],
        FALSE,
      ],
      'items not a FileFieldItemList' => [
        ['#type' => 'managed_file'],
        ['items' => 'field_item_list'],
        FALSE,
      ],
    ];
  }

}
