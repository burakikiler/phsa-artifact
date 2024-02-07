<?php

namespace Drupal\phsa_migration\Plugin\PhsaContentProcessor;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\phsa_migration\PhsaContentProcessorPluginBase;

/**
 * Plugin implementation of the phsa_content_processor.
 *
 * @PhsaContentProcessor(
 *   id = "news",
 *   label = @Translation("News Content Processor"),
 *   description = @Translation("Processes the contents from a news external page.")
 * )
 */
class NewsContent extends PhsaContentProcessorPluginBase {

  /**
   * The default paragraph type to be used for rich HTML content.
   */
  const TEXT_PARAGRAPH_TYPE = 'rich_content';

  /**
   * The HTML DOM document to process.
   *
   * @var \DOMDocument
   */
  protected \DOMDocument $document;

  /**
   * The document XPath.
   *
   * @var \DOMXPath
   */
  protected \DOMXPath $documentXPath;

  /**
   * {@inheritdoc}
   */
  public function process(string $url, MigrationInterface $migration, array $configuration): ?array {
    $this->configuration['migration_target'] = $configuration['migration_target'];
    libxml_use_internal_errors(TRUE);

    $html_page = file_get_contents($url);
    $this->document = new \DOMDocument();
    $this->document->loadHTML($html_page);
    $this->documentXPath = new \DOMXPath($this->document);

    /** @var \DOMElement */
    $article = $this->document->getElementsByTagName('article')->item(0);

    if ($header = $article->getElementsByTagName('header')->item(0)) {
      $this->processHeaderContent($header);
    }

    if ($body = $this->findBodyElement($this->documentXPath, $article)) {
      $this->processBodyContent($body);
    }

    if (libxml_get_errors()) {
      libxml_clear_errors();
    }

    return $this->contentProperties;
  }

  /**
   * Processes the content within the article header.
   *
   * @param \DOMElement $header
   *   The header element.
   */
  protected function processHeaderContent(\DOMElement $header): void {
    foreach ($header->childNodes as $child) {
      /** @var \DOMElement $child */
      switch ($child->tagName ?? '') {
        case 'h1':
          $this->contentProperties['title'] = trim($child->textContent);
          break;

        case 'time':
          $date_value = trim($child->textContent);
          $date = \DateTime::createFromFormat('d/m/Y', $date_value);
          $this->contentProperties['date'] = [
            'string' => $date->format('Y-m-d'),
            'timestamp' => $date->getTimestamp(),
          ];
          break;

        case 'div':
          // In the header, if it's a div, it can either be the teaser image, or
          // the summary.
          if ($child->getAttribute('class') === 'lead' && ($value = $this->sanitizeTextContent($child->nodeValue))) {
            $this->contentProperties['summary'] = $value;
          }
          elseif ($child->getAttribute('class') === 'article-image-block') {
            $url = $this->getImageSrc($child);
            $media_type = 'image';
            $mt = $this->configuration['migration_target'][$media_type] ?? FALSE;
            $this->contentProperties['teaserImage'] = [
              'url' => $url,
              // We could just return the image URL and in the migration YML use
              // use the migration lookup plugin with stubs, but we're skipping
              // that step and doing it right here.
              'media_id' =>
                $mt ? $this->processContentMediaFile($url, $media_type, $mt) : NULL,
            ];
          }
          break;
      }
    }
  }

