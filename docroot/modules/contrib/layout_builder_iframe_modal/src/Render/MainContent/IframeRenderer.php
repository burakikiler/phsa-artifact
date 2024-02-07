<?php

declare(strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Render\MainContent;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\layout_builder_iframe_modal\Ajax\OpenIframeCommand;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\MainContent\DialogRenderer;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Renders the requested page inside an iframe.
 *
 * The renderer returns the markup of an iframe, with the currently requested
 * url set as the iframe src. A destination query parameter is appended to the
 * url. This destination route will indicate to the iframe's parent window that
 * the dialog containing the iframe can be closed.
 */
class IframeRenderer extends DialogRenderer {

  /**
   * {@inheritdoc}
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match): AjaxResponse {
    $response = new AjaxResponse();

    // Build the iframe source link.
    $iframe_src = Url::createFromRequest($request);

    // Set the destination query parameter to our custom redirect route.
    $iframe_src->setOption('query', [
      'destination' => Url::fromRoute('layout_builder_iframe_modal.redirect')->toString(TRUE)->getGeneratedUrl(),
    ]);

    $iframe_attributes = new Attribute([
      'src' => $iframe_src->toString(),
      'class' => ['lbim-dialog-iframe'],
      'name' => 'lbim-dialog-iframe',
    ]);

    $render_array = [
      '#theme' => 'lbim_iframe',
      '#iframe_attributes' => $iframe_attributes,
    ];

    $content = $this->renderer->renderRoot($render_array);
    $response->setAttachments([
      'library' => [
        'layout_builder_iframe_modal/iframe',
        'core/drupal.dialog.ajax',
        'core/jquery.ui.resizable',
        'core/jquery.ui.draggable',
      ],
    ]);

    $title = isset($main_content['#title']) ? $main_content['#title'] : $this->titleResolver->getTitle($request, $route_match->getRouteObject());

    if (is_array($title)) {
      $title = $this->renderer->renderPlain($title);
    }

    $options = $request->request->all('dialogOptions');
    $response->addCommand(new OpenIframeCommand($title, $content, $options));

    return $response;
  }

}
