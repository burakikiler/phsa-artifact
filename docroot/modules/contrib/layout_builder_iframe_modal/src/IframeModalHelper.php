<?php

namespace Drupal\layout_builder_iframe_modal;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Helper for config.
 */
class IframeModalHelper {

  /**
   * Enabled routes.
   *
   * @var string[]
   */
  protected $enabledRoutes;

  /**
   * IframeModalHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $config = $config_factory->get('layout_builder_iframe_modal.settings');
    $layout_builder_routes = $config->get('layout_builder_iframe_routes') ?? [];
    $custom_routes = $config->get('custom_routes') ?? [];

    $this->enabledRoutes = array_merge($layout_builder_routes, $custom_routes);
  }

  /**
  * Check if the given route is enabled.
  *
  * @param string $route_name
  *   Name of the route to check.
  *
  * @return bool
  *   The route is enabled and links should open in the iframe modal.
  */
  public function isModalRoute(string $route_name) {
    return in_array($route_name, $this->enabledRoutes);
  }
}
