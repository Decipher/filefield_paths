<?php
// $Id$

/**
 * @file
 * Provides FileField Paths integration with the Comment Upload module.
 */

/**
 * Implementation of hook_filefield_paths_form_alter().
 */
function comment_upload_filefield_paths_form_alter(&$form, &$ffp) {
  if (isset($form['#id']) && $form['#id'] == 'node-type-form') {
    $ffp['comment_upload'] = array(
      'type' => $form['#node_type']->type,
      'form_path' => &$form['workflow']['ffp_comment_upload'],
      'file_path_default' => ''
    );

    // Create path settings fieldset
    $ffp['comment_upload']['form_path'] = array(
      '#type' => 'fieldset',
      '#title' => t('Comment Upload Path settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => $form['workflow']['comment_upload']['#weight'] + 1,
    );

    $ffp['comment_upload']['form_path']['file_path'] = array(
      '#type' => 'textfield',
      '#title' => t('File path'),
      '#description' => t('Optional subdirectory within the "%dir" directory where files will be stored. Do not include trailing slash.', array('%dir' => variable_get('file_directory_path', 'files'))),
      '#tree' => TRUE,
    );
  }
}

/**
 * Implementation of hook_filefield_paths_form_submit().
 */
function comment_upload_filefield_paths_form_submit(&$form_values, &$ffp) {
  if ($form_values['form_id'] == 'node_type_form') {
    $ffp['comment_upload'] = array(
      'type' => $form_values['type'],
    );
  }
}

/**
 * Implementation of hook_filefield_paths_get_fields().
 */
function comment_upload_filefield_paths_get_fields(&$node, &$ffp) {
  if (is_object($node) && $node->form_id == 'comment_form') {
    if (isset($node->files)) {
      foreach ($node->files as &$file) {
        $new = FALSE;
        if (preg_match('/upload_([\d])/', $file['fid'], $match)) {
          $new = TRUE;
          $file = (array) $node->processed_files[$match[1]];
        }

        $ffp['#files'][] = array(
          'field' => &$file,
          'module' => 'comment_upload',
          'name' => 'comment_upload',
          'new' => $new,
        );

        $ffp['#types']['comment_upload'] = TRUE;
      }
    }
  }
}

/**
 * Implementation of hook_filefield_paths_batch_update().
 */
function comment_upload_filefield_paths_update($field_name, $type_name) {
  if (empty($field_name)) {
    $result = db_query(
      "SELECT DISTINCT(cu.cid) FROM {comment_upload_files} cu LEFT JOIN {node} n
      ON cu.nid = n.nid WHERE n.type = '%s'", $type_name
    );

    while ($data = db_fetch_object($result)) {
      $comment = _comment_load($data->cid);
      comment_invoke_comment($comment, 'view');

      // Flag files for update.
      if (isset($comment->files)) {
        foreach ($comment->files as $id => &$file) {
          $file = (array) $file;
          $file['fid'] = 'upload_'. $id;
        }
      }

      // Set Form ID.
      $comment->form_id = 'comment_form';

      // Process Comment.
      filefield_paths_comment((array) $comment, 'update');
    }
  }
}

/**
 * Implementation of hook_comment().
 */
function filefield_paths_comment($a1, $op) {
  switch ($op) {
    case 'insert':
    case 'update':
      $update = new stdClass;
      $update->node = FALSE;
      $node = node_load($a1['nid']);

      $object = new stdClass();
      $object->form_id = $a1['form_id'];
      $object->type = $node->type;
      $object->files = $a1['files'];
      $object->processed_files = _comment_upload_load_files($a1['cid']);

      if (($ffp = filefield_paths_get_fields($object)) == FALSE) {
        break;
      }

      // Process files
      foreach ($ffp['#files'] as &$file) {
        module_invoke_all('filefield_paths_process_file', $file['new'], $file, $ffp['#settings'][$file['name']], $node, $update);
        if ($file['new'] == TRUE) {
          db_query(
            "UPDATE {comment_upload_files} SET filename = '%s', filepath = '%s' WHERE fid = %d",
            $file['field']['filename'], $file['field']['filepath'], $file['field']['fid']
          );
        }
      }

      break;
  }
}
