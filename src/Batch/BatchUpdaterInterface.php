<?php

namespace Drupal\filefield_paths\Batch;

use Drupal\field\FieldConfigInterface;

/**
 * File (Field) Paths Batch Updater service.
 */
interface BatchUpdaterInterface {

  /**
   * Set batch process to update File (Field) Paths.
   *
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   *
   * @return bool
   *   True if there were paths to update, false otherwise.
   */
  public function batchUpdate(FieldConfigInterface $field_config): bool;

  /**
   * Batch callback for File (Field) Paths retroactive updates.
   *
   * @param int[] $objects
   *   A list of entity ID's for the entity type that the field is attached to.
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   * @param array $context
   *   The batch context.
   *
   * @internal
   */
  public function batchProcess(array $objects, FieldConfigInterface $field_config, array &$context): void;

}
