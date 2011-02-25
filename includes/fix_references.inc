<?php
/**
 * @file
 */

function _filefield_paths_include_fix_references_filefield_paths_file_postprocess($source, $file, $node, $setings) {
  // Regular expression to replace old file reference.
  $file_directory_path = variable_get('file_downloads', FILE_DOWNLOADS_PUBLIC) == FILE_DOWNLOADS_PUBLIC ? file_directory_path() : 'system/files';
  $pattern = array(
    'find' => str_replace('/', '\/', quotemeta($file_directory_path)) . '([^"]*?)' . str_replace('/', '\/', quotemeta(str_replace(file_directory_path(), '', $source))),
    'replace' => $file_directory_path . '$1' . str_replace(file_directory_path(), '', $file['filepath']),
  );

  if (_filefield_paths_replace_pattern($pattern, $node)) {
    // We don't use node_save() here as it triggers hook_nodeapi('save') and
    // causes an infinite loop.
    global $user;

    drupal_write_record('node', $node, 'nid');
    _node_save_revision($node, $user->uid, 'vid');
  }
}

/**
 * Run regular expression over all available text-based fields.
 *
 * @TODO - This section needs work.
 */
function _filefield_paths_replace_pattern($pattern, &$node) {
  $update = FALSE;
  $fields = _filefield_paths_fix_references_fields($node);
//dpm($fields);
  // Loop through all fields and process filter.
  foreach ($fields as &$field) {
    //$old = urldecode($field);
    //dpm($field);
    //dpm($pattern);
    //$field = drupal_urlencode(preg_replace("/{$pattern['find']}/s", $pattern['replace'], urldecode($field)));
    //if ($old != $field) {
    //  $update = TRUE;
    //}
  }

  return $update;
}

function _filefield_paths_fix_references_fields($node) {
  static $fields = array();

  if (!isset($fields[$node->type]) || !is_array($fields[$node->type])) {
    // Node body.
    if (isset($node->body)) {
      $fields[$node->type]['body'] = &$node->body;
      $fields[$node->type]['teaser'] = &$node->body;
    }

    // Node teaser.
    if (isset($node->teaser)) {
      $fields['teaser'] = &$node->teaser;
    }

    // Get CCK fields.
    $content_type = content_types($node->type);
    foreach ($content_type['fields'] as $name => $field) {
      if ($field['type'] == 'text' && is_array($node->{$name})) {
        foreach ($node->{$name} as $key => $value) {
          if (isset($node->{$name}[$key]['value'])) {
            $fields["{$name}_{$key}"] = &$node->{$name}[$key]['value'];
          }
        }
      }
    }
  }

  return $fields[$node->type];
}
