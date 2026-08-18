<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Batch;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;
use Drupal\filefield_paths\ProcessOutcomeInterface;

/**
 * File (Field) Paths Batch Updater service.
 */
class Updater implements BatchUpdaterInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use MessengerTrait;

  /**
   * Constructs a new FileFieldPathBatchUpdater object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\filefield_paths\ProcessOutcomeInterface $processOutcome
   *   The service that records the result of processing each file.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ProcessOutcomeInterface $processOutcome,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function batchUpdate(FieldConfigInterface $field_config): bool {
    $entity_info = $this->entityTypeManager->getDefinition($field_config->getTargetEntityTypeId());
    $query = $this->entityTypeManager->getStorage($field_config->getTargetEntityTypeId())->getQuery();
    if ($bundle_field = $entity_info->getKey('bundle')) {
      $query->condition($bundle_field, $field_config->getTargetBundle());
    }
    $result = $query->accessCheck(FALSE)
      ->addTag('DANGEROUS_ACCESS_CHECK_OPT_OUT')
      ->condition($field_config->getName() . '.target_id', '', '<>')
      ->execute();

    // If there are no results, do not set a batch as there is nothing
    // to process.
    if (empty($result)) {
      return FALSE;
    }

    // Create batch.
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Updating File (Field) Paths'))
      ->setFinishCallback([$this, 'batchFinished']);
    $batch->addOperation(
      [$this, 'batchProcess'],
      [$result, $field_config]
    );
    batch_set($batch->toArray());
    return TRUE;
  }

  /**
   * {@inheritdoc}
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
    $field_name = $field_config->getName();

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

      // Collect the file IDs to report on. This must happen AFTER
      // $field_config->save() above: accessing $entity->get($field_name)
      // lazily populates the entity's field definitions from the entity
      // field manager, and doing so before the save would cache a stale
      // definition without 'active_updating', causing the process callback
      // to skip the file.
      $file_ids = [];
      foreach ($entity->get($field_name)->getValue() as $item) {
        if (!empty($item['target_id'])) {
          $file_ids[] = $item['target_id'];
        }
      }

      $this->processOutcome->reset();
      DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $entity->setOriginal($entity), fn() => $entity->original = $entity);
      filefield_paths_entity_update($entity);

      // Restore active updating to it's previous state if necessary.
      if (!$active_updating) {
        $field_config->setThirdPartySetting('filefield_paths', 'active_updating', $active_updating);
        $field_config->save();
      }

      // Tally the result the processor recorded for each file. Comparing
      // the scheme before and after is not sufficient: a file that fails to
      // move stays on its original scheme, which looks unchanged even when
      // the source and destination schemes are the same.
      $outcomes = $this->processOutcome->getOutcomes();
      foreach ($file_ids as $file_id) {
        // A file with no recorded result was never reached by the
        // processor, so it did not get to its destination either.
        $outcome = $outcomes[$file_id] ?? ProcessOutcomeInterface::SKIPPED;
        $key = $outcome === ProcessOutcomeInterface::UPDATED ? 'updated' : 'skipped';
        $context['results'][$key] = ($context['results'][$key] ?? 0) + 1;
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

  /**
   * {@inheritdoc}
   */
  public function batchFinished(bool $success, array $results, array $operations): void {
    if (!$success) {
      // The batch stopped early, so the counts cover only part of the work.
      // Report the failure instead of a total that looks complete.
      $this->messenger()->addError($this->t('The update did not complete. Some files may not have been moved.'));
      return;
    }
    $updated = $results['updated'] ?? 0;
    $skipped = $results['skipped'] ?? 0;
    if ($skipped > 0) {
      if ($this->moduleHandler->moduleExists('dblog') && $this->currentUser->hasPermission('access site reports')) {
        $dblog_url = Url::fromRoute('dblog.overview', [], ['query' => ['type' => ['filefield_paths']]]);
        $skipped_message = $this->formatPlural($skipped,
          '1 file could not be moved automatically; see the <a href=":url">recent log messages</a>.',
          '@count files could not be moved automatically; see the <a href=":url">recent log messages</a>.',
          [':url' => $dblog_url->toString()]
        );
      }
      else {
        $skipped_message = $this->formatPlural($skipped,
          '1 file could not be moved automatically; see the recent log messages.',
          '@count files could not be moved automatically; see the recent log messages.'
        );
      }
      // Concatenating two Markup objects with '.' casts both to plain
      // strings, losing the "safe HTML" status that stops the link above
      // from being escaped. Re-wrap the joined result as safe markup: both
      // pieces were already produced by formatPlural(), so this doesn't
      // trust anything that wasn't already trusted.
      $this->messenger()->addWarning(
        Markup::create(
          $this->formatPlural($updated, 'Updated 1 file.', 'Updated @count files.') . ' ' . $skipped_message
        )
      );
      return;
    }
    $this->messenger()->addStatus($this->formatPlural($updated, 'Updated 1 file.', 'Updated @count files.'));
  }

}
