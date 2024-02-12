<?php

namespace Drupal\phsa_migration;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * PHSA Content Processor plugin manager.
 */
class PhsaContentProcessorPluginManager extends DefaultPluginManager {

  /**
   * Constructs PhsaContentProcessorPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/PhsaContentProcessor',
      $namespaces,
      $module_handler,
      'Drupal\phsa_migration\PhsaContentProcessorInterface',
      'Drupal\phsa_migration\Annotation\PhsaContentProcessor'
    );
    $this->alterInfo('phsa_content_processor_info');
    $this->setCacheBackend($cache_backend, 'phsa_content_processor_plugins');
  }

}
