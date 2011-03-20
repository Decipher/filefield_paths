<?php
/**
 * @file
 * FileField Paths module integration.
 */

/**
 * Implements hook_filefield_paths_field_settings().
 */
function filefield_paths_filefield_paths_field_settings() {
  return array(
    'file_path' => array(
      'title' => 'File path',
      'sql' => 'filepath',

      'form' => array(
        'file_path' => array(
          '#maxlength' => 512,
          '#size' => 128,
        ),
      ),
    ),

    'file_name' => array(
      'title' => 'File name',
      'sql' => 'filename',

      'form' => array(
        'file_name' => array(
          '#type' => 'textfield',
          '#title' => t('File name'),
          '#default_value' => '[filefield-onlyname-original].[filefield-extension-original]',
        ),
      ),
    )
  );
}
