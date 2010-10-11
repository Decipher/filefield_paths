<?php
// $Id$
/**
 * @file
 */

/**
 * Implements hook_filefield_paths_form_options().
 */
function _filefield_paths_include_updater_filefield_paths_form_options($widget) {
  $options = array();

  $options['updater'] = array(
    '#title' => t('Updater mode'),
    '#type' => 'select',
    '#options' => array(
      'none' => t('None'),
      'active' => t('Active'),
      'retroactive' => t('Retroactive'),
      'retroactive-cron' => t('Retroactive (cron)')
    ),
    '#default_value' => $widget['updater']
  );

  return $options;
}

/**
 * Implements hook_filefield_paths_file_check().
 */
function _filefield_paths_include_updater_filefield_paths_file_check($file, $field) {
  return $field['widget']['updater'] == 'active';
}
