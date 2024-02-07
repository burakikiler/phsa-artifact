<?php

/**
 * @file
 *
 * Local development overrides.
 */

if (getenv('IS_DDEV_PROJECT') == 'true') {
  // Use the default acquia server, just with the local config.
  $config['search_api.server.acquia_search_server'] = [
    'backend' => 'search_api_solr',
    'backend_config' => [
      'connector' => 'standard',
      'connector_config' => [
        'scheme' => 'http',
        'host' => 'solr',
        'path' => '/',
        'core' => 'dev',
        'port' => '8983',
      ],
    ],
  ];
}
