<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Local task alter hook implementation.
 */
final class LocalTaskAlter {

  use StringTranslationTrait;

  /**
   * Implements hook_local_tasks_alter().
   */
  // @phpstan-ignore-next-line
  #[Hook('local_tasks_alter')]
  public function localTasksAlterImplementation(array &$local_tasks): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    foreach ($local_tasks as $definition) {
      if ($definition['route_name'] === 'system.file_system_settings') {
        // Filesystem route exists - no need to add our own.
        return;
      }
    }
    // Provide filesystem route if it not exists.
    $local_tasks['system.file_system_settings'] = [
      'route_name' => 'system.file_system_settings',
      'base_route' => 'system.file_system_settings',
      'title' => $this->t('Settings'),
      'id' => 'system.file_system_settings',
    ] + $local_tasks['filefield_paths.admin_settings'];
  }

}
