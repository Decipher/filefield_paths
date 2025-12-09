<?php

namespace Drupal\filefield_paths\Utility;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filefield_paths\Batch\BatchUpdaterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\HttpFoundation\Response;

/**
 * File (Field) Paths field config edit form handler.
 */
class FieldConfigEditFormHandler implements FieldConfigEditFormHandlerInterface {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Closure $updaterClosure
   *   The batch updater closure.
   * @param \Closure $streamWrapperManagerClosure
   *   The stream wrapper manager closure.
   */
  public function __construct(
    #[AutowireServiceClosure(BatchUpdaterInterface::class)]
    protected readonly \Closure $updaterClosure,
    #[AutowireServiceClosure(StreamWrapperManagerInterface::class)]
    protected readonly \Closure $streamWrapperManagerClosure,
  ) {}

  /**
   * Form submission handler for the File (Field) Paths settings form.
   */
  public function submit(array $form, FormStateInterface $form_state): void {
    $settings = $form_state->getValue('third_party_settings')['filefield_paths'];
    // Retroactive updates.
    if (!($settings['enabled'] && $settings['retroactive_update'])) {
      // Retroactive updates disabled.
      return;
    }
    $updater = $this->getUpdater();
    if (!$updater->batchUpdate($form_state->getFormObject()->getEntity())) {
      // No paths to update.
      return;
    }
    $response = batch_process($form_state->getRedirect());
    if (!$response instanceof Response) {
      // Not expected batch response.
      return;
    }
    $response->send();
  }

  /**
   * Retrieves the batch updater instance.
   *
   * @return \Drupal\filefield_paths\Batch\BatchUpdaterInterface
   *   The batch updater instance obtained from the updater closure.
   */
  protected function getUpdater(): BatchUpdaterInterface {
    return ($this->updaterClosure)();
  }

  /**
   * Validate the temporary upload location.
   */
  public function elementTempLocationValidate(array $element, FormStateInterface $form_state): void {
    $value = $element['#value'] ?? $element['#default_value'];
    if (empty($value)) {
      // No value to validate.
      return;
    }
    $stream_wrapper_manager = $this->getStreamWrapperManager();
    if ($stream_wrapper_manager->getViaUri($value)) {
      // Valid location.
      return;
    }
    $form_state->setError($element, $this->t('Invalid temporary file location.'));
  }

  /**
   * Retrieves the stream wrapper manager instance.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   *   The stream wrapper manager instance.
   */
  protected function getStreamWrapperManager(): StreamWrapperManagerInterface {
    return ($this->streamWrapperManagerClosure)();
  }

}
