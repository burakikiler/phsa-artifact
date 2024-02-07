<?php

namespace Drupal\phsa_migration;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Interface for phsa_content_processor plugins.
 */
interface PhsaContentProcessorInterface {

  /**
   * Loads and parses the content page data into an object.
   *
   * @param string $url
   *   The page content URL.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The entity migration object.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *    with the $row above.
   * @param array $configuration
   *   The configuration information passed into the plugin
   *
   * @return array
   *   An array with the transformed
   */
  public function process(string $url, MigrationInterface $migration, array $configuration): ?array;

}
