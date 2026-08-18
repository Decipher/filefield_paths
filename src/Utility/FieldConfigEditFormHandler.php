<?php

declare(strict_types=1);

namespace Drupal\filefield_paths\Utility;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filefield_paths\Batch\BatchUpdaterInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

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
    if (!$settings['enabled'] || !$settings['retroactive_update']) {
      // Retroactive updates disabled.
      return;
    }
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof FieldConfigInterface) {
      return;
    }
    $updater = $this->getUpdater();
    // Setting a batch here is enough: the form API processes any pending
    // batch right after submit handlers run and sends the response itself.
    // Calling batch_process() and sending a response here too would emit a
    // second, conflicting response.
    if (!$updater->batchUpdate($entity)) {
      // No paths to update.
      return;
    }
    // For an AJAX submission the form API disabled the redirect, and the
    // batch it stores would keep that disabled state. A finished batch
    // then falls back to the request URL, which still carries the
    // ajax_form query arguments and crashes on a normal page load.
    // Re-enable the redirect so the finished batch lands on the form's
    // own redirect target, the same page as a non-JavaScript submission.
    $form_state->disableRedirect(FALSE);
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
