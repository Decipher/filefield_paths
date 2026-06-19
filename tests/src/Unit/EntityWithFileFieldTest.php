<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\filefield_paths\Hook\EntityWithFileField;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the EntityWithFileField hook handler.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\EntityWithFileField
 */
class EntityWithFileFieldTest extends UnitTestCase {

  /**
   * Tests handleProcessFile() skips non-content entities.
   */
  public function testHandleProcessFileSkipsNonContentEntity(): void {
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $handler = new EntityWithFileField(fn (): ModuleHandlerInterface => $module_handler);

    $entity = $this->createMock(EntityInterface::class);

    // Module handler invokeAll should never be called for non-content entities.
    $module_handler->expects($this->never())->method('invokeAll');

    $handler->handleProcessFile($entity);
  }

}
