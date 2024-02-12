<?php

namespace Drupal\tooltip\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\editor\Entity\Editor;
use Drupal\tooltip\Plugin\CKEditorPlugin\Tooltip;
use Drupal\tooltip\TooltipBlockPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a tooltip dialog for text editors.
 */
class EditorTooltipDialog extends FormBase {

  use AjaxFormHelperTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The existing placed blocks.
   *
   * @var array
   */
  protected $blocks;

  /**
   * EditorTooltipDialog constructor.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    RouteMatchInterface $route_match,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->moduleHandler = $module_handler;
    $this->routeMatch = $route_match;

    // Load placed blocks.
    $tooltip_blocks = [];
    $blocks = $entity_type_manager->getStorage('block')->loadMultiple();
    foreach ($blocks as $id => $block) {
      if ($block->getPlugin() instanceof TooltipBlockPluginInterface) {
        $tooltip_blocks[$block->uuid()] = $block->label();
      }
    }

    // Load content blocks, if possible.
    $content_blocks = [];
    if ($this->moduleHandler->moduleExists('block_content')) {
      $query = $database->select('block_content_field_data', 'bf')
        ->fields('bf', ['info'])
        ->fields('bc', ['uuid']);
      $query->innerJoin('block_content', 'bc', 'bf.id = bc.id');
      $results = $query->execute()->fetchAll();
      foreach ($results as $block) {
        $content_blocks[$block->uuid] = $block->info;
      }
    }

    $this->blocks = [];
    if (!empty($tooltip_blocks)) {
      $this->blocks['Tooltip blocks'] = $tooltip_blocks;
    }
    if (!empty($content_blocks)) {
      $this->blocks['Content blocks'] = $content_blocks;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('current_route_match'),
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'tooltip_editor_dialog_form';
  }

  /**
   * {@inheritDoc}
   */
  private function getDefaultSettings(FormStateInterface $form_state) {
    // Get preloaded settings.
    if ($already_loaded_settings = $form_state->get('default_settings')) {
      return $already_loaded_settings;
    }

    // Get settings from tooltip button placed in the Editor.
    $default_settings = [
      'text' => t('Tootip text'),
      'placement' => 'top',
      'arrow' => true,
    ];

    $editor = $this->routeMatch->getParameter('editor');

    if ($editor instanceof Editor && $this->moduleHandler->moduleExists('ckeditor')) {
      $tooltip_plugin = \Drupal::service('plugin.manager.ckeditor.plugin')
        ->createInstance('tooltip');
      $default_settings = $tooltip_plugin->getConfig($editor)['tooltip'] ?? [];
    }

    return $default_settings;
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get default settings.
    $default_settings = $this->getDefaultSettings($form_state);
    $form_state->set('default_settings', $default_settings);

    // Data from the text editor.
    $tooltip_data = $form_state->get('tooltip_data') ?: [];

    // Must be cached on first load or will be lost on form rebuilt.
    if (isset($form_state->getUserInput()['editor_object'])) {
      $tooltip_data = $form_state->getUserInput()['editor_object'] ?? [];
      $form_state->set('tooltip_data', $tooltip_data);
      $form_state->setCached(TRUE);
    }

    $wrapper_id = Html::getUniqueId($this->getFormId());
    $form['#tree'] = TRUE;
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    // Wrapped text.
    $tooltip_text = $tooltip_data['text'] ?? NULL;
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selected text'),
      '#default_value' => $tooltip_text ?? $default_settings['text'],
      '#disabled' => TRUE,
      '#access' => !empty($tooltip_text),
    ];

    // Allow other modules to remove/add available blocks for Tooltip.
    $this->moduleHandler->alter('tooltip_block_list', $this->blocks);

    // Tooltip blocks.
    $tooltip_block = $tooltip_data['block'] ?? NULL;
    $form['block'] = [
      '#type' => 'select',
      '#title' => $this->t('From block'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->blocks,
      '#default_value' => $tooltip_block,
      '#disabled' => empty($this->blocks),
      '#description' => !empty($this->blocks) ? ''
        : $this->t('No blocks found.') . ' ' . $this->t('Learn how to create a block @here', [
        '@here' => Link::fromTextAndUrl($this->t('here'), Url::fromRoute('help.page', [
          'name' => 'tooltip',
        ], [
          'attributes' => ['target' => '_blank'],
        ]))->toString(),
      ]),
    ];

    // Manual tooltip content.
    $tooltip_content = $tooltip_data['content'] ?? NULL;
    $form['content'] = [
      '#type' => 'textarea',
      '#rows' => 1,
      '#title' => $this->t('From content'),
      '#description' => $this->t('Disabled if a <em>block</em> is selected.'),
      '#default_value' => $tooltip_content,
      '#states' => [
        'visible' => [
          'select[name="block"]' => ['value' => ''],
        ],
        'enabled' => [
          'select[name="block"]' => ['value' => ''],
        ],
      ],
    ];

    // Tooltip styling.
    $form['placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => Tooltip::getPlacementOptions(),
      '#default_value' => $tooltip_data['placement'] ?? $default_settings['placement'],
    ];

    $display_arrow = $default_settings['arrow'];
    if (isset($tooltip_data['arrow'])) {
      $display_arrow = ($tooltip_data['arrow'] == 'true');
    }
    $form['arrow'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display arrow'),
      '#default_value' => $display_arrow,
    ];

    // Actions
    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => empty($tooltip_content) ? $this->t('Insert') : $this->t('Update'),
      '#button_type' => 'primary',
    ];

    $form['actions']['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#button_type' => 'danger',
      '#access' => !empty($tooltip_text),
    ];

    if ($this->isAjax()) {
      foreach (Element::children($form['actions']) as $key) {
        $form['actions'][$key]['#ajax']['callback'] = '::ajaxSubmit';
      }
    }

    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do your things
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    unset($form['op'], $form['actions'], $form['form_build_id'], $form['form_token'], $form['form_id']);

    $parents = $form_state->getTriggeringElement()['#parents'] ?? [];
    $values['trigger'] = end($parents);

    $response = new AjaxResponse();
    $response->addCommand(new EditorDialogSave($values));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
