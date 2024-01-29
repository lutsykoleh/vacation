<?php

/**
 * @file
 * Includes a helper function for debug/devel purposes.
 *
 * Should not be used in productions sites.
 */

/**
 * Helper function for D8-port: Get some info on screen.
 *
 * @param string $class_name
 * @param string $function_name
 * @param string $line_nr
 * @param string $value1
 * @param string $value2
 *
 * @see workflow_devel-module
 *
 * Usage:
 *   workflow_debug(__FILE__, __FUNCTION__, __LINE__, '', '');
 */
function workflow_debug($class_name, $function_name, $line_nr = '', $value1 = '', $value2 = '') {
  $debug_switch = FALSE;
  // $debug_switch = TRUE;

  if (!$debug_switch) {
    return;
  }

  $class_name_elements = explode("\\", $class_name);
  $output = 'Testing... function ' . end($class_name_elements) . '::' . $function_name . '/' . $line_nr;
  if ($value1) {
    $output .= ' = ' . $value1;
  }
  if ($value2) {
    $output .= ' > ' . $value2;
  }
  \Drupal::messenger()->addWarning($output, TRUE);
}
