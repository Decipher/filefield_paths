<?php

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
   * Stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    FileSystemInterface $file_system,
    TypedConfigManagerInterface $typed_config_manager,
    protected /*readonly*/ ?MoveFileProcessorInterface $moveFileProcessor = NULL,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileSystem = $file_system;
    if ($this->moveFileProcessor === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $moveFileProcessor argument is deprecated in filefield_paths:8.x-1.0 and it will be required in filefield_paths:2.0.0. See https://www.drupal.org/node/3562442', E_USER_DEPRECATED);
      // @phpstan-ignore-next-line
      $this->moveFileProcessor = \Drupal::service(MoveFileProcessorInterface::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
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
  public function getFormId() {
    return 'filefield_paths_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'filefield_paths.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Request $request = NULL) {
    $description = $this->t('The location that unprocessed files will be uploaded prior to being processed by File (Field) Paths.');
    $description .= '<br />';
    $description .= $this->t('It is recommended to use the temporary file system (temporary://) whenever possible, especially for files that do not require previewing before form submission. Alternatively, if your server configuration permits, the private file system (private://) is preferred for situations where file previews — such as image previews — are needed before the form is submitted, as it provides secure and appropriate access for this functionality.');
    $description .= '<br />';
    $description .= '<strong>' . $this->t('Never use the public directory (public://) if the site supports private files, or private files can be temporarily exposed publicly.') . '</strong>';
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $scheme = $this->streamWrapperManager->getScheme($values['temp_location']);
    if (!$scheme) {
      $form_state->setErrorByName('temp_location', $this->t('Invalid file location. You must include a file stream wrapper (e.g., public://).'));

      return FALSE;
    }

    if (!$this->streamWrapperManager->isValidScheme($scheme)) {
      $form_state->setErrorByName('temp_location', $this->t('Invalid file stream wrapper.'));

      return FALSE;
    }

    if ((!is_dir($values['temp_location']) || !is_writable($values['temp_location'])) && !$this->fileSystem->prepareDirectory($values['temp_location'], FileSystem::CREATE_DIRECTORY | FileSystem::MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('temp_location', $this->t('File location can not be created or is not writable.'));

      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('filefield_paths.settings')
      ->set('temp_location', $values['temp_location'])
      ->save();
  }

}
