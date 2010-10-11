<?php
// $Id$
/**
 * @file
 * FileField module integration.
 */

/**
 * Implements hook_filefield_paths_api().
 */
function filefield_filefield_paths_api() {
  return array(
    'api' => 2
  );
}

/**
 * Implements hook_filefield_paths_tokens().
 */
function filefield_filefield_paths_field_tokens() {
  return TRUE;
}

/**
 * Implements hook_file_delete().
 */
function filefield_paths_file_delete($file) {
  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_file_delete")) {
      $function($file);
    }
  }
}
