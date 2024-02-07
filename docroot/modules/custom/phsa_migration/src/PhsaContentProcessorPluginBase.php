<?php

namespace Drupal\phsa_migration;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateStubInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for PHSA Content Processor plugins.
 */
abstract class PhsaContentProcessorPluginBase extends PluginBase implements PhsaContentProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The content properties object to hold the already parsed data.
   *
   * @var array
   */
  protected array $contentProperties;

  /**
   * Migrate lookup service.
   *
   * @var \Drupal\migrate\MigrateLookupInterface
   */
  protected MigrateLookupInterface $migrateLookup;

  /**
   * The migrate stub service.
   *
   * @var \Drupal\migrate\MigrateStubInterface
   */
  protected MigrateStubInterface $migrateStub;

  /**
   * The external media mapping handler.
   *
   * @var \Drupal\phsa_migration\MediaMappingHandler
   */
  protected MediaMappingHandler $mediaMappingHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    $plugin->contentProperties = [];
    $plugin->migrateLookup = $container->get('migrate.lookup');
    $plugin->migrateStub = $container->get('migrate.stub');
    $plugin->mediaMappingHandler = $container->get('phsa_migration.media_mapping_handler');
    $plugin->entityTypeManager = $container->get('entity_type.manager');

    return $plugin;
  }

  /**
   * Gets the image source from an image DOM element.
   *
   * @param \DOMElement $element
   *   The image DOM element or its parent.
   *
   * @return string|null
   *   The image source URL.
   */
  protected function getImageSrc(\DOMElement $element): ?string {
    $image_element = $element->tagName === 'img' ? $element : $element->getElementsByTagName('img')->item(0);
    if ($image_element) {
      /** @var \DOMElement $image_element */
      return $image_element->getAttribute('src');
    }

    return NULL;
  }

  /**
   * Find the sharepoint rich content element from a DOMNode.
   *
   * @param \DOMXPath $xpath
   *   The parent DOM XPath document.
   * @param \DOMNode $node
   *   The DOM node element to search the rich content wrapper.
   * @param string $id
   *   The element ID of the rich content wrapper.
   *
   * @return \DOMElement|null
   *   The found rich content element, or NULL if not found.
   */
  protected function findBodyElement(\DOMXPath $xpath, \DOMNode $node, string $id = 'ctl00_PlaceHolderMain_SubPlaceholder_ctl07__ControlWrapper_RichHtmlField'): ?\DOMElement {
    $body_element = $xpath->query("//div[@id='$id']", $node);

    return $body_element->length > 0 ? $body_element->item(0) : NULL;
  }

  /**
   * Processes the external content file to create a media stub.
   *
   * @param string $url
   *   The media file external URL.
   * @param string $file_type
   *   The media file type.
   * @param string $migration_lookup_id
   *   The migration ID to run the lookup against. The migration ID should be
   *   the migration in charge of processing the media files for the content
   *   being processed.
   * @param string|null $source_content_id
   *   The source content where the file was found. If not provided, it
   *   fallbacks to the plugin that invokes this method.
   *
   * @return int|null
   *   The media file ID or stub.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function processContentMediaFile(string $url, string $file_type, string $migration_lookup_id, ?string $source_content_id = NULL): ?int {
    try {
      // Theoretically, it shouldn't find the file with this URL, because the
      // migration processing the content (i.e., the one calling this method)
      // acts as the source for retrieving all the related media URLs that will
      // be later migrated. However, it can find a stub value.
      $destination_id = $this->migrateLookup->lookup([$migration_lookup_id], [$url]);
    }
    catch (\Exception $e) {
      throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
    }

    if (!$destination_id) {
      try {
        $destination_id = $this->migrateStub->createStub($migration_lookup_id, [$url], [], FALSE);
        if ($destination_id !== FALSE) {
          $destination_id = reset($destination_id);
        }
      }
      catch (\Exception $e) {
        throw new MigrateException(sprintf('%s was thrown while attempting to stub: %s', get_class($e), $e->getMessage()), $e->getCode(), $e);
      }
    }
    else {
      $destination_id = reset($destination_id)['mid'];
    }

    if ($destination_id) {
      // The stubbing worked, so let's record this file into the mapping table
      // for it to serve as source for the later file migration.
      // If a lookup was found, let's try and record it anyway, in case the
      // mapping table was dropped for some reason, but the stubbing stayed.
      $this->mediaMappingHandler->recordMediaFileMapping($url, $file_type, $source_content_id ?: $this->getPluginId());
    }

    return $destination_id;
  }

  /**
   * Sanitizes a string to remove white and non-breaking spaces.
   *
   * @param string $string
   *   The string to be sanitized.
   *
   * @return string
   *   The trimmed string.
   */
  public function sanitizeTextContent(string $string): string {
    $string = preg_replace("/\s+/u", ' ', $string);
    return trim($string);
  }

  /**
   * Remove break (<br>) DOM element children.
   *
   * @param \DOMElement $element
   *   The DOM element parent to remove the break element children.
   * @param \DOMXPath $xpath
   *   The DOM document XPath.
   */
  public function removeBreakElements(\DOMElement &$element, \DOMXPath $xpath): void {
    $br_elements = $xpath->query('.//br', $element);
    // Loop through <br> elements and remove them.
    foreach ($br_elements as $br_element) {
      $br_element->parentNode->removeChild($br_element);
    }
  }

  /**
   * Gets embed code for media entities.
   *
   * @param string $mid
   *   The media id.
   * @param \DOMDocument $document
   *   The document to create a DOM element.
   *
   * @return \DOMElement|null
   *   The Drupal media DOM element.
   */
  public function getEmbedMediaElement(string $mid, \DOMDocument $document): ?\DOMElement {
    $media_entity = $this->entityTypeManager->getStorage('media')->load($mid);
    if (!$media_entity) {
      return NULL;
    }

    $media = $document->createElement('drupal-media');
    $media->setAttribute('data-entity-uuid', $media_entity->uuid());
    $media->setAttribute('data-entity-type', 'media');

    return $media;
  }

  /**
   * Gets the video source from an iframe DOM element.
   *
   * This tries to parse and get embed videos from youtube or videos.
   *
   * @param \DOMElement $element
   *   The iframe DOM element or its parent.
   *
   * @return string|null
   *   The video source URL.
   */
  public function getEmbedVideoSrc(\DOMElement $element): ?string {
    /** @var \DOMElement|null */
    $video_element = $element->tagName === 'iframe' ? $element : $element->getElementsByTagName('iframe')->item(0);
    if (!$video_element) {
      return NULL;
    }

    $uri = $video_element->getAttribute('src');
    if (strpos($uri, 'youtube') !== FALSE || strpos($uri, 'youtu.be') !== FALSE) {
      $video_id_pattern = '/(?:embed\/|v=)([\w-]+)/';
      return 'https://youtu.be/' . $video_id_pattern;
    }
    elseif (strpos($uri, 'vimeo.com') !== FALSE) {
      // All embedded videos are set under the player.vimeo.com domain.
      $pattern = '/^(https:\/\/player\.vimeo\.com\/video\/\d+\?h=[^&]+).*$/';
      preg_match($pattern, $uri, $matches);
      return $matches[1] ?? NULL;
    }
    else {
      return NULL;
    }
  }

}
