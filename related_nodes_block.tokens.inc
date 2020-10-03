<?php

use Drupal\related_nodes_block\Module;

/**
* Implements hook_token_info().
*/
function related_nodes_block_token_info() {
  $info = [];

  $info['types'][Module\NAME] = [
    'name' => Module\LABEL,
    'description' => t('Custom token group for @label1', ['@label1' => 'Related Nodes Block']),
    'needs-data' => 'related_nodes_block',
  ];

  $info['tokens'][Module\NAME]['counter'] = [
    'name' => t('Counter'),
    'description' => t("A running counter."),
  ];
  $info['tokens'][Module\NAME]['display-type'] = [
    'name' => t('Display Type'),
    'description' => t("Translated display type."),
  ];
  $info['tokens'][Module\NAME]['display-type-dashed'] = [
    'name' => t('Display Type Dashed Name'),
    'description' => t("Dashed machine name of Display Type."),
  ];

	return $info;
}

/**
* Implements hook_tokens().
*/
function related_nodes_block_tokens($type, $tokens, array $data, array $options, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata) {
  static $display_types = [
    'prev' => 'Previous',
    'next' => 'Next',
    'most_viewed_today' => 'Most Viewed Today',
    'least_viewed_today' => 'Least Viewed Today',
    'most_viewed' => 'Most Viewed',
    'least_viewed' => 'Least Viewed',
    'first' => 'First',
    'last' => 'Last',
    'random' => 'Random',
    'specific' => 'Specific Node',
  ];

  $replacements = [];

  if ($type == Module\NAME && !empty($data['related_nodes_block'])) {
    $replacement_data = $data[Module\NAME];

    foreach ($tokens as $name => $original) {
      if (!array_key_exists($name, $replacement_data)) {
        continue;
      }

      switch ($name) {
        case 'counter':
          $replacements[$original] = $replacement_data[$name];
          break;
        case 'display-type':
          $text = array_key_exists($replacement_data[$name], $display_types)
              ? $display_types[$replacement_data[$name]]
              : 'Invalid display type';
          $replacements[$original] = t($text);
          break;
        case 'display-type-dashed':
          $replacements[$original] = array_key_exists($replacement_data[$name], $display_types)
              ? str_replace('_', '-', $replacement_data[$name])
              : 'invalid-display-type';
          break;
        default:
          break;
      }
    }
  }

  return $replacements;
}
