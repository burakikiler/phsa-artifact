<?php

namespace Drupal\layoutbuilder_extras\Menu;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\layoutbuilder_extras\Form\LayoutBuilderExtrasSettingsForm;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Disables contextual links.
 */
class ContextualLinkManager extends \Drupal\Core\Menu\ContextualLinkManager {

  /**
   * Original service object.
   *
   * @var \Drupal\Core\Menu\ContextualLinkManager
   */
  protected $originalService;

  /** @var ConfigFactoryInterface */
  protected $configFactory;

  private $isAdmin = TRUE;
  private $isLBPath = TRUE;

  /**
   * Constructs a new ContextualLinkManager.
   *
   * @param \Drupal\Core\Menu\ContextualLinkManager $original_service
   *   The original contextual links manager service we are decorating.
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   The controller resolver.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(\Drupal\Core\Menu\ContextualLinkManager $original_service, ControllerResolverInterface $controller_resolver, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, LanguageManagerInterface $language_manager, AccessManagerInterface $access_manager, AccountInterface $account, RequestStack $request_stack, ConfigFactoryInterface $configFactory) {
    parent::__construct( $controller_resolver,  $module_handler,  $cache_backend,  $language_manager,  $access_manager,  $account,  $request_stack);
    $this->originalService = $original_service;
    $this->configFactory = $configFactory;

    $roles = $account->getRoles();
    $config = $configFactory->get(LayoutBuilderExtrasSettingsForm::SETTINGSNAME);
    $allowOnlyOnLb = $config->get('contextual_links_only_lb');
    $allowedRoles = $config->get('contextual_links_roles');

    if (!$allowOnlyOnLb) {
      return;
    }

    // Reset here because we are using the code now.
    $this->isAdmin = FALSE;
    $this->isLBPath = FALSE;

    foreach ($allowedRoles as $allowedRole) {
      if  (in_array($allowedRole, $roles)) {
        $this->isAdmin = TRUE;
        break;
      }
    }

    $ids = version_compare(\Drupal::VERSION, '9.3', '>=') ? $this->requestStack->getMainRequest()->request->all()['ids'] ?? NULL : $this->requestStack->getMasterRequest()->request->get('ids');
    if (!empty($ids)) {
      foreach ($ids as $id) {
        if (strpos($id, 'layout_builder_block:section_storage_type=') !== FALSE) {
          $this->isLBPath = TRUE;
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if ($this->isAdmin || $this->isLBPath) {
      return parent::getDiscovery();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualLinkPluginsByGroup($group_name) {
    if ($this->isAdmin || $this->isLBPath) {
      return parent::getContextualLinkPluginsByGroup($group_name);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualLinksArrayByGroup($group_name, array $route_parameters, array $metadata = []) {
    if ($this->isAdmin || $this->isLBPath) {
      return parent::getContextualLinksArrayByGroup($group_name, $route_parameters, $metadata);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function alterInfo($alter_hook) {
    if ($this->isAdmin || $this->isLBPath) {
      parent::alterInfo($alter_hook);
    } else {
      $this->alterHook = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    if ($this->isAdmin || $this->isLBPath) {
      return parent::getDefinitions();
    }
    return [];
  }

}
