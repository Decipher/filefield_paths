<?php
/**
 * @file
 * Trash cleanup functionality for FileField Paths module.
 */

/**
 * Implements hook_filefield_paths_file_postprocess().
 */
function _filefield_paths_include_trash_filefield_paths_file_postprocess($source, $file, $node, $setings) {
  $trash = unserialize(variable_get('filefield_paths_trash', serialize(array())));

  // Add directory to trash queue.
  $trash[dirname($source)] = TRUE;

  variable_set('filefield_paths_trash', serialize($trash));
}

/**
 * Implements hook_file_delete().
 */
function _filefield_paths_include_trash_file_delete($file) {
  $trash = unserialize(variable_get('filefield_paths_trash', serialize(array())));

  // Add directory to trash queue.
  $trash[dirname($file->filepath)] = TRUE;

  variable_set('filefield_paths_trash', serialize($trash));
}

/**
 * Implements hook_cron().
 */
function _filefield_paths_include_trash_cron() {
  $trash = unserialize(variable_get('filefield_paths_trash', serialize(array())));
  foreach (array_keys($trash) as $directory) {
    filefield_paths_trash($directory);
    unset($trash[$directory]);
  }
  variable_set('filefield_paths_trash', serialize($trash));
}

/**
 * Recursively delete empty directories from top down.
 */
function filefield_paths_trash($directory) {
  while ($directory != file_directory_path()) {
    $directory_array = explode('/', $directory);
    if (@rmdir($directory) === TRUE) {
      array_pop($directory_array);
      $directory = implode('/', $directory_array);
      continue;
    }
    break;
  }
}
