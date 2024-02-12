<?php

namespace Drupal\phsa_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\phsa_migration\MediaMappingHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The external media mapping source plugin.
 *
 * This plugin loads and queries the media mapping table that holds all the
 * found files.
 *
 * For this plugin to actually return the list of files, the migrations related
 * to finding the files from content must be run first.
 *
 * Usage:
 *
 * @code
 * process:
 *   source:
 *     plugin: external_media_mapping
 *     file_type: image
 *     source_bundle: news
 *     base_url: http://www.bcmhsus.ca
 * @endcode
 *
 * @MigrateSource(
 *   id = "external_media_mapping",
 *   source_module = "phsa_migration"
 * )
 */
class ExternalMedia extends SqlBase {

  /**
   * External media mapping handler.
   *
   * @var \Drupal\phsa_migration\MediaMappingHandler
   */
  protected MediaMappingHandler $mediaMappingHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $plugin->mediaMappingHandler = $container->get('phsa_migration.media_mapping_handler');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select(MediaMappingHandler::MAPPING_TABLE_NAME, 't')
      ->fields('t', ['id', 'url', 'file_type', 'file_name']);

    if ($file_type = $this->configuration['file_type'] ?? FALSE) {
      $query->condition('t.file_type', $file_type);
    }
    if ($source_bundle = $this->configuration['source_bundle'] ?? FALSE) {
      $query->condition('t.source_bundle', $source_bundle);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'id' => $this->t('The media file ID.'),
      'url' => $this->t('The media file url.'),
      'file_type' => $this->t('The media file type.'),
      'file_name' => $this->t('The media file name'),
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
  public function prepareRow(Row $row) {
    $url = $row->getSourceProperty('url');
    if ($base_url = $this->configuration['base_url'] ?? FALSE) {
      $url = $this->mediaMappingHandler->appendHostToUrl($url, $base_url);
    }

    $row->setSourceProperty('full_url', $url);
    return parent::prepareRow($row);
  }

}