  /**
   * Processes the content within the article rich content wrapper.
   *
   * @param \DOMElement $body
   *   The body (i.e, rich content wrapper) element.
   */
  protected function processBodyContent(\DOMElement $body): void {
    $document = new \DOMDocument('1.0', 'UTF-8');
    $container = $document->createElement('container');
    $container->setAttribute('data-type', self::TEXT_PARAGRAPH_TYPE);
    $document->appendChild($container);

    // We'll have to do this for each DOM element we want to add into the body,
    // so let's create this inline function to avoid code repetition.
    $append_element = function (\DOMElement $child) use (&$document, &$container): void {
      if ($child->getAttribute('data-type')) {
        // If the child has a data type, it means this child is a paragraph.
        // If it is a paragraph, we need to convert the current container (which
        // should be a container that's holding text elements) into a paragraph.
        if ($container) {
          $paragraph = $this->createParagraphEntity($container, $document, self::TEXT_PARAGRAPH_TYPE);
          $container = $this->createDomContainerFromParagraph($paragraph, self::TEXT_PARAGRAPH_TYPE, $container);
          // Now, make the container empty, so that if there are other text
          // elements that need to be processed, a new DOM container is created
          // to hold those new elements. We can't keep the same container (after
          // we found a special DOM element) because we need to split these into
          // different paragraphs.
          $container = NULL;
        }
        // Lastly, add the new node, which should be a special element, into the
        // document.
        $node = $document->importNode($child, TRUE);
        $document->appendChild($node);
      }
      else {
        // It means the child DOM element is a normal one that can be saved into
        // a "rich_content" paragraph.
        if (!$container) {
          // If the container is empty, it means a special paragraph was created
          // before, so we need to create it again to hold the next text element
          // nodes.
          $container = $document->createElement('container');
          $container->setAttribute('data-type', self::TEXT_PARAGRAPH_TYPE);
          $document->appendChild($container);
        }
        $child->removeAttribute('class');
        $node = $document->importNode($child, TRUE);
        $container->appendChild($node);
      }
    };

    /** @var \DOMElement $child */
    foreach ($body->childNodes as $child) {
      switch ($child->tagName ?? '') {
        case 'h3':
        case 'h4':
          if ($this->sanitizeTextContent($child->nodeValue)) {
            $this->removeBreakElements($child, $this->documentXPath);
            $append_element($child);
          }
          break;

        case 'p':
          $children = $this->processParagraphElement($child);
          foreach ($children as $paragraph_child) {
            $append_element($paragraph_child);
          }
          break;

        case 'ul':
          $append_element($child);
          break;

        case 'blockquote':
          if ($this->processBlockQuoteElement($child, $body)) {
            $append_element($child);
          }
          break;

        case 'div':
          // Embed videos are wrapped within a div. Let's check first whether
          // the child is an embed video.
          if ($embed_video = $this->processEmbedVideoElement($child)) {
            $append_element($embed_video);
          }
          elseif ($child->getAttribute('id') === 'accordion') {
            // @todo Process the accordion accordingly.
            // Accordion is a special element that should be contained within a
            // paragraph different to "rich_content", so create the paragraph.
            $paragraph = $this->createParagraphEntity($child, $document, 'accordion');
            $paragraph_container = $this->createDomContainerFromParagraph($paragraph, 'accordion', NULL, $document);
            $append_element($paragraph_container);
          }
          else {
            $this->processDivElement($child, $body);
          }
          break;
      }
    }

    // Get the body element, and then remove the <body> wrapper tag.
    $this->contentProperties['body_elements'] = $this->processDocumentContainersParagraphs($document);
  }

  /**
   * Process the paragraph element.
   *
   * @param \DOMElement $paragraph
   *   The paragraph DOM element.
   *
   * @return \DOMElement[]
   *   The list of dom elements extracted from the paragraph.
   */
  protected function processParagraphElement(\DOMElement &$paragraph): array {
    // Sometimes, paragraphs have special children, such as images. In those
    // cases, we need to parse the image and convert it into an embed image
    // media.
    $elements = [];
    $image_children = $paragraph->getElementsByTagName('img');
    if ($has_images = $image_children->length > 0) {
      /** @var \DOMElement $image_child */
      foreach ($image_children as $image_child) {
        $elements[] = $this->processEmbedImageElement($image_child);
        $paragraph->removeChild($image_child);
      }
    }

    // Only process paragraph wtih content; skip empty ones.
    if ($has_images && !$this->sanitizeTextContent($paragraph->nodeValue)) {
      return $elements;
    }

    // Remove the <br> elements inside a paragraph. If found, they're always at
    // the end of the element.
    $this->removeBreakElements($paragraph, $this->documentXPath);
    $elements[] = $paragraph;
    return $elements;
  }

