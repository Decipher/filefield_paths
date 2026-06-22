<?php

declare(strict_types=1);

/**
 * @file
 * Hook implementation that processes an entity's file fields on save.
 */

namespace Drupal\filefield_paths\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\filefield_paths\Utility\FieldItem;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Provides functionality to handle file processing for an entity's fields.
 */
final readonly class EntityWithFileField {

  /**
   * Constructor.
   *
   * @param \Closure $moduleHandlerClosure
   *   The module handler closure.
   * @param \Closure $configFactoryClosure
   *   The config factory closure.
   */
  public function __construct(
    #[AutowireServiceClosure(ModuleHandlerInterface::class)]
    private \Closure $moduleHandlerClosure,
    #[AutowireServiceClosure(ConfigFactoryInterface::class)]
    private \Closure $configFactoryClosure,
  ) {}

  /**
   * Implements hook_entity_update() and hook_entity_insert().
   */
  // @phpstan-ignore-next-line
  #[Hook('entity_insert'), Hook('entity_update')]
  public function handleProcessFile(EntityInterface $entity): void {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    if (!($this->getSettings()->get('enabled') ?? TRUE)) {
      return;
    }
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    $module_handler = $this->getModuleHandler();
    $fields = $entity->getFields();
    // Lets calling code skip (or otherwise tweak) processing for a single
    // save without touching field config, which would invalidate caches.
    // See filefield_paths.api.php for the accepted shapes of this property.
    $override = $entity->filefield_paths_settings ?? [];
    // The property is untyped, so guard against a caller setting something
    // other than an array.
    if (!is_array($override)) {
      $override = [];
    }
    // Anything that isn't a field name applies to every field; a field name
    // key scopes the override to that field only and wins if both are set.
    $flat_override = array_diff_key($override, $fields);
    foreach ($fields as $field_name => $field) {
      if (!FieldItem::hasConfigurationEnabled($field)) {
        continue;
      }
      $settings = $flat_override + FieldItem::getConfiguration($field);
      if (is_array($override[$field_name] ?? NULL)) {
        $settings = $override[$field_name] + $settings;
      }
      if (empty($settings['enabled'])) {
        continue;
      }
      // Invoke hook_filefield_paths_process_file().
      $module_handler->invokeAll(
        'filefield_paths_process_file',
        [$entity, $field, &$settings]
      );
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

  /**
   * Retrieves the configuration settings for filefield_paths.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration settings object.
   */
  private function getSettings(): ImmutableConfig {
    return ($this->configFactoryClosure)()->get('filefield_paths.settings');
  }

}
