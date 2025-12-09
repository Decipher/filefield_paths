<?php

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\filefield_paths\Utility\FieldItem;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Provides functionality to handle file processing for an entity's fields.
 */
final class EntityWithFileField {

  /**
   * Constructor.
   *
   * @param \Closure $moduleHandlerClosure
   *   The module handler closure.
   */
  public function __construct(
    #[AutowireServiceClosure(ModuleHandlerInterface::class)]
    private readonly \Closure $moduleHandlerClosure,
  ) {}

  /**
   * Implements hook_entity_update() and hook_entity_insert().
   */
  // @phpstan-ignore-next-line
  #[Hook('entity_insert'), Hook('entity_update')]
  public function handleProcessFile(EntityInterface $entity): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    $module_handler = $this->getModuleHandler();
    foreach ($entity->getFields() as $field) {
      if (FieldItem::hasConfigurationEnabled($field)) {
        $settings = FieldItem::getConfiguration($field);
        // Invoke hook_filefield_paths_process_file().
        $module_handler->invokeAll(
          'filefield_paths_process_file',
          [$entity, $field, &$settings]
        );
      }
    }
  }

  /**
   * Retrieves the module handler service.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler instance.
   */
  private function getModuleHandler(): ModuleHandlerInterface {
    return ($this->moduleHandlerClosure)();
  }

}
