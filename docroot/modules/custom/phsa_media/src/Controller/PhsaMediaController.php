<?php

namespace Drupal\phsa_media\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\OEmbed\Resource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns responses for PHSA Media routes.
 */
final class PhsaMediaController extends ControllerBase {

  const PHSA_MEDIA_SERVER_URL = 'https://media.phsa.ca/Video/';

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The Image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected ImageFactory $imageFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->requestStack = $container->get('request_stack');
    $instance->imageFactory = $container->get('image.factory');
    $instance->renderer = $container->get('renderer');
    $instance->cacheBackend = $container->get('cache.default');

    return $instance;
  }

  /**
   * Get video from the PHSA Media server.
   *
   * This is a proxy endpoint that helps us to simulate OEmbed functionality.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response with oembed compatible data.
   *
   * @throws \Exception
   */
  public function oembed(): JsonResponse {
    $errorMessage = $this->t('The link is not valid.');

    // Verify the URL has the required get parameter.
    $url = $this->requestStack->getCurrentRequest()?->get('url');
    if (!$url) {
      throw new BadRequestHttpException($errorMessage);
    }

    $cacheId = "phsa_media:oembed_resource:$url";
    if ($cached = $this->cacheBackend->get($cacheId)) {
      return new JsonResponse($cached->data);
    }

    // Verify that parameter has required GET parameter.
    $url = UrlHelper::parse($url);
    if (!isset($url['query']['url'])) {
      throw new BadRequestHttpException($errorMessage);
    }
    $fileName = $url['query']['url'];

    $thumbnailUrl = self::PHSA_MEDIA_SERVER_URL . $fileName . '.jpeg';

    // Load image and verify that it was downloaded. Otherwise, we assume that
    // the link is incorrect.
    $image = $this->imageFactory->get($thumbnailUrl);
    if (!$image->isValid()) {
      throw new BadRequestHttpException($errorMessage);
    }

    // Prepare render array for the response.
    $html = [
      '#theme' => 'phsa_media_video',
      '#video_url' => self::PHSA_MEDIA_SERVER_URL . $fileName . '.mp4',
    ];

    $data = [
      'type' => Resource::TYPE_VIDEO,
      'provider_name' => 'PHSA',
      'version' => '1.0',
      'thumbnail_url' => $thumbnailUrl,
      'thumbnail_width' => $image->getWidth(),
      'thumbnail_height' => $image->getHeight(),
      'html' => $this->renderer->render($html),
    ];

    $this->cacheBackend->set($cacheId, $data);

    return new JsonResponse($data);
  }

}
