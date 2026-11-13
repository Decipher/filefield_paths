<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Batch;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;
use Drupal\filefield_paths\ProcessOutcomeInterface;
use Drupal\filefield_paths\Utility\FieldItem;

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
  public function batchUpdate(FieldConfigInterface $field_config, bool $include_revisions = TRUE): bool {
    $entity_info = $this->entityTypeManager->getDefinition($field_config->getTargetEntityTypeId());
    $result = $this->getQuery($field_config)->execute();

    // Older revisions can reference files that the current revision no
    // longer uses. Both lists are keyed by revision ID, so the difference
    // holds the revisions that are not default revisions. Each entity's
    // revisions come together, newest first.
    $revision_ids = [];
    $id_key = $entity_info->getKey('id');
    $revision_key = $entity_info->getKey('revision');
    if ($include_revisions && $id_key && $revision_key && is_array($result)) {
      $all_revisions = $this->getQuery($field_config)
        ->allRevisions()
        ->sort($id_key)
        ->sort($revision_key, 'DESC')
        ->execute();
      if (is_array($all_revisions)) {
        $revision_ids = array_keys(array_diff_key($all_revisions, $result));
      }
    }

    // If there are no results, do not set a batch as there is nothing
    // to process.
    if (empty($result) && empty($revision_ids)) {
      return FALSE;
    }

    // Create batch.
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Updating File (Field) Paths'))
      ->setFinishCallback([$this, 'batchFinished']);
    if (!empty($result)) {
      $batch->addOperation(
        [$this, 'batchProcess'],
        [$result, $field_config]
      );
    }
    if (!empty($revision_ids)) {
      $batch->addOperation(
        [$this, 'batchProcessRevisions'],
        [$revision_ids, $field_config]
      );
    }
    batch_set($batch->toArray());
    return TRUE;
  }

  /**
   * Builds the query for entities whose file field is not empty.
   *
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function getQuery(FieldConfigInterface $field_config): QueryInterface {
    $entity_info = $this->entityTypeManager->getDefinition($field_config->getTargetEntityTypeId());
    $query = $this->entityTypeManager->getStorage($field_config->getTargetEntityTypeId())->getQuery();
    if ($bundle_field = $entity_info->getKey('bundle')) {
      $query->condition($bundle_field, $field_config->getTargetBundle());
    }
    // Use a numeric comparison. The target_id column is an integer, and
    // PostgreSQL rejects a comparison with an empty string.
    return $query->accessCheck(FALSE)
      ->addTag('DANGEROUS_ACCESS_CHECK_OPT_OUT')
      ->condition($field_config->getName() . '.target_id', 0, '>');
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

    // Process nodes by groups of 5.
    $count = min(5, count($context['sandbox']['objects']));
    for ($i = 1; $i <= $count; $i++) {
      // For each oid, load the object, update the files and save it.
      $oid = array_shift($context['sandbox']['objects']);
      $entity = $entity_storage->load($oid);
      if ($entity instanceof ContentEntityInterface) {
        $this->processEntity($entity, $field_config, $context);
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
  public function batchProcessRevisions(array $revision_ids, FieldConfigInterface $field_config, array &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($revision_ids);
      $context['sandbox']['objects'] = $revision_ids;
    }
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $entity_storage */
    $entity_storage = $this->entityTypeManager
      ->getStorage($field_config->getTargetEntityTypeId());

    // Process revisions by groups of 5.
    $count = min(5, count($context['sandbox']['objects']));
    for ($i = 1; $i <= $count; $i++) {
      $revision_id = array_shift($context['sandbox']['objects']);
      $revision = $entity_storage->loadRevision($revision_id);
      if ($revision instanceof ContentEntityInterface) {
        $this->processRevision($revision, $field_config, $context);
      }
      $context['sandbox']['progress']++;
    }

    if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Updates the files of one entity or revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity or revision.
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   * @param array $context
   *   The batch context.
   */
  protected function processEntity(ContentEntityInterface $entity, FieldConfigInterface $field_config, array &$context): void {
    $field_name = $field_config->getName();

    // Skip a field that File (Field) Paths is off for, as a normal save does.
    // This reads the field config, not the entity's field. See below for why
    // the entity's field must not be read yet.
    if (!$field_config->getThirdPartySetting('filefield_paths', 'enabled', FALSE)) {
      return;
    }

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
    $file_ids = $this->getFileIds($entity->get($field_name));

    $this->processOutcome->reset();
    $this->setOriginal($entity);
    filefield_paths_entity_update($entity);

    // Update the existing translations too.
    foreach (array_keys($entity->getTranslationLanguages(FALSE)) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      $this->setOriginal($translation);
      filefield_paths_entity_update($translation);
    }

    // Restore active updating to it's previous state if necessary.
    if (!$active_updating) {
      $field_config->setThirdPartySetting('filefield_paths', 'active_updating', $active_updating);
      $field_config->save();
    }

    $this->tallyOutcomes($file_ids, $context);
  }

  /**
   * Updates the files that only an older revision references.
   *
   * A file goes where the newest revision that references it says. The
   * default revision's files were done by batchProcess(), and the newer
   * revisions of the same entity come first. So this skips any file that is
   * already done.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision. It is not a default revision.
   * @param \Drupal\field\FieldConfigInterface $field_config
   *   The file field for which to update paths.
   * @param array $context
   *   The batch context.
   */
  protected function processRevision(ContentEntityInterface $revision, FieldConfigInterface $field_config, array &$context): void {
    $field_name = $field_config->getName();

    // Skip a field that File (Field) Paths is off for, as a normal save does.
    if (!FieldItem::hasConfigurationEnabled($revision->get($field_name))) {
      return;
    }

    // Start a new list of done files for each entity.
    if (($context['sandbox']['entity_id'] ?? NULL) !== $revision->id()) {
      $context['sandbox']['entity_id'] = $revision->id();
      $default = $this->entityTypeManager
        ->getStorage($field_config->getTargetEntityTypeId())
        ->load($revision->id());
      $context['sandbox']['done'] = $default instanceof ContentEntityInterface ? $this->getFileIds($default->get($field_name)) : [];
    }

    // Remove the done files from a copy of the revision. The copy is never
    // saved, so the stored revision does not change.
    $done = $context['sandbox']['done'];
    $revision = clone $revision;
    $field = $revision->get($field_name);
    $field->filter(static fn($item): bool => !in_array((string) $item->target_id, $done, TRUE));
    $file_ids = $this->getFileIds($field);
    if (empty($file_ids)) {
      return;
    }
    $context['sandbox']['done'] = array_merge($done, $file_ids);

    // Process this field only. Other fields of an old revision may hold files
    // that newer revisions still use. A retroactive update moves files that
    // are already attached, so it acts as if active updating is on.
    $settings = FieldItem::getConfiguration($field);
    $settings['active_updating'] = TRUE;
    $this->processOutcome->reset();
    $this->setOriginal($revision);
    $this->moduleHandler->invokeAll('filefield_paths_process_file', [$revision, $field, &$settings]);

    $this->tallyOutcomes($file_ids, $context);
  }

  /**
   * Makes an entity its own original, as if it was saved unchanged.
   *
   * The file processor then treats the entity as an update, not an insert.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity or revision.
   */
  protected function setOriginal(ContentEntityInterface $entity): void {
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn(): ContentEntityInterface => $entity->setOriginal($entity), fn(): ContentEntityInterface => $entity->original = $entity);
  }

  /**
   * Returns the IDs of the files a field references.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The file field items.
   *
   * @return string[]
   *   The file IDs.
   */
  protected function getFileIds(FieldItemListInterface $items): array {
    $file_ids = [];
    foreach ($items->getValue() as $item) {
      if (!empty($item['target_id'])) {
        $file_ids[] = (string) $item['target_id'];
      }
    }
    return $file_ids;
  }

  /**
   * Adds the result recorded for each file to the batch results.
   *
   * @param string[] $file_ids
   *   The IDs of the files that were processed.
   * @param array $context
   *   The batch context.
   */
  protected function tallyOutcomes(array $file_ids, array &$context): void {
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
