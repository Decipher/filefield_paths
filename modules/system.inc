<?php
// $Id$
/**
 * @file
 */

/**
 * Implements hook_cron().
 */
function filefield_paths_cron() {
  foreach (_filefield_paths_includes() as $include) {
    if (function_exists($function = "_filefield_paths_include_{$include}_cron")) {
      $function();
    }
  }
}
