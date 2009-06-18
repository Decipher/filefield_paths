<?php
// $Id$

/**
 * @file
 * Provides FileField Paths integration with the Image module.
 */

/**
 * Implementation of hook_filefield_paths_form_alter().
 */
function image_filefield_paths_form_alter(&$form, &$ffp) {
  if ($form['#id'] == 'image-admin-settings') {
    $ffp['image'] = array(
      'type' => 'image',
      'form_path' => &$form['ffp_image'],
      'file_path_default' => $form['paths']['image_default_path']['#default_value']
    );

    // Create path settings fieldset
    $ffp['image']['form_path'] = array(
      '#type' => 'fieldset',
      '#title' => t('Image Path settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 0,
    );

    $ffp['image']['form_path']['file_path'] = $form['paths']['image_default_path'];
    $ffp['image']['form_path']['file_path']['#title'] = t('File path');
    $form['paths']['#access'] = FALSE;
  }
}

/**
 * Implementation of hook_filefield_paths_form_submit().
 */
function image_filefield_paths_form_submit(&$form_values, &$ffp) {
  $ffp['image'] = array(
    'type' => 'image',
  );
}

/**
 * Implementation of hook_filefield_paths_get_fields().
 */
function image_filefield_paths_get_fields(&$node, &$ffp) {
  if ($node->type == 'image') {
    $info = array();

    $result = db_query('SELECT * FROM {image} WHERE nid = %d', $node->nid);
    while ($data = db_fetch_object($result)) {
      $info[$data->image_size] = $data->fid;
    }

    foreach ($node->images as $name => &$file) {
      if (isset($info[$name])) {
        $file_info = pathinfo($file);

        $ffp['#files'][] = array(
          'field' => array(
            'filepath' => &$file,
            'filename' => $file_info['basename'],
            'fid' => $info[$name],
            'type' => $name
          ),
          'module' => 'image',
          'name' => 'image',
          'new' => $node->new_file
        );

        $ffp['#types']['image'] = TRUE;
      }
    }
  }
}

/**
 * Implementation of hook_filefield_paths_batch_update().
 */
function image_filefield_paths_update($field_name, $type_name, &$objects) {
  if (empty($field_name) && empty($field_name)) {
    $result = db_query("SELECT nid FROM {node} WHERE type = '%s'", 'image');

    while ($data = db_fetch_object($result)) {
      $node = node_load($data->nid);

      // Flag files for update.
      $node->new_file = TRUE;

      // Process Node.
      filefield_paths_nodeapi($node, 'update', NULL, NULL);
    }
  }
}
