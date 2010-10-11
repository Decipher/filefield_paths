<?php
// $Id$
/**
 * @file
 * CTools module integration.
 */

/**
 * Implements hook_filefield_paths_field_cleanup_option().
 */
function ctools_filefield_paths_field_options() {
  return array(
    'ctools' => t('Cleanup using CTools')
  );
}

/**
 * Implements hook_filefield_paths_field_postprocess().
 */
function ctools_filefield_paths_field_postprocess($value, $field, $settings) {
  if ($settings['ctools']) {
    ctools_include('cleanstring');

    if ($field == 'file_name') {
      $pathinfo = pathinfo($value);
      $value = drupal_substr($value, 0, drupal_strlen($value) - drupal_strlen($pathinfo['extension']) - 1);
    }

    $values = explode('/', $value);
    foreach ($values as &$tmp_value) {
      $tmp_value = ctools_cleanstring($tmp_value);
    }
    $value = implode('/', $values);

    if ($field == 'file_name') {
      $value .= '.' . ctools_cleanstring($pathinfo['extension']);
    }
  }
}
