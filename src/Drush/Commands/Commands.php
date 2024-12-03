<?php

namespace Drupal\filefield_paths\Drush\Commands;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filefield_paths\Batch\Updater;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush integration.
 */
final class Commands extends DrushCommands {

  protected const ALL_OPTION = 'all';

  /**
   * Constructs a new instance of the class.
   *
   * This constructor method initializes the necessary services required
   * for the class functionality, injecting dependencies for managing
   * entity fields, entity types, and their bundles.
   *
   * @param \Drupal\filefield_paths\Batch\Updater $updater
   *   File (Field) Paths Batch Updater service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Manages the discovery of entity fields.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Manages entity type plugin definitions.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Provides discovery and retrieval of entity type bundles.
   */
  public function __construct(
    protected readonly Updater $updater,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('filefield_paths.batch.updater'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Retroactively updates all File (Field) Paths of a chosen field instance.
   *
   * @param string|null $entity_type
   *   Entity type.
   * @param string|null $bundle_name
   *   Bundle name.
   * @param string|null $field_name
   *   Field name.
   * @param array|null $options
   *   Command options.
   */
  #[CLI\Command(name: 'filefield_paths:update', aliases: ['ffpu', 'ffp-update'])]
  #[CLI\Argument(name: 'entity_type', description: 'Entity type. (e.g. node, user, etc.)')]
  #[CLI\Argument(name: 'bundle_name', description: 'Bundle name. (e.g. article, page, user, etc.)')]
  #[CLI\Argument(name: 'field_name', description: 'Field name. (e.g. field_image, field_file, etc.)')]
  #[CLI\Option(name: self::ALL_OPTION, description: 'Retroactively update all File (Field) Paths.')]
  #[CLI\Usage(name: 'drush ffp:update', description: 'Retroactively updates the File (Field) Paths of the instances chosen via an interactive menu.')]
  #[CLI\Usage(name: 'drush ffp:update node article field_image', description: 'Retroactively updates the File (Field) Paths of the instances chosen via an interactive menu.')]
  #[CLI\Usage(name: 'drush ffp:update --all', description: 'Retroactively updates the File (Field) Paths of all instances of the Article content types Image field.')]
  public function updateFileFieldPaths(
    ?string $entity_type = NULL,
    ?string $bundle_name = NULL,
    ?string $field_name = NULL,
    ?array $options = [self::ALL_OPTION => FALSE],
  ): void {
    // Build array of information of all entity types, bundle names
    // and field names.
    $info = $this->buildInfo();

    // Get entity type.
    if (
      $options[self::ALL_OPTION] ||
      ($entity_type ??= $this->askEntityType($info)) === self::ALL_OPTION
    ) {
      $this->processAllEntityTypes($info);
      return;
    }
    // Get bundle.
    $bundle_name ??= $this->askBundle($info, $entity_type);
    if ($bundle_name === self::ALL_OPTION) {
      $this->processAllEntityBundles($info, $entity_type);
      return;
    }

    // Get field.
    $field_name ??= $this->askField($info, $entity_type, $bundle_name);
    if ($field_name === self::ALL_OPTION) {
      $this->processAllBundleFields($info, $entity_type, $bundle_name);
      return;
    }

    $this->processField($entity_type, $bundle_name, $field_name);
  }

  /**
   * Builds an information array about file fields across entity bundles.
   *
   * This method collects data concerning fields that target a file entity type,
   * organized by entity type and their respective bundles, and returns this
   * structured information.
   *
   * @return array
   *   An associative array containing information about entity types and their
   *   bundles. Each entry details fields targeting file entities with labels
   *   and descriptions, organized under their respective entity types and
   *   bundles.
   */
  protected function buildInfo(): array {
    $info = [];
    /** @var \Drupal\field\FieldStorageConfigInterface $field */
    foreach ($this->entityTypeManager->getStorage('field_storage_config')->loadMultiple() as $field) {
      if ($field->getSetting('target_type') === 'file') {
        $entity_type_name = $field->getTargetEntityTypeId();
        $bundles_list = $field->getBundles();
        $bundles_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_name);
        foreach ($bundles_list as $bundle) {
          if (!isset($info[$entity_type_name])) {
            $entity_type_info = $this->entityTypeManager->getDefinition($entity_type_name);
            $info[$entity_type_name] = [
              '#label' => "{$entity_type_info->getLabel()} ({$entity_type_name})",
            ];
          }

          if (!isset($info[$entity_type_name][$bundle])) {
            $info[$entity_type_name][$bundle] = [
              '#label' => "{$bundles_info[$bundle]['label']} ({$bundle})",
            ];
          }
          $field_instance = $this->entityFieldManager->getFieldDefinitions($entity_type_name, $bundle)[$field->getName()] ?? NULL;
          if ($field_instance) {
            $info[$entity_type_name][$bundle][$field_instance->getName()] = "{$field_instance->getLabel()} ({$field_instance->getName()})";
          }
        }
      }
    }
    return $info;
  }

  /**
   * Helper function; Invokes File (Field) Paths Retroactive updates.
   *
   * @param array $instances
   *   Instances collection.
   */
  protected function ffpUpdate(array $instances) {
    foreach ($instances as $instance) {
      if ($this->updater->batchUpdate($instance)) {
        $batch =& batch_get();
        $batch['progressive'] = FALSE;
        drush_backend_batch_process();
        $this->logger()?->success(dt('"@field_name" File (Field) Paths updated.', ['@field_name' => "{$instance['label']} ({$instance['entity_type']}-{$instance['bundle']}-{$instance['field_name']})"]));
      }
    }
  }

  /**
   * Prompts the user to select an entity type from a list of available options.
   *
   * @param array $info
   *   An associative array where keys are entity type IDs and values
   *   contain details about each entity type, including its label.
   *
   * @return string
   *   The selected entity type ID. If no selection is made,
   *   a UserAbortException is thrown.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *   Thrown when the user aborts the selection process.
   */
  protected function askEntityType(array $info): string {
    $choices = [];
    foreach ($info as $entity_type_id => $entity_type_info) {
      $choices[$entity_type_id] = $entity_type_info['#label'];
    }
    if (empty($choices)) {
      throw new UserAbortException(dt('No entity types found for user selection.'));
    }
    if (count($choices) !== 1) {
      $choices = [self::ALL_OPTION => dt('All')] + $choices;
    }
    $entity_type = $this->io()->choice(dt("Choose an Entity type."), $choices, 0);
    if (empty($entity_type)) {
      throw new UserAbortException();
    }
    return $entity_type;
  }

  /**
   * Asks the user to select a bundle from a given entity type.
   *
   * This method presents the user with a choice of bundles associated with the
   * specified entity type, allowing the user to select one for further actions.
   *
   * @param array $info
   *   An associative array containing entity types and their bundled
   *   information, including labels.
   * @param string $entity_type
   *   The type of entity for which a bundle selection is being requested.
   *
   * @return string
   *   The name of the selected bundle.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *   If no bundles are available for selection or if the user opts to abort.
   */
  protected function askBundle(array $info, string $entity_type): string {
    $choices = [];
    foreach ($info[$entity_type] as $bundle => $bundle_info) {
      if (str_starts_with($bundle, '#')) {
        // Skip labels.
        continue;
      }
      $choices[$bundle] = $bundle_info['#label'];
    }
    if (empty($choices)) {
      throw new UserAbortException(dt('No bundles found for user selection.'));
    }
    if (count($choices) !== 1) {
      $choices = [self::ALL_OPTION => dt('All')] + $choices;
    }
    $bundle_name = $this->io()->choice(dt("Choose a Bundle."), $choices, 0);
    if (empty($bundle_name)) {
      throw new UserAbortException();
    }
    return $bundle_name;
  }

  /**
   * Prompts the user to choose a field from a bundle of an entity type.
   *
   * This method presents a list of fields for user selection within a given
   * entity type and bundle. If only one field is available, it adds an option
   * for selecting all fields.
   *
   * @param array $info
   *   An associative array containing entity types, their bundles, and the
   *   corresponding fields with labels.
   * @param string $entity_type
   *   The type of entity whose bundle fields are to be presented for selection.
   * @param string $bundle
   *   The name of the bundle within the entity type to retrieve fields from.
   *
   * @return string
   *   The name of the selected field.
   *
   * @throws \Drush\Exceptions\UserAbortException
   *   Thrown when no fields are available for selection or the selection is
   *   aborted by the user.
   */
  protected function askField(array $info, string $entity_type, string $bundle): string {
    $choices = [];
    foreach ($info[$entity_type][$bundle] as $field => $field_label) {
      if (str_starts_with($field, '#')) {
        // Skip labels.
        continue;
      }
      $choices[$field] = $field_label;
    }
    if (empty($choices)) {
      throw new UserAbortException(dt('No fields found for user selection.'));
    }
    if (count($choices) !== 1) {
      $choices = [self::ALL_OPTION => dt('All')] + $choices;
    }
    $field_name = $this->io()->choice(dt("Choose a Field."), $choices, 0);
    if (empty($field_name)) {
      throw new UserAbortException();
    }
    return $field_name;
  }

  /**
   * Processes all fields for each bundle of all entity types.
   *
   * This method retrieves field definitions for each field in the bundles
   * of all provided entity types, and updates them as necessary.
   *
   * @param array $info
   *   An associative array where keys are entity type IDs. Each value is an
   *   associative array where keys are bundle names, and each value is a list
   *   of fields within that bundle.
   */
  protected function processAllEntityTypes(array $info): void {
    $instances = [];
    foreach ($info as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle => $field_list) {
        if (str_starts_with($bundle, '#')) {
          // Skip labels.
          continue;
        }
        foreach (array_keys($field_list) as $field) {
          if (str_starts_with($field, '#')) {
            // Skip labels.
            continue;
          }
          $field_instance = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field] ?? NULL;
          if ($field_instance) {
            $instances[] = $field_instance;
          }
        }
      }
    }
    $this->ffpUpdate($instances);
  }

  /**
   * Processes all bundles of a specified entity type.
   *
   * This method iterates over each bundle of a given entity type, retrieving
   * field definitions for each field, and updates them accordingly.
   *
   * @param array $info
   *   An associative array where each key is an entity type and the value is
   *   another associative array of bundles with their respective fields.
   * @param string $entity_type
   *   The type of entity whose bundles are to be processed.
   */
  protected function processAllEntityBundles(array $info, string $entity_type): void {
    $instances = [];
    foreach ($info[$entity_type] as $bundle => $field_list) {
      if (str_starts_with($bundle, '#')) {
        // Skip labels.
        continue;
      }
      foreach (array_keys($field_list) as $field) {
        if (str_starts_with($field, '#')) {
          // Skip labels.
          continue;
        }
        $field_instance = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle)[$field] ?? NULL;
        if ($field_instance) {
          $instances[] = $field_instance;
        }
      }
    }
    $this->ffpUpdate($instances);
  }

  /**
   * Processes all fields for a given bundle of an entity type.
   *
   * This method retrieves field definitions for each field in the specified
   * bundle of the given entity type, and updates them as necessary.
   *
   * @param array $info
   *   An associative array containing entity types and their bundles with
   *   fields.
   * @param string $entity_type
   *   The type of entity whose bundle fields are to be processed.
   * @param string $bundle_name
   *   The name of the bundle within the entity type to process fields for.
   */
  protected function processAllBundleFields(array $info, string $entity_type, string $bundle_name): void {
    $instances = [];
    foreach (array_keys($info[$entity_type][$bundle_name]) as $field) {
      if (str_starts_with($field, '#')) {
        // Skip labels.
        continue;
      }
      $field_instance = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_name)[$field] ?? NULL;
      if ($field_instance) {
        $instances[] = $field_instance;
      }
    }
    $this->ffpUpdate($instances);
  }

  /**
   * Processes a specific field by invoking a retroactive update.
   *
   * @param string $entity_type
   *   The type of the entity to which the field belongs.
   * @param string $bundle_name
   *   The name of the bundle that the field is a part of.
   * @param string $field_name
   *   The name of the field to process.
   */
  protected function processField(string $entity_type, string $bundle_name, string $field_name): void {
    // Invoke Retroactive update.
    $field_instance = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_name)[$field_name] ?? NULL;
    if ($field_instance) {
      $this->ffpUpdate([$field_instance]);
    }
  }

}
