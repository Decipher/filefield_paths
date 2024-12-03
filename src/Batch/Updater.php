<?php

namespace Drupal\filefield_paths\Batch;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldConfigInterface;

/**
 * File (Field) Paths Batch Updater service.
 */
class Updater {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs a new FileFieldPathBatchUpdater object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Set batch process to update File (Field) Paths.
   *
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   *
   * @return bool
   *   True if there were paths to update, false otherwise.
   */
  public function batchUpdate(FieldConfigInterface $field_config): bool {
    $entity_info = $this->entityTypeManager->getDefinition($field_config->getTargetEntityTypeId());
    $query = $this->entityTypeManager->getStorage($field_config->getTargetEntityTypeId())->getQuery();
    if ($bundle_field = $entity_info->getKey('bundle')) {
      $query->condition($bundle_field, $field_config->getTargetBundle());
    }
    $result = $query->accessCheck(FALSE)
      ->addTag('DANGEROUS_ACCESS_CHECK_OPT_OUT')
      ->condition("{$field_config->getName()}.target_id", '', '<>')
      ->execute();

    // If there are no results, do not set a batch as there is nothing
    // to process.
    if (empty($result)) {
      return FALSE;
    }

    // Create batch.
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Updating File (Field) Paths'));
    $batch->addOperation(
      [$this, 'batchProcess'],
      [$result, $field_config]
    );
    batch_set($batch->toArray());
    return TRUE;
  }

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
  public function batchProcess(array $objects, FieldConfigInterface $field_config, array &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($objects);
      $context['sandbox']['objects'] = $objects;
    }
    /** @var \Drupal\Core\Entity\ContentEntityStorageBase $entity_storage */
    $entity_storage = $this->entityTypeManager
      ->getStorage($field_config->getTargetEntityTypeId());

    // Process nodes by groups of 5.
    $count = min(5, count($context['sandbox']['objects']));
    for ($i = 1; $i <= $count; $i++) {
      // For each oid, load the object, update the files and save it.
      $oid = array_shift($context['sandbox']['objects']);
      $entity = $entity_storage->load($oid);

      // Enable active updating if it isn't already enabled.
      $active_updating = $field_config->getThirdPartySetting('filefield_paths', 'active_updating');
      if (!$active_updating) {
        $field_config->setThirdPartySetting('filefield_paths', 'active_updating', TRUE);
        $field_config->save();
      }

      $entity->original = $entity;
      filefield_paths_entity_update($entity);

      // Restore active updating to it's previous state if necessary.
      if (!$active_updating) {
        $field_config->setThirdPartySetting('filefield_paths', 'active_updating', $active_updating);
        $field_config->save();
      }

      // Update our progress information.
      $context['sandbox']['progress']++;
    }

    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

}
