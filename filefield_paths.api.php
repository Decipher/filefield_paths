<?php
// $Id$
/**
 * @file
 * Hooks provided by the FileField Paths module.
 */

/**
 * Add a checkbox/checboxes to the each FileField Paths field options.
 *
 * @return
 *   A keyed array with option ID and Title.
 */
function hook_filefield_paths_field_options() {
  return array(
    'strtolower' => t('Convert to lower case')
  );
}

/**
 *
 */
function hook_filefield_paths_field_postprocess($value, $field, $settings) {
  if ($settings['strtolower']) {
    $value = drupal_strtolower($value);
  }
}

/**
 * Add support for your modules 'field' tokens to FileField Paths.
 *
 * @return
 *   No return necessary, implementation of this hook is all that is required.
 */
function hook_filefield_paths_field_tokens() {
  return TRUE;
}
