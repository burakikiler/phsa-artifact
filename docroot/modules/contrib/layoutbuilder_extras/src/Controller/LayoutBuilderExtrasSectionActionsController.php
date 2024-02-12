<?php

namespace Drupal\layoutbuilder_extras\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Controller\ChooseSectionController;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\section_library\Controller\ChooseSectionFromLibraryController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to choose a new section.
 *
 * @internal
 *   Controller classes are internal.
 */
class LayoutBuilderExtrasSectionActionsController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected $extensionListModule;

  /**
   * ChooseSectionController constructor.
   *
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LayoutPluginManagerInterface $layout_manager, EntityTypeManagerInterface $entity_type_manager, ExtensionList $extension_list_module) {
    $this->layoutManager = $layout_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->extensionListModule = $extension_list_module;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_type.manager'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Choose a layout plugin to add as a section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return array
   *   The render array.
   */
  public function build(SectionStorageInterface $section_storage, int $delta) {
    $chooseSectionFromLibController = new ChooseSectionFromLibraryController($this->entityTypeManager, $this->extensionListModule);
    $output['from_library'] = [
      '#type' => 'details',
      '#title' => $this->t('From library'),
      $chooseSectionFromLibController->build($section_storage, $delta),
    ];

    $chooseSectionController = new ChooseSectionController($this->layoutManager);
    $output['choose_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Choose section'),
      $chooseSectionController->build($section_storage, $delta),
      '#open' => TRUE,
    ];

    return $output;
  }

}
