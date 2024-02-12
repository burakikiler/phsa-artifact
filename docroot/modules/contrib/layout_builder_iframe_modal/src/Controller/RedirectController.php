<?php

declare(strict_types=1);

namespace Drupal\layout_builder_iframe_modal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to render the iframe redirect page.
 */
class RedirectController extends ControllerBase {

  /**
   * The attachment Processor.
   *
   * @var \Drupal\Core\Render\HtmlResponseAttachmentsProcessor
   */
  protected $attachmentProcessor;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * RedirectController constructor.
   *
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $attachment_processor
   *   The Container.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(AttachmentsResponseProcessorInterface $attachment_processor, RendererInterface $renderer) {
    $this->attachmentProcessor = $attachment_processor;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('html_response.attachments_processor'),
      $container->get('renderer')
    );
  }

  /**
   * Renders and returns the redirect page.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function content(): HtmlResponse {
    $build = [
      '#theme' => 'html',
      'page' => [
        '#theme' => 'lbim_redirect',
        '#title' => 'Redirecting...',
      ],
    ];
    $this->renderer->renderRoot($build);

    $response = new HtmlResponse();
    $response->setContent($build);
    $response = $this->attachmentProcessor->processAttachments($response);

    return $response;
  }

}
