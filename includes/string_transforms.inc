<?php
/**
 * @file
 */

function _filefield_paths_include_string_transforms_filefield_paths_field_options() {
  return array(
    'strtolower' => t('Convert to lower case')
  );
}

function _filefield_paths_include_string_transforms_filefield_paths_field_postprocess($value, $field, $settings) {
  if ($settings['strtolower']) {
    $value = drupal_strtolower($value);
  }
}
