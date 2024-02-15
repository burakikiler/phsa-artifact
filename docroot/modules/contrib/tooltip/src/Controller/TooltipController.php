<?php

namespace Drupal\tooltip\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for tooltip module routes.
 */
class TooltipController extends ControllerBase {

  use AjaxHelperTrait;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs our object.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * Presents the aggregator feed creation form.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the block content in "html".
   */
  public function getBlock($block_id = NULL) {
    $block = NULL;
    $block_storage = $this->entityTypeManager()->getStorage('block');
    $block_content_storage = NULL;
    if ($this->entityTypeManager()->hasHandler('block_content', 'storage')) {
      $block_content_storage = $this->entityTypeManager()->getStorage('block_content');
    }

    // Lookup for a block by UUID.
    if (Uuid::isValid($block_id)) {
      $blocks = $block_storage->loadByProperties(['uuid' => $block_id]);
      if (empty($blocks) && $block_content_storage) {
        $blocks = $block_content_storage->loadByProperties(['uuid' => $block_id]);
      }
      $block = reset($blocks);
    }

    // Lookup a block by ID.
    if (!$block) {
      $block = $block_storage->load($block_id);
      if (!$block && $block_content_storage) {
        $block = $block_content_storage->load($block_id);
      }
    }

    $build = [];
    $content = NULL;

    // Check block access.
    if ($block && $block->access('view')) {
      $build = $this->entityTypeManager()
        ->getViewBuilder($block->getEntityTypeId())
        ->view($block, '');
    }

    if (!$this->isAjax()) {
      return ['#markup' => $this->renderer->render($build)];
    }

    $output = $this->renderer->renderPlain($build);

    $response = new JsonResponse([
      'content' => $output,
    ], 201);

    // Required to verify Ajax call.
    $response->headers->set('X-Drupal-Ajax-Token', 1);

    return $response;
  }

}
