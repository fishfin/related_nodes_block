<?php

namespace Drupal\related_nodes_block\Module;

use Drupal\Core\Template\Attribute;

/** @var string NAME Machine name of the module. */
const NAME = 'related_nodes_block';

/** @var string LABEL Human-readable label of the module. */
const LABEL = 'Related Nodes Block';

/** @var string MODULE_WEBPAGE Module webpage. */
const MODULE_WEBPAGE = 'https://drupal.org/project/related_nodes_block';

/** @var boolean DEBUG_MODE Only when true, debug function writes messages. */
const DEBUG_MODE = FALSE;

/**
 * Writes variable values to a static debug file.

 * @param mixed $var
 *   Variable to print.
 * @param string $processor
 *   Base PHP function to use.
 *
 */
function debug($var, $php_func='') {
  static $log_dir = __DIR__ . '/logs';

  if (!DEBUG_MODE) {
    return;
  }

  switch ($php_func) {
    case 'pr':
      $var = print_r($var, TRUE) . PHP_EOL;
      break;
    case 'vd':
      ob_start();
      var_dump($var);
      $var = ob_get_clean();
      ob_end_clean();
      break;
    default:
      if (!is_string($var)) {
        $var = 'DEBUG: Cannot print non-string input.';
      }
      $var .=  PHP_EOL;
      break;
  }

  if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, TRUE);
  }
  file_put_contents($log_dir . '/debug.log', '[' . date('Y-m-d h:i:s') . '] ' . $var, FILE_APPEND);
}

/**
 * Returns an array of attributes for a rendereable element generated from
 * plain-text. Does not include the wrapping '#attributes' key.
 *
 * @param string $newline_text
 *   A string containing attributes on a new line of the format:
 *   attr-1|attr-1-val-1\n
 *   attr-2|attr-2-val-1\n
 *   attr-1|attr-2-val-2
 *
 * @return array
 *   An associative array of attributes.
 *   Above example returns:
 *   ['attr-1' => ['attr-1-val-1', 'attr-1-val-2'],
 *    'attr-2' => ['attr-2-val-1'],]
 */
function text_to_renderable_attr($newline_text) {
  $attributes = [];

  $lines = preg_split("/\r\n|\n|\r/", $newline_text);

  foreach ($lines as $line) {
    $line = trim($line);
    $items = explode('|', $line, 2);
    if (count($items) < 2
      or empty($key = trim($items[0], " \t\n\r\0\x0B\""))
      or empty($value = trim($items[1], " \t\n\r\0\x0B\""))) {
      continue;
    }

    // Replacing xcess whitespaces with single '-'
    $key = preg_replace('/\s+/', '-', $key);
    // Removing excess whitespaces
    $value = preg_replace('/\s+/', ' ', $value);

    if (!array_key_exists($key, $attributes)) {
      $attributes[$key] = [];
    }
    if (!in_array($value, $attributes[$key])) {
      $attributes[$key][] = $value;
    }
  }

  return $attributes;
}

/**
 * Returns a text string of attributes from renderable attributes array.
 *
 * @param array $renderable_attributes
 *   A string or an array (list) containing the content type(s).
 *
 * @return string
 *   An associative array of attributes.
 */
function renderable_attr_to_text($renderable_attributes) {
  if (array_key_exists('#attributes', $renderable_attributes)) {
    $renderable_attributes = $renderable_attributes['#attributes'];
  }

  return (string) new Attribute($renderable_attributes);
}
