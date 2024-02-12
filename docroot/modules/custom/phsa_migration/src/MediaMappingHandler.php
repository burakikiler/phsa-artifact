<?php

namespace Drupal\phsa_migration;

use Drupal\Core\Database\Connection;

/**
 * Handle all operations related to mapping and storing external media.
 */
class MediaMappingHandler {

  /**
   * The media mapping table name.
   */
  const MAPPING_TABLE_NAME = 'phsa_migration_media_mapping';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs a MediaMapping object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Records the found media file into the mapping table to be used as source.
   *
   * We need to record all the found files into the mapping table so we can use
   * as source to run the media file migration.
   *
   * @param string $url
   *   The media file URL.
   * @param string $file_type
   *   The file type.
   * @param string|null $source_content_id
   *   The source content where the file was found. If not provided, it
   *   fallbacks to the plugin that invokes this method.
   *
   * @return int
   *   The ID of the new record.
   */
  public function recordMediaFileMapping(string $url, string $file_type, string $source_bundle): int {
    $exists = $this->connection
      ->select(self::MAPPING_TABLE_NAME, 't')
      ->fields('t', ['id'])
      ->condition('url', $url)
      ->execute()
      ->fetchCol();

    if ($exists) {
      return reset($exists);
    }

    $id = $this->connection
      ->insert(self::MAPPING_TABLE_NAME)
      ->fields([
        'url' => $url,
        'file_type' => $file_type,
        'file_name' => $this->getMediaFileName($url),
        'source_bundle' => $source_bundle,
      ])
      ->execute();

    return $id;
  }

  /**
   * Gets the file name from the URL path.
   *
   * @param string $url
   *   The file URL.
   *
   * @return string
   *   The file name.
   */
  public function getMediaFileName(string $url): string {
    $path_parts = explode('/', $url);
    $file_name = end($path_parts);

    return $file_name;
  }

  /**
   * Appends an external hostname into the media file URL.
   *
   * If the URL already has the hostname, it's possible to either leave it as is
   * or replace the hostname with the one provided.
   *
   * @param string $url
   *   The file URL.
   * @param string $hostname
   *   The hostname to append.
   * @param bool $override
   *   Whether to append the hostname regardless if the URL already has one.
   *   Defaults to false.
   *
   * @return string
   *   The full URL with a hostname.
   */
  public function appendHostToUrl(string $url, string $hostname, bool $override = FALSE): string {
    if (!$override && $this->isHostnameSet($url)) {
      return $url;
    }
    $path = parse_url($url)['path'];
    $path = (strpos($path, '/') === 0) ? $path : ('/' . $path);
    $hostname = ltrim($hostname, '/');

    return $hostname . $path;
  }

  /**
   * Checks if a URL has a hostname.
   *
   * @param string $url
   *   The URL.
   *
   * @return bool
   *   Whether the URL has a hostname.
   */
  public function isHostnameSet(string $url): bool {
    $url_parts = parse_url($url);
    return isset($url_parts['hostname']);
  }

}
