<?php
// $Id$

/**
 * @file
 * Provides FileField Paths integration with the FileField module.
 */

/**
 * Implementation of hook_filefield_paths_form_alter().
 */
function filefield_filefield_paths_form_alter(&$form, &$ffp) {
  if (isset($form['module']['#value']) && preg_match('/\bfilefield\b|\bimagefield\b/', $form['module']['#value'])) {
    $ffp[$form['field_name']['#value']] = array(
      'show' => TRUE,
      'type' => $form['type_name']['#value'],
      'form_path' => &$form['widget']['ffp_'. $form['field_name']['#value']],
      'file_path_default' => $form['widget']['path_settings']['file_path']['#default_value']
    );

    // Create path settings fieldset
    $ffp[$form['field_name']['#value']]['form_path'] = array(
      '#type' => 'fieldset',
      '#title' => t('FileField Path settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE
    );

    $filepath = ($form['module']['#value'] == 'filefield') ? 'file_path' : 'image_path';
    $form['widget'][$filepath]['#description'] = t('Optional subdirectory within the "%dir" directory where files will be stored. Do not include trailing slash.', array('%dir' => variable_get('file_directory_path', 'files')));
    if ($filepath == 'image_path') {
      unset($form['widget'][$filepath]['#suffix']);
    }

    // Move file path field into path settings fieldset
    $ffp[$form['field_name']['#value']]['form_path'][$filepath] = $form['widget'][$filepath];
    $form['widget'][$filepath]['#access'] = FALSE;

    $form['widget']['description']['#weight'] = 10;
    $ffp[$form['field_name']['#value']]['form_path']['#attributes'] = array('id' => 'fieldset-path_settings');
  }
}

/**
 * Implementation of hook_filefield_paths_form_submit().
 */
function filefield_filefield_paths_form_submit(&$form_values, &$ffp) {
  if ($form_values['form_id'] != 'node_type_form') {
    $ffp[$form_values['field_name']] = array(
      'type' => $form_values['type_name'],
    );

    $form_values['file_path'] = $form_values['ffp_'. $form_values['field_name']]['file_path'];
  }
}

/**
 * Implementation of hook_filefield_paths_get_fields().
 */
function filefield_filefield_paths_get_fields(&$node, &$ffp) {
  if (is_object($node) && $node->form_id == $node->type .'_node_form') {
    $content_type = content_types($node->type);

    foreach ($content_type['fields'] as $field) {
      if (preg_match('/\bfile\b|\bimage\b/', $field['type']) && isset($node->$field['field_name']) && is_array($node->$field['field_name'])) {

        foreach ($node->$field['field_name'] as &$file) {
          $ffp['#files'][] = array(
            'field' => &$file,
            'module' => $field['type'],
            'name' => $field['field_name'],
            'widget' => $field['widget']['type'],
            'new' => (!empty($file['filepath']) && !empty($file['data']['process'])) ? TRUE : FALSE
          );

          $ffp['#types'][$field['field_name']] = TRUE;
        }

      }
    }
  }
}

/**
 * Implementation of hook_filefield_paths_batch_update().
 */
function filefield_filefield_paths_update($field_name, $type_name) {
  if (!empty($field_name)) {
    $field = content_fields($field_name, $type_name);
    $db_info = content_database_info($field);

    $result = db_query(
      "SELECT c.nid FROM {%s} c LEFT JOIN {node} n ON c.nid = n.nid WHERE c.%s IS NOT NULL
      AND n.type = '%s'", $db_info['table'], $db_info['columns']['fid']['column'], $type_name
    );

    while ($data = db_fetch_object($result)) {
      $node = node_load($data->nid);

      // Flag files for update.
      if (isset($node->$field_name)) {
        foreach ($node->$field_name as &$file) {
          if (empty($file['filepath'])) {
            continue;
          }

          $file['data']['process'] = TRUE;
        }
      }

      // Set Form ID.
      $node->form_id = $node->type .'_node_form';

      // Process Node.
      filefield_paths_nodeapi($node, 'update', NULL, NULL);
    }
  }
}