  /**
   * Process the blockquote DOM element.
   *
   * @param \DOMElement $block_quote
   *   The blockquote DOM element.
   * @param \DOMElement $body
   *   The original body field being processed.
   *
   * @return bool
   *   Due to blockquotes being wrapped within other blockquote elements, we
   *   need to check first whether the blockquote is a wrapper or the actual
   *   element we want to process. If it's a wrapper, return false to not append
   *   the element, otherwise return true.
   */
  protected function processBlockQuoteElement(\DOMElement &$block_quote, \DOMElement &$body): bool {
    // The blockquotes in the content is wrapped within another blockquote.
    // If there's another blockquote inside the blockquote, that's the target
    // blockquote element we need to manipulate.
    if ($actual_blockquote = $block_quote->getElementsByTagName('blockquote')->item(0)) {
      // Let's add the actual blockquote into the DOM so it can be processed.
      if ($next_sibling = $block_quote->nextSibling) {
        $body->insertBefore($actual_blockquote, $next_sibling);
      }
      else {
        $body->appendChild($actual_blockquote);
      }
      return FALSE;
    }

    foreach ($block_quote->childNodes as $child) {
      if ($child instanceof \DOMElement) {
        $child->removeAttribute('class');
        $child->removeAttribute('style');
      }
    }

    return TRUE;
  }

  /**
   * Processes an image DOM element that needs to be embedded.
   *
   * @param \DOMElement $image
   *   The image DOM element.
   *
   * @return \DOMElement|null
   *   The Drupal embed DOM element.
   */
  protected function processEmbedImageElement(\DOMElement $image): ?\DOMElement {
    $media_type = 'image';
    $mt = $this->configuration['migration_target'][$media_type] ?? FAlSE;
    if (!$mt) {
      return NULL;
    }

    $url = $this->getImageSrc($image);
    $mid = $this->processContentMediaFile($url, $media_type, $mt);
    $embed_media_element = $this->getEmbedMediaElement($mid, $this->document);
    if ($image->getAttribute('class') === 'phsa-rtePosition-1') {
      $align = 'left';
    }
    elseif ($image->getAttribute('class') === 'phsa-rtePosition-2') {
      $align = 'right';
    }
    else {
      $align = 'center';
    }
    $embed_media_element->setAttribute('data-align', $align);

    return $embed_media_element;
  }

  /**
   * Processes a embed video DOM element.
   *
   * @param \DOMElement $element
   *   The wrapper DOM element containing the embed video.
   *
   * @return \DOMElement|null
   *   The Drupal media embed or null if not embed video found.
   */
  protected function processEmbedVideoElement(\DOMElement $element): ?\DOMElement {
    $media_type = 'remote_video';
    $mt = $this->configuration['migration_target'][$media_type] ?? FALSE;
    $uri = $this->getEmbedVideoSrc($element);

    if (!$uri || !$mt) {
      return NULL;
    }

    $mid = $this->processContentMediaFile($uri, $media_type, $mt);
    $embed_media_element = $this->getEmbedMediaElement($mid, $this->document);
    $embed_media_element->setAttribute('data-align', 'center');

    return $embed_media_element;
  }

  /**
   * Processes the div DOM element.
   *
   * Usually, all div element are wrappers with other child nodes. This function
   * takes those children and move them into the main parent to be processed
   * as a normal DOM element.
   *
   * @param \DOMElement $div
   *   The div DOM element.
   * @param \DOMElement $body
   *   The body DOM element containing all the DOM elements being processed.
   */
  protected function processDivElement(\DOMElement $div, \DOMElement &$body): void {
    // Because div elements are only wrappers in the DOM, we'll move its
    // children a parent up so they can be processed normally.
    $next_sibling = $div->nextSibling;
    while ($div->childNodes->length > 0) {
      $child = $div->childNodes->item(0);
      if ($next_sibling) {
        $body->insertBefore($child, $next_sibling);
      }
      else {
        $body->appendChild($child);
      }
    }
  }

