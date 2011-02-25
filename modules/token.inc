<?php
/**
 * @file
 * Token module integration.
 */

/**
 * Implements hook_token_list().
 */
function filefield_paths_token_list($type = 'all') {
  if ($type == 'field' || $type == 'filefield_paths' || $type == 'all') {
    $tokens = array();
    if (!module_exists('filefield')) {
      $tokens['file']['filefield-onlyname'] = t("File name without extension");
      $tokens['file']['filefield-extension'] = t("File extension");
    }
    $tokens['file']['filefield-onlyname-original'] = t("File name without extension - original");
    $tokens['file']['filefield-extension-original'] = t("File extension - original");

    // Invoke hook_filefield_paths_tokens().
    foreach (module_implements('filefield_paths_field_tokens') as $module) {
      $function = "{$module}_token_list";
      $tokens = array_merge_recursive($function('field'), $tokens);
    }

    return $tokens;
  }
}

/**
 * Implements hook_token_values().
 */
function filefield_paths_token_values($type, $object = NULL) {
  if ($type == 'field' || $type == 'filefield_paths') {
    $item = pathinfo($object[0]['filename']);
    $tokens = array();
    if (!module_exists('filefield')) {
      // PHP < 5.2: pathinfo() doesn't return 'filename' variable.
      $tokens['filefield-onlyname'] = isset($item['filename'])
        ? $item['filename']
        : basename($object[0]['filename'], ".{$item['extension']}");
      $tokens['filefield-extension'] = $item['extension'];
    }
    // Original filename.
    $orig = $item;
    $filename = $object[0]['filename'];
    $result = db_fetch_object(db_query("SELECT origname FROM {files} WHERE fid = %d", $object[0]['fid']));
    if (!empty($result->origname)) {
      $object[0]['origname'] = $result->origname;
    }
    if (!empty($object[0]['origname'])) {
      $orig = pathinfo($object[0]['origname']);
      $filename = $object[0]['origname'];
    }
    // PHP < 5.2: pathinfo() doesn't return 'filename' variable.
    $tokens['filefield-onlyname-original'] = isset($orig['filename'])
      ? $orig['filename']
      : basename($filename, ".{$orig['extension']}");
    $tokens['filefield-extension-original'] = $orig['extension'];

    // Invoke hook_filefield_paths_tokens().
    foreach (module_implements('filefield_paths_field_tokens') as $module) {
      $function = "{$module}_token_values";
      $tokens = array_merge_recursive($function('field'), $tokens);
    }

    return $tokens;
  }
}
