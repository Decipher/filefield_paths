<?php

/**
 * @file
 * Provides FileField Paths integration with the File module.
 */

/**
 * Implements hook_filefield_paths_form_alter().
 */
function file_filefield_paths_form_alter(&$form, &$ffp) {
  if (isset($form['#field']) && $form['#field']['type'] == 'file' && isset($form['instance']['settings']['file_directory'])) {
    $ffp[$form['#field']['field_name']] = array(
      'show' => TRUE,
      'type' => $form['instance']['bundle']['#value'],
      'form_path' => &$form['instance']['ffp_'. $form['#field']['field_name']],
      'file_path_default' => $form['instance']['settings']['file_directory']['#default_value']
    );

    // Create path settings fieldset
    $ffp[$form['#field']['field_name']]['form_path'] = array(
      '#type' => 'fieldset',
      '#title' => t('File Path settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 1,
    );

    $ffp[$form['#field']['field_name']]['form_path']['file_path'] = $form['instance']['settings']['file_directory'];
    $ffp[$form['#field']['field_name']]['form_path']['file_path']['#title'] = t('File path');
    $form['instance']['settings']['file_directory']['#access'] = FALSE;
  }
}

/**
 * Implements hook_filefield_paths_form_submit().
 */
function file_filefield_paths_form_submit(&$form_state, &$ffp) {
  if (isset($form_state['values']['form_id']) && $form_state['values']['form_id'] != 'node_type_form') {
    $form_state['values']['ffp_' . $form_state['values']['instance']['field_name']] = $form_state['values']['instance']['ffp_' . $form_state['values']['instance']['field_name']];
    $ffp[$form_state['values']['instance']['field_name']] = array(
      'type' => $form_state['values']['instance']['bundle'],
    );

    $form_state['values']['instance']['settings']['file_directory'] = $form_state['values']['ffp_' . $form_state['values']['instance']['field_name']]['file_path'];
  }
}

/**
 * Implements hook_filefield_paths_get_fields().
 */
function file_filefield_paths_get_fields(&$node, &$ffp) {
  if (is_object($node)) {
    // TODO: Replace with field_info_fields() when http://drupal.org/node/613754 is fixed.
    $instances = field_info_instances('node', $node->type);
    $fields = array();
    foreach ($instances as $field_name => $instance) {
      $fields[$field_name] = field_info_field($field_name);
    }

    foreach ($fields as $name => $field) {
      if ($field['type'] == 'file' && isset($node->{$field['field_name']}) && is_array($node->{$field['field_name']})) {

        foreach ($node->{$field['field_name']} as $language) {
          foreach ($language as $file) {
            $new = !isset($file['uri']) ? TRUE : FALSE;
            $file = !isset($file['uri']) ? file_load($file['fid']) : (object) $file;

            $ffp['#files'][] = array(
              'field' => (array) $file,
              'module' => $field['module'],
              'name' => $field['field_name'],
              'new' => ($new || isset($file->new)) ? TRUE : FALSE,
            );

            $ffp['#types'][$field['field_name']] = TRUE;
          }
        }
      }
    }
  }
}

/**
 * Implements hook_filefield_paths_batch_update().
 */
function image_filefield_paths_batch_update($field, $type, &$objects) {
  $instance = field_info_instance('node', $field, $type);

  // TODO This needs to be replaced with EntityFieldQuery
  //$results = field_attach_query($instance['field_id'], array(array('bundle', array($type))));

  // Build array of Node IDs.
  foreach ($results['node'] as $node) {
    $objects[] = $node->nid;
  }
}

/**
 * Implements hook_filefield_paths_update().
 */
function image_filefield_paths_update($oid, $field) {
  $node = node_load($oid);

  // Flag files for update.
  if (isset($node->{$field})) {
    foreach ($node->{$field} as &$language) {
      foreach ($language as &$file) {

        if (!is_array($file) || empty($file['uri'])) {
          continue;
        }

        $file['new'] = TRUE;
      }
    }
  }
  // Process Node.
  filefield_paths_node_update($node);
}
