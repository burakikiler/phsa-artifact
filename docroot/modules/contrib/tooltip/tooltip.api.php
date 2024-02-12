<?php

/**
 * @file
 * Hooks and documentation related to the tooltip module.
 */

/**
 * Alter the list of blocks available in Tooltip editor dialog.
 *
 * @param array $blocks
 *   An array of block instances.
 *
 * @see \Drupal\tooltip\Form\EditorTooltipDialog::buildForm();
 *
 * @ingroup menu
 */
function hook_tooltip_block_list_alter(array &$blocks) {
  // Example: Load/Create a custom block for specific users.
  if (\Drupal::currentUser()->hasPermission('a custom permission')) {
    $bid = 'a_custom_block';
    $block = \Drupal\block\Entity\Block::load($bid);
    if (!$block instanceof \Drupal\block\Entity\Block) {
      $plugin_id = 'system_powered_by_block';
      $block = \Drupal\block\Entity\Block::create(['id' => $bid, 'plugin' => $plugin_id]);
      $block->save();
    }

    if ($block instanceof \Drupal\block\Entity\Block) {
      $blocks[$block->uuid()] = $block->label() . ' (custom)';
    }
  }
}
