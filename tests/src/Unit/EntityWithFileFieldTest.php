<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\filefield_paths\Hook\EntityWithFileField;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the EntityWithFileField hook handler.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\EntityWithFileField
 */
#[Group('filefield_paths')]
class EntityWithFileFieldTest extends UnitTestCase {

  /**
   * Builds a handler with mocked module handler and config factory.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject&\Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The mocked module handler, passed by reference so the caller can set
   *   expectations on it.
   * @param bool $global_enabled
   *   The value the mocked `filefield_paths.settings:enabled` config returns.
   *
   * @return \Drupal\filefield_paths\Hook\EntityWithFileField
   *   The handler under test.
   *
   * @param-out \PHPUnit\Framework\MockObject\MockObject&\Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  private function createHandler(?ModuleHandlerInterface &$module_handler = NULL, bool $global_enabled = TRUE): EntityWithFileField {
    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('enabled')->willReturn($global_enabled);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('filefield_paths.settings')->willReturn($config);

    return new EntityWithFileField(
      fn (): ModuleHandlerInterface => $module_handler,
      fn (): ConfigFactoryInterface => $config_factory,
    );
  }

  /**
   * Builds a mocked enabled FileFieldItemList field.
   */
  private function createEnabledField(): FileFieldItemList {
    $definition = $this->createMock(FieldConfigInterface::class);
    $definition->method('getThirdPartySettings')->with('filefield_paths')->willReturn(['enabled' => TRUE]);

    $field = $this->createMock(FileFieldItemList::class);
    $field->method('getFieldDefinition')->willReturn($definition);

    return $field;
  }

  /**
   * Tests handleProcessFile() skips non-content entities.
   */
  public function testHandleProcessFileSkipsNonContentEntity(): void {
    $handler = $this->createHandler($module_handler);

    $entity = $this->createMock(EntityInterface::class);

    // Module handler invokeAll should never be called for non-content entities.
    $module_handler->expects($this->never())->method('invokeAll');

    $handler->handleProcessFile($entity);
  }

  /**
   * Tests handleProcessFile() processes an enabled field with no override.
   */
  public function testHandleProcessFileProcessesFieldWithoutOverride(): void {
    $handler = $this->createHandler($module_handler);

    $field = $this->createEnabledField();
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getFields')->willReturn(['field_x' => $field]);

    $module_handler->expects($this->once())->method('invokeAll');

    $handler->handleProcessFile($entity);
  }

  /**
   * Tests the global `enabled` config setting suppresses all processing.
   */
  public function testHandleProcessFileSkipsAllFieldsWhenGloballyDisabled(): void {
    $handler = $this->createHandler($module_handler, global_enabled: FALSE);

    $field = $this->createEnabledField();
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getFields')->willReturn(['field_x' => $field]);

    $module_handler->expects($this->never())->method('invokeAll');

    $handler->handleProcessFile($entity);
  }

  // Tests for the entity-level `filefield_paths_settings` transient override
  // live in Kernel\EntityWithFileFieldOverrideTest: setting a dynamic
  // property on a mocked ContentEntityInterface triggers a "creation of
  // dynamic property" deprecation (the mock doesn't implement
  // ContentEntityBase::__set()), whereas a real entity's magic setter
  // handles it without deprecation, and exercising real file movement is a
  // more meaningful assertion than mock call counts for this behavior.
}
