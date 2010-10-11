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
    'ctools' => t('Cleanup using !ctools', array('!pathauto' => l(t('CTools'), 'admin/build/path/pathauto')))
  );
}

/**
 * Implements hook_filefield_paths_field_postprocess().
 */
function ctools_filefield_paths_field_postprocess($value, $field, $settings) {

}
