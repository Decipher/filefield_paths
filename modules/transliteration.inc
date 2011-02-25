<?php
/**
 * @file
 * Transliteration module integration.
 */

/**
 * Implements hook_filefield_paths_field_options().
 */
function transliteration_filefield_paths_field_options() {
  return array(
    'transliteration' => t('Transliterate')
  );
}

/**
 * Implements hook_filefield_paths_field_postprocess().
 */
function transliteration_filefield_paths_field_postprocess($value, $field, $settings) {
  if ($settings['transliteration']) {
    $values = explode('/', $value);
    foreach ($values as &$value) {
      $value = transliteration_clean_filename($value);
    }
    $value = implode('/', $values);
  }
}
