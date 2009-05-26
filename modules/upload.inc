<?php
// $Id$

/**
 * @file
 * Provides FileField Paths integration with the Upload module.
 */

/**
 * Implementation of hook_filefield_paths_form_alter().
 */
function upload_filefield_paths_form_alter(&$form, &$ffp) {
  if (isset($form['#id']) && $form['#id'] == 'node-type-form') {
    $ffp['upload'] = array(
      'type' => $form['#node_type']->type,
      'form_path' => &$form['workflow']['ffp_upload'],
      'file_path_default' => ''
    );

    // Create path settings fieldset
    $ffp['upload']['form_path'] = array(
      '#type' => 'fieldset',
      '#title' => t('Upload Path settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => $form['workflow']['upload']['#weight'] + 1,
    );

    $ffp['upload']['form_path']['file_path'] = array(
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
function upload_filefield_paths_form_submit(&$form_values, &$ffp) {
  if ($form_values['form_id'] == 'node_type_form') {
    $ffp['upload'] = array(
      'type' => $form_values['type'],
    );
  }
}

/**
 * Implementation of hook_filefield_paths_get_fields().
 */
function upload_filefield_paths_get_fields(&$node, &$ffp) {
  if (is_object($node) && $node->form_id == $node->type .'_node_form') {
    if (isset($node->files)) {
      foreach ($node->files as &$file) {
        $ffp['#files'][] = array(
          'field' => &$file,
          'module' => 'upload',
          'name' => 'upload',
          'new' => is_object($file)
        );

        $ffp['#types']['upload'] = TRUE;
      }
    }
  }
}

/**
 * Implementation of hook_filefield_paths_batch_update().
 */
function upload_filefield_paths_update($field_name, $type_name) {
  if (empty($field_name)) {
    $result = db_query(
      "SELECT f.nid FROM {file_revisions} r LEFT JOIN {files} f ON r.fid = f.fid LEFT JOIN {node} n
      ON f.nid = n.nid WHERE n.type = '%s'", $type_name
    );

    while ($data = db_fetch_object($result)) {
      $node = node_load($data->nid);

      // Set Form ID.
      $node->form_id = $node->type .'_node_form';

      // Process Node.
      filefield_paths_nodeapi($node, 'update', NULL, NULL);
    }
  }
}
