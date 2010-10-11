<?php
// $Id$
/**
 * @file
 * FileField Paths module integration.
 */

/**
 * Implements hook_filefield_paths_field_settings().
 */
function filefield_paths_filefield_paths_field_settings() {
  return array(
    // File name.
    'file_name' => array(
      'key' => 'filename',
      'fapi' => array(
        '#title' => t('File name'),
        '#default_value' => '[filefield-onlyname-original].[filefield-extension-original]',
        '#required' => TRUE,
        '#weight' => 0,
      ),
    ),

    // File path.
    'file_path' => array(
      'key' => 'filepath',
      'fapi' => array(
        '#title' => t('File path'),
        '#weight' => 1,
      ),
    ),
  );
}

/**
 * Implements hook_filefield_paths_form_options().
 */
function filefield_paths_filefield_paths_form_options($widget) {
  $options = array();

  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_filefield_paths_form_options")) {
      $options = array_merge($function($widget), $options);
    }
  }

  return $options;
}

/**
 * Implements hook_filefield_paths_file_check().
 */
function filefield_paths_filefield_paths_file_check($file, $field) {
  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_filefield_paths_file_check")) {
      if ($function($file, $field) == TRUE) {
        return TRUE;
      }
    }
  }

  return FALSE;
}

/**
 * Implements hook_filefield_paths_field_cleanup_option().
 */
function filefield_paths_filefield_paths_field_options() {
  $options = array();

  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_filefield_paths_field_options")) {
      $options = array_merge($function(), $options);
    }
  }

  return $options;
}

/**
 * Implements hook_filefield_paths_field_postprocess().
 */
function filefield_paths_filefield_paths_field_postprocess($value, $field, $settings) {
  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_filefield_paths_field_postprocess")) {
      $function(&$value, $field, $settings);
    }
  }
}

/**
 * Implements hook_filefield_paths_file_postprocess().
 */
function filefield_paths_filefield_paths_file_postprocess($source, $file, $node, $settings) {
  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_filefield_paths_file_postprocess")) {
      $function($source, $file, &$node, $settings);
    }
  }
}
