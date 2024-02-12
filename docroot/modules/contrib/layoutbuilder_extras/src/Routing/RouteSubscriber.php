<?php

namespace Drupal\layoutbuilder_extras\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('layout_builder.configure_section')) {
      $route->setDefault('_form', '\Drupal\layoutbuilder_extras\Form\LayoutBuilderExtrasConfigureSectionForm');
    }
  }

}
