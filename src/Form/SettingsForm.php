<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\filefield_paths\MoveFileProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Administration settings form for File (Field) Paths.
 *
 * @package Drupal\filefield_paths\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    /**
     * Stream wrapper manager.
     */
    protected StreamWrapperManagerInterface $streamWrapperManager,
    /**
     * Filesystem service.
     */
    protected FileSystemInterface $fileSystem,
    TypedConfigManagerInterface $typed_config_manager,
    protected /*readonly*/ ?MoveFileProcessorInterface $moveFileProcessor = NULL,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    if (!$this->moveFileProcessor instanceof MoveFileProcessorInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $moveFileProcessor argument is deprecated in filefield_paths:8.x-1.0 and it will be required in filefield_paths:2.0.0. See https://www.drupal.org/node/3562442', E_USER_DEPRECATED);
      // @phpstan-ignore-next-line
      $this->moveFileProcessor = \Drupal::service(MoveFileProcessorInterface::class); // phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('config.typed'),
      $container->get(MoveFileProcessorInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'filefield_paths_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'filefield_paths.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function buildForm(array $form, FormStateInterface $form_state, ?Request $request = NULL) {
    $description = $this->t('Files are uploaded here first. They move to their final path when the entity is saved.');
    $description .= '<br />';
    $description .= $this->t('Use the temporary file system (temporary://) where you can. Files there have no public URL, and image previews still work. If temporary:// does not suit your server, use the private file system (private://).');
    $description .= '<br />';
    $description .= '<strong>' . $this->t('Do not use the public file system (public://) on a site with private files. A file bound for private:// would have a public URL until the entity is saved.') . '</strong>';
    $form['temp_location'] = [
      '#title' => $this->t('Temporary file location'),
      '#type' => 'textfield',
      '#default_value' => $this->config('filefield_paths.settings')
        ->get('temp_location') ?: $this->moveFileProcessor->recommendedTemporaryScheme() . 'filefield_paths',
      '#description' => $description,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $scheme = $this->streamWrapperManager->getScheme($values['temp_location']);
    if (!$scheme) {
      $form_state->setErrorByName('temp_location', $this->t('Invalid file location. You must include a file stream wrapper (e.g., public://).'));

      return;
    }

    if (!$this->streamWrapperManager->isValidScheme($scheme)) {
      $form_state->setErrorByName('temp_location', $this->t('Invalid file stream wrapper.'));

      return;
    }

    if ((!is_dir($values['temp_location']) || !is_writable($values['temp_location'])) && !$this->fileSystem->prepareDirectory($values['temp_location'], FileSystem::CREATE_DIRECTORY | FileSystem::MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('temp_location', $this->t('File location can not be created or is not writable.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->config('filefield_paths.settings')
      ->set('temp_location', $values['temp_location'])
      ->save();
  }

}
