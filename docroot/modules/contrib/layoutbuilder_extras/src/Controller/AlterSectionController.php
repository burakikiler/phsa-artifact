<?php

namespace Drupal\layoutbuilder_extras\Controller;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to choose a new section.
 *
 * @internal
 *   Controller classes are internal.
 */
class AlterSectionController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;
  use AjaxHelperTrait;

  /**
   * The section storage.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $sectionStorage;

  /** @var integer Delta */
  protected $delta;

  /** @var string plugin ID of the layout */
  protected $plugin_id;

  /**
   * @var FormBuilderInterface
   */
  protected $form_builder;

  /**
   * ChooseSectionController constructor.
   *
   * @param FormBuilderInterface $form_builder
   *   The layout manager.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->form_builder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }


  /**
   * Alter a layout plugin's layout
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $delta
   *   The delta of the section to splice.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function build(SectionStorageInterface $section_storage, int $delta, $plugin_id) {
    $this->sectionStorage = $section_storage;
    $this->delta = $delta;
    $this->plugin_id = $plugin_id;

    $section = $section_storage->getSection($delta);
    $this->swapLayout($section->getLayoutId(), $plugin_id, $section, $delta);

    $configureSectionForm = $this->form_builder->getForm('Drupal\layoutbuilder_extras\Form\LayoutBuilderExtrasConfigureSectionForm', $section_storage, $delta, NULL);
    $this->alterAjaxSubmitURL($configureSectionForm);

    $response = $this->rebuildLayout($section_storage);
    $response->addCommand(new ReplaceCommand('#layout-builder-configure-section', $configureSectionForm));
    return $response;
  }

  private function swapLayout($fromLayout, $toLayout, $currentSection, $delta) {
    if ($fromLayout !== $currentSection) {
      /* @var \Drupal\layout_builder\Section $section */

      $newSection = new Section(
        $toLayout,
        $currentSection->getLayoutSettings(),
        $currentSection->getComponents(),
        $this->buildThirdPartySettings($currentSection),
      );

      $this->reorderComponents($newSection);

      $this->sectionStorage->removeSection($delta);
      $this->sectionStorage->insertSection($delta, $newSection);
    }

    return $this->rebuildLayout($this->sectionStorage);
  }

  /**
   * Reorder the components from switching between layouts.
   *
   * @param \Drupal\layout_builder\Section $section
   */
  private function reorderComponents($section) {
    $newLayoutRegions = array_keys($section->getLayout()->getPluginDefinition()->getRegions());
    $amountOfRegions = count($newLayoutRegions);

    $counter = 0;
    foreach ($section->getComponents() as &$component) {
      $component->set('region', $newLayoutRegions[$counter]);

      $counter++;
      // Reset.
      if ($counter >= $amountOfRegions) {
        $counter = 0;
      }
    }
  }


  /**
   * There was no getter to get all the settings, so made my own.
   *
   * @param $section
   *
   * @return array
   */
  private function buildThirdPartySettings($section) {
    $settings = [];
    $providers = $section->getThirdPartyProviders();
    foreach ($providers as $provider) {
      $settings[$provider] = $section->getThirdPartySettings($provider);
    }

    return $settings;
  }

  /**
   * Alter the AJAX URLS so the AJAX request work after swapping layout.
   *
   * @param $configureForm
   */
  private function alterAjaxSubmitURL(&$configureForm) {
    $configureForm['#action'] = Url::fromRoute('layout_builder.configure_section', [
      'section_storage_type' => $this->sectionStorage->getStorageType(),
      'section_storage' => $this->sectionStorage->getStorageId(),
      'delta' => $this->delta,
      'plugin_id' => $this->plugin_id,
    ])->toString();
  }

}
