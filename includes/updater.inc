<?php
// $Id$
/**
 * @file
 */

/**
 * Implements hook_filefield_paths_form_options().
 */
function _filefield_paths_include_updater_filefield_paths_form_options($widget) {
  $options = array();

  $options['updater'] = array(
    '#title' => t('Updater mode'),
    '#type' => 'select',
    '#options' => array(
      'none' => t('None'),
      'active' => t('Active'),
      'retroactive' => t('Retroactive'),
      //'retroactive-cron' => t('Retroactive (cron)'),
    ),
    '#default_value' => $widget['updater']
  );

  return $options;
}

/**
 * Implements hook_filefield_paths_file_check().
 */
function _filefield_paths_include_updater_filefield_paths_file_check($file, $field) {
  return $field['widget']['updater'] == 'active';
}

/**
 * Implements hook_widget_settings_alter().
 */
function _filefield_paths_include_updater_widget_settings_alter(&$settings, $op, $widget) {
  if (_filefield_paths_module_supported($widget['module']) && $op == 'save' && in_array($widget['updater'], array('retroactive'/*, 'retroactive-cron'*/))) {
    $nodes = array();

    $field = content_fields($widget['field_name'], $widget['type_name']);
    $db_info = content_database_info($field);

    $result = db_query(
      "SELECT c.nid FROM {%s} c LEFT JOIN {node} n ON c.nid = n.nid WHERE c.%s IS NOT NULL AND n.type = '%s'",
      $db_info['table'], $db_info['columns']['fid']['column'], $widget['type_name']
    );

    // Build array of Node IDs.
    while ($node = db_fetch_object($result)) {
      $nodes[] = $node->nid;
    }

    // Create batch.
    $batch = array(
      'title' => t('Updating FileField Paths'),
      'operations' => array(
        array('_filefield_paths_updater_process', array($nodes, $field))
      ),
    );
    batch_set($batch);
  }
}

function _filefield_paths_updater_process($nodes, $field, &$context) {
  if (!isset($context['sandbox']['progress'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = count($nodes);
    $context['sandbox']['nodes'] = $nodes;
  }

  // Process nodes by groups of 5.
  $count = min(5, count($context['sandbox']['nodes']));
  for ($i = 1; $i <= $count; $i++) {
    // For each nid, load the node, update the files and save it.
    $nid = array_shift($context['sandbox']['nodes']);
    $node = node_load($nid);

    foreach ($node->{$field['field_name']} as $count => &$file) {
      if (is_array($file) && !empty($file['filepath'])) {
        filefield_paths_process_file($file, $field['widget'], $node);
      }
    }

    // Update our progress information.
    $context['sandbox']['progress']++;
  }

  // Inform the batch engine that we are not finished,
  // and provide an estimation of the completion level we reached.
  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}
