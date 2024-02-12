<?php
/**
 * @file
 * Extra layout builder settings.
 */
namespace Drupal\layoutbuilder_extras;

use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;

class LayoutBuilderExtrasLayout extends LayoutDefault {

  use LayoutRebuildTrait;
  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use LayoutBuilderHighlightTrait;
  use StringTranslationTrait;

  /* @var \Drupal\Core\Layout\LayoutPluginManager $layoutManager */
  private $layoutManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->layoutManager = \Drupal::service('plugin.manager.core.layout');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['layout_details'] = [
      '#type' => 'details',
      '#collapsed' => TRUE,
      '#title' => $this->t('Change layout'),
      '#description' => $this->t('Warning this a permanent change.'),
    ];

    $form['layout_details']['layouts'] = $this->getLayouts($this->pluginId);

    return $form;
  }

  /**
   * Returns a list of layouts.
   *
   * @param $currentLayout
   *
   * @return array
   */
  private function getLayouts($currentLayout) {
    $section_storage = \Drupal::routeMatch()->getParameters()->get('section_storage');
    $delta = \Drupal::routeMatch()->getParameters()->get('delta') ?? 0;

    $items = [];
    $definitions = $this->layoutManager->getFilteredDefinitions('layout_builder', $this->getPopulatedContexts($section_storage), ['section_storage' => $section_storage]);
    unset($definitions[$currentLayout]);

    \Drupal::moduleHandler()->alter('layoutbuilder_extras_allowed_layouts', $definitions);

    foreach ($definitions as $plugin_id => $definition) {
      $item = [
        '#type' => 'link',
        '#title' => [
          'icon' => $definition->getIcon(60, 80, 1, 3),
          'label' => [
            '#type' => 'container',
            '#children' => $definition->getLabel(),
          ],
        ],
        '#url' => Url::fromRoute(
          'layoutbuilder_extras.alter_section',
          [
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'delta' => $delta,
            'plugin_id' => $plugin_id,
          ]
        ),
      ];
      if ($this->isAjax()) {
        $item['#attributes']['class'][] = 'use-ajax';
        $item['#attributes']['data-dialog-type'][] = 'dialog';
        $item['#attributes']['data-dialog-renderer'][] = 'off_canvas';
      }
      $items[$plugin_id] = $item;
    }
    return [
      '#theme' => 'item_list__layouts',
      '#items' => $items,
      '#attributes' => [
        'class' => [
          'layout-selection',
        ],
        'data-layout-builder-target-highlight-id' => $this->sectionAddHighlightId($delta),
      ],
    ];
  }

}
