<?php
// $Id$
/**
 * @file
 * UI Tweaks for FileField Paths.
 */

// @TODO - Find less hacky approach.
function _filefield_paths_include_ui_filefield_paths_form_options($widget) {
  $fields = module_invoke_all('filefield_paths_field_settings');
  drupal_add_css(drupal_get_path('module', 'filefield_paths') . '/includes/ui/ui.css');
  drupal_add_js(drupal_get_path('module', 'filefield_paths') . '/includes/ui/ui.js');
  drupal_add_js(array('fileFieldPaths_UI' => array('fields' => array_keys($fields))), 'setting');
  return array();
}
