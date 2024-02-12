<?php

namespace Drupal\layout_builder_iframe_modal\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to scroll to the position before rebuild.
 *
 * @ingroup ajax
 */
class ScrollToBlockCommand implements CommandInterface {

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return [
      'command' => 'scrollToBlock',
    ];
  }

}
