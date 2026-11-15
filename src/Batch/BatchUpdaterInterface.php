<?php

declare(strict_types=1);

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
   * @param bool $include_revisions
   *   (optional) TRUE, the default, also updates the files of older
   *   revisions. FALSE updates the current revision of each entity only.
   *
   * @return bool
   *   True if there were paths to update, false otherwise.
   */
  public function batchUpdate(FieldConfigInterface $field_config, bool $include_revisions = TRUE): bool;

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

  /**
   * Batch callback that updates the files of older revisions.
   *
   * @param int[] $revision_ids
   *   A list of revision IDs. None of them is a default revision.
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   * @param array $context
   *   The batch context.
   *
   * @internal
   */
  public function batchProcessRevisions(array $revision_ids, FieldConfigInterface $field_config, array &$context): void;

  /**
   * Batch finished callback: reports how many files moved or were skipped.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The results array built up during batchProcess(), containing
   *   'updated' and 'skipped' file counts.
   * @param array $operations
   *   The operations that remained unprocessed.
   *
   * @internal
   */
  public function batchFinished(bool $success, array $results, array $operations): void;

}
