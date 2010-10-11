<?php
// $Id$
/**
 * @file
 * Pathauto module integration.
 */

/**
 * Implements hook_filefield_paths_field_cleanup_option().
 */
function pathauto_filefield_paths_field_options() {
  return array(
    'pathauto' => t('Cleanup using !pathauto', array('!pathauto' => l(t('Pathauto'), 'admin/build/path/pathauto')))
  );
}

/**
 * Implements hook_filefield_paths_field_postprocess().
 */
function pathauto_filefield_paths_field_postprocess($value, $field, $settings) {
  if ($settings['pathauto']) {
    module_load_include('inc', 'pathauto');

    if ($field == 'file_name') {
      $pathinfo = pathinfo($value);
      $value = drupal_substr($value, 0, drupal_strlen($value) - drupal_strlen($pathinfo['extension']) - 1);
    }

    $values = explode('/', $value);
    foreach ($values as &$tmp_value) {
      $tmp_value = pathauto_cleanstring($tmp_value);
    }
    $value = implode('/', $values);

    if ($field == 'file_name') {
      $value .= '.' . pathauto_cleanstring($pathinfo['extension']);
    }
  }
}