  /**
   * Creates a DOM container with Drupal paragraph values.
   *
   * It adds the type, target_id, and revision_id values into the container
   * through data attributes.
   *
   * @param array $paragraph
   *   The paragraph.
   * @param string $type
   *   The type of paragraph.
   * @param \DOMElement|null $node
   *   The DOM node you want to add the attributes to. Leave NULL if you want to
   *   create it.
   * @param \DOMDocument|null $document
   *   The DOM document being used to build the new DOM elements. Leave NULL if
   *   you're passing the DOM node, otherwise is required for creating the
   *   container node.
   *
   * @return \DOMElement|null
   *   The DOM Element with the paragraph attributes.
   */
  public function createDomContainerFromParagraph(array $paragraph, string $type, \DOMElement $node = NULL, \DOMDocument $document = NULL): \DOMElement|null {
    if (!$node && !$document) {
      return NULL;
    }

    if (!$node && $document) {
      $node = $document->createElement('container');
    }

    $node->setAttribute('data-type', $type);
    $node->setAttribute('data-target-id', $paragraph['target_id']);
    $node->setAttribute('data-revision-id', $paragraph['revision_id']);
    return $node;
  }

  /**
   * Creates a Drupal paragraph entity from the DOM node.
   *
   * @param \DOMElement $node
   *   The DOM node to extract values from to create the new paragraph entity.
   * @param \DOMDocument $document
   *   The DOM document.
   * @param string $type
   *   The type of paragraph entity to be created.
   *
   * @return array
   *   An associative array with the target_id and revision_id values of the
   *   newly created paragraph entity.
   */
  public function createParagraphEntity(\DOMElement $node, \DOMDocument $document, string $type): array {
    $field_values = [];
    switch ($type) {
      case 'rich_content':
        // The basis of this migration is that all HTML and text data are being
        // contained within a container.
        $dom_node_value = $document->saveHTML($node);
        $dom_node_value = $this->sanitizeTextContent($dom_node_value);
        $dom_node_value = preg_replace('~<container[^>]*>(.*?)</container>~is', '$1', $dom_node_value);
        $field_values = [
          'field_body' => [
            'value' => $dom_node_value,
            'format' => 'legacy_html',
          ],
        ];
        break;

      case 'accordion':
        // @todo Add the proper code to extract the values from the accordion,
        // and then create an accordion paragraph. Remove this code.
        $type = self::TEXT_PARAGRAPH_TYPE;
        $node = $document->importNode($node, TRUE);
        $dom_node_value = $document->saveHTML($node);
        $dom_node_value = $this->sanitizeTextContent($dom_node_value);
        $dom_node_value = preg_replace('~<container[^>]*>(.*?)</container>~is', '$1', $dom_node_value);
        $field_values = [
          'field_body' => [
            'value' => $dom_node_value,
            'format' => 'legacy_html',
          ],
        ];
        break;
    }

    if (!$field_values) {
      return [];
    }

    $field_values['type'] = $type;
    $paragraph = Paragraph::create($field_values);
    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'revision_id' => $paragraph->getRevisionId(),
    ];
  }

  /**
   * Process the container paragraphs.
   *
   * All data extracted from the DOM should be contained in at least one
   * container. If the DOM should have splitted into multiple paragraphs, those
   * paragraphs are represented by containers. Each container should have
   * attributes referencing the paragraph entity.
   *
   * @param \DOMDocument $document
   *   The DOM document where all the DOM containers are held.
   *
   * @return array
   *   An array with each paragraph reference.
   */
  public function processDocumentContainersParagraphs(\DOMDocument $document): array {
    $paragraphs = [];
    $containers = $document->getElementsByTagName('container');
    /** @var \DOMElement $container */
    foreach ($containers as $container) {
      if ($container->getAttribute('data-type') === self::TEXT_PARAGRAPH_TYPE && !$container->getAttribute('data-target-id')) {
        // At the beginning of the body processing, we assume the first element
        // will be an element that can be stored in a "rich_content" paragraph.
        // During DOM crawling, the body will be split into multiple containers
        // if we stumble upon into a special DOM element (i.e., those that need
        // to be converted into other paragraph type different to "rich_content"
        // for example, "accordion"). If that doesn't happen, then all the data
        // was contained in the initial DOM container, and was never converted
        // into a paragraph. This is handled here.
        $paragraph = $this->createParagraphEntity($container, $document, self::TEXT_PARAGRAPH_TYPE);
        $container->setAttribute('data-target-id', $paragraph['target_id']);
        $container->setAttribute('data-revision-id', $paragraph['revision_id']);
      }

      $paragraphs[] = [
        'target_id' => $container->getAttribute('data-target-id'),
        'revision_id' => $container->getAttribute('data-revision-id'),
      ];
    }

    return $paragraphs;
  }
}
