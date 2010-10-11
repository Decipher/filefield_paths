<?php
// $Id$
/**
 * @file
 * Pathauto module integration.
 *
 * Integration to be deprecated in favor of CTools.
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
    $value = pathauto_cleanstring($value);
  }
}
