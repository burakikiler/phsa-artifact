<?php

namespace Drupal\tooltip;

/**
 * Defines common methods for Tooltip blocks.
 */
interface TooltipBlockPluginInterface {

  /**
   * Create the block content.
   *
   * @param array $build
   *   The render array, passed by reference.
   */
  public function tooltip(array &$build);

}
