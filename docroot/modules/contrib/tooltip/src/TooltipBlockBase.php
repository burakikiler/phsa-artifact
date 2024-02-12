<?php

namespace Drupal\tooltip;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\tooltip\TooltipBlockPluginInterface;

/**
 * Defines a base block implementation that most Tooltip blocks will extend.
 *
 * @ingroup block_api
 */
abstract class TooltipBlockBase extends BlockBase implements TrustedCallbackInterface, TooltipBlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Append arrow.
    $build['#pre_render'][] = [$this, 'setAttributes'];

    // Populate block content.
    $this->tooltip($build);

    // Attach required JS library.
    $build['#attached']['library'][] = 'tooltip/tooltip';

    return $build;
  }

  /**
   * Set custom attributes on block container.
   */
  public function setAttributes($build) {
    $build['#attributes']['class'][] = 'tooltip';
    $build['#attributes']['class'][] = 'visually-hidden';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['setAttributes'];
  }

}
