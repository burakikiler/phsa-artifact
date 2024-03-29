<?php

/**
 * @file
 * Search Solr updates once other modules have made their own updates.
 */

use Drupal\acquia_search\Helper\Runtime;
use Drupal\Core\PhpStorage\PhpStorageFactory;

/**
 * Clear cache to rebuild routes.
 */
function acquia_search_post_update_clear_routes() {
  \Drupal::service("router.builder")->rebuild();
  PhpStorageFactory::get("twig")->deleteAll();
}

/**
 * Upgrade from Acquia Search v2 to v3.
 */
function acquia_search_post_update_move_search_modules() {
  $config_factory = \Drupal::configFactory();
  \Drupal::service('config.installer')->installDefaultConfig('module', 'acquia_search');

  // Remove exposed block if its still around.
  if ($config = $config_factory->getEditable('block.block.exposedformacquia_searchpage')) {
    $config->delete();
  }

  // Uninstall Search API Solr Multilingual if its still around.
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
  $module_installer = \Drupal::service('module_installer');
  if (\Drupal::moduleHandler()->moduleExists('search_api_solr_multilingual')) {
    $module_installer->uninstall(['search_api_solr_multilingual']);
  }

  // Import settings from the connector if it is installed and configured.
  $subscription = \Drupal::state()->get('acquia_subscription_data');
  if (isset($subscription)) {
    \Drupal::configFactory()->getEditable('acquia_search.settings')
      ->set('api_host', \Drupal::config('acquia_search_solr.settings')->get('api_host') ?? 'https://api.sr-prod02.acquia.com')
      ->save();

    \Drupal::state()->set('acquia_search.api_key', \Drupal::state()->get('acquia_connector.key'));
    \Drupal::state()->set('acquia_search.identifier', \Drupal::state()
      ->get('acquia_connector.identifier'));
    \Drupal::state()->set('acquia_search.uuid', $subscription['uuid']);
  }

  if ($search_config = $config_factory->getEditable('acquia_search.settings')) {
    if ($override = $search_config->get('default_search_core')) {
      $search_config->set('override_search_core', $override);
      $search_config->clear('default_search_core');
      $search_config->save();
    }
    if ($search_config->get('default_search_core')) {
      \Drupal::messenger()->addWarning(t(
          "'acquia_connector.settings.default_search_core' is being overridden by settings.php. Update the key to acquia_connector.settings.override_search_core to continue overriding the core (usually for local development)"));
    }
  }
}

/**
 * Service definition updates, empty post_update for container rebuild.
 */
function acquia_search_post_update_local_overrides_update() {
  // Empty post-update hook.
}

/**
 * Update Search API servers for Acquia Search to use new backend plugin.
 */
function acquia_search_post_update_acquia_search_solr_backend(&$sandbox) {
  /** @var \Drupal\search_api\ServerInterface[] $servers */
  $servers = \Drupal::entityTypeManager()->getStorage('search_api_server')->loadMultiple();
  foreach ($servers as $server) {
    if (!Runtime::isAcquiaServer($server)) {
      continue;
    }
    $server->set('backend', 'acquia_search_solr');
    $server->save();
  }
}
