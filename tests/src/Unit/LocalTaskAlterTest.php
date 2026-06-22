<?php

declare(strict_types=1);

namespace Drupal\Tests\filefield_paths\Unit;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;
use Drupal\filefield_paths\Hook\LocalTaskAlter;

/**
 * Tests the local task alter hook implementation.
 *
 * @group filefield_paths
 * @covers \Drupal\filefield_paths\Hook\LocalTaskAlter
 */
#[Group('filefield_paths')]
class LocalTaskAlterTest extends UnitTestCase {

  /**
   * Tests that the filesystem settings tab is added when missing.
   */
  public function testAddsLocalTaskWhenMissing(): void {
    $hook = new LocalTaskAlter();
    $hook->setStringTranslation($this->getStringTranslationStub());

    $local_tasks = [
      'filefield_paths.admin_settings' => [
        'route_name' => 'filefield_paths.admin_settings',
        'base_route' => 'filefield_paths.admin_settings',
        'title' => 'File (Field) Paths',
        'weight' => 5,
      ],
    ];
    $hook->localTasksAlterImplementation($local_tasks);

    $this->assertArrayHasKey('system.file_system_settings', $local_tasks);
    // Explicit keys in the new definition win over the merged-in entry.
    $this->assertSame('system.file_system_settings', $local_tasks['system.file_system_settings']['route_name']);
    $this->assertSame('system.file_system_settings', $local_tasks['system.file_system_settings']['base_route']);
    // Keys not explicitly set are inherited from the admin settings task.
    $this->assertSame(5, $local_tasks['system.file_system_settings']['weight']);
  }

  /**
   * Tests that nothing is added when the filesystem tab already exists.
   */
  public function testSkipsWhenAlreadyPresent(): void {
    $hook = new LocalTaskAlter();
    $hook->setStringTranslation($this->getStringTranslationStub());

    $local_tasks = [
      'system.file_system_settings' => [
        'route_name' => 'system.file_system_settings',
      ],
    ];
    $hook->localTasksAlterImplementation($local_tasks);

    $this->assertCount(1, $local_tasks);
  }

}
