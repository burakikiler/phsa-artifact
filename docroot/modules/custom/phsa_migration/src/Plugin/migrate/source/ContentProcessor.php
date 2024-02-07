<?php

namespace Drupal\phsa_migration\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\phsa_migration\PhsaContentProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a PHSA content processor plugin.
 *
 * This plugin allows for the loading and parsing of the content from an
 * external source into an object.
 *
 * There should be a plugin of type PhsaContentProcessor for each content that
 * needs to be processed.
 *
 * The migration target is an optional paramater that allows specifying against
 * which migration the lookup should be run, if needed. It can be just one
 * target or multiple. If multiple, it's recommended they be categorized by type
 * to specify which to use depending on the type.
 *
 * Usage:
 *
 * @code
 * process:
 *   source:
 *     plugin: phsa_content_processor
 *     processor_id: phsa_news
 *     migration_target:
 *       - image:image_news
 *       - remote_video:remote_video_news
 * @endcode
 *
 * @MigrateSource(
 *   id = "phsa_content_processor",
 *   source_module = "phsa_migration"
 * )
 */
class ContentProcessor extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * PHSA Content Processor Manager.
   *
   * @var \Drupal\phsa_migration\PhsaContentProcessorPluginManager
   */
  protected PhsaContentProcessorPluginManager $contentProcessorManager;

  /**
   * Constructs a ContentProcessor plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\phsa_migration\PhsaContentProcessorPluginManager $content_processor_manager
   *   The content processor manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, PhsaContentProcessorPluginManager $content_processor_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->contentProcessorManager = $content_processor_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.phsa_content_processor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    $this->setMigrationTargetValues();
    $records = [];
    // The idea for this source plugin was to crawl the content list page, and
    // go through the list pager to get all content page URLs. The problem,
    // however, is that sharepoint content list is loaded through javascript,
    // meaning that the DOM with the content list is not available if we get
    // the full HTML page.
    // There's another alternative, with web scraping. The problem with this
    // approach is that it would mean to use web crawler libraries that consume
    // lots of resources just to scrap and get a list of URLs, besides the high
    // development effort involved.
    $url_list = [
      'http://www.bcmhsus.ca/about/news-stories/stories/from-clients-to-champions-recognizing-our-peer-support-workers',
      'http://www.bcmhsus.ca/about/news-stories/stories/new-one-stop-shop-centralizes-mental-health-and-substance-use-education',
      'http://www.bcmhsus.ca/about/news-stories/stories/may-kung-2023-phsa-plus-recipient',
      'http://www.bcmhsus.ca/about/news-stories/stories/bcmhsus-working-group-phsa-plus-recipient',
    ];

    $content_processor_plugin_id = $this->configuration['processor_id'] ?? NULL;
    if (!$content_processor_plugin_id || !$this->contentProcessorManager->getDefinition($content_processor_plugin_id)) {
      throw new MigrateException('Processor ID is either empty or does not exist');
    }
    /** @var \Drupal\phsa_migration\PhsaContentProcessorInterface */
    $plugin = $this->contentProcessorManager->createInstance($content_processor_plugin_id);

    foreach ($url_list as $url) {
      $row = [];
      $row['url'] = $url;
      $row_data = $plugin->process($url, $this->migration, $this->configuration);
      $row += $row_data;
      $records[] = $row;
    }

    return new \ArrayIterator($records);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      $this->t('The content page URL.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['url'] = [
      'type' => 'string',
      'length' => 512,
    ];
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return '';
  }

  /**
   * Sets the migration target values as an array.
   *
   * If the migration target is not an array, this will make it an array.
   * If the migration target sets its type and migration ID, the final array
   * will be an associative array of type => migration_target.
   */
  protected function setMigrationTargetValues(): void {
    if (!isset($this->configuration['migration_target'])) {
      $this->configuration['migration_target'] = [];
      return;
    }
    $migration_destinations = [];
    $migration_target = $this->configuration['migration_target'];
    $migration_target = is_array($migration_target) ? $migration_target : [$migration_target];

    foreach ($migration_target as $mt) {
      [$type, $migration_id] = explode(':', $mt);
      // If the migration target was splited by a colon, it means we're adding
      // the type and the migration ID for that type.
      if ($migration_id) {
        $migration_destinations[$type] = $migration_id;
      }
      else {
        $migration_destinations[] = $mt;
      }
    }
    $this->configuration['migration_target'] = $migration_destinations;
  }

  /**
   * {@inheritdoc}
   */
  public function preRollback(MigrateRollbackEvent $event): void {
    parent::preRollback($event);

    if (!$this->idMap instanceof Sql) {
      return;
    }

    // Rollback the paragraphs that were created upon migration execution.
    $connection = $this->idMap->getDatabase();
    $nids = $connection
      ->select($this->idMap->mapTableName(), 't')
      ->fields('t', ['destid1'])
      ->execute()
      ->fetchCol();

    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $paragraphs = $node?->field_content->referencedEntities();
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
      foreach ($paragraphs as $paragraph) {
        $paragraph->delete();
      }
    }
  }

}
