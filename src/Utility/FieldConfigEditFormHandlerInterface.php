<?php

namespace Drupal\filefield_paths\Utility;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for handlers to the field configuration editing form.
 */
interface FieldConfigEditFormHandlerInterface {

  /**
   * Form submission handler for the File (Field) Paths settings form.
   */
  public function submit(array $form, FormStateInterface $form_state): void;

  /**
   * Validate the temporary upload location.
   */
  public function elementTempLocationValidate(array $element, FormStateInterface $form_state): void;

}
