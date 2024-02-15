<?php

namespace Drupal\tooltip\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\ckeditor\CKEditorPluginCssInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "tooltip" plugin.
 *
 * @CKEditorPlugin(
 *   id = "tooltip",
 *   label = @Translation("Tooltip"),
 *   module = "tooltip"
 * )
 */
class Tooltip extends CKEditorPluginBase implements CKEditorPluginCssInterface, CKEditorPluginConfigurableInterface {

  /**
   * Get path to plugin folder.
   */
  public function getPluginFolderPath() {
    return $this->getModulePath('tooltip') . '/js/plugins/tooltip';
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->getPluginFolderPath() . '/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $settings = $editor->getSettings()['plugins'][$this->getPluginId()] ?? [];

    $config = [];
    foreach ($settings as $key => $value) {
      $config['tooltip'][$key] = $value;
    }

    if (!$editor->isNew()) {
      // Display button if permission.
      $dialog_url = Url::fromRoute('tooltip.editor_dialog', [
        'editor' => $editor->getFilterFormat()->id(),
      ]);
      if ($dialog_url->access()) {
        $config['tooltip']['modal_url'] = $dialog_url
          ->toString(TRUE)
          ->getGeneratedUrl();
      }
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'core/drupal.ajax',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $image_path = $this->getPluginFolderPath() . '/icons';

    return [
      'tooltip' => [
        'label' => $this->t('Add tooltip'),
        'image' => $image_path . '/tooltip.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCssFiles(Editor $editor) {
    return [
      $this->getModulePath('tooltip') . '/css/plugins/tooltip/ckeditor.tooltip.css',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\editor\Form\EditorImageDialog
   * @see editor_image_upload_settings_form()
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $settings = ($editor->getSettings()['plugins'][$this->getPluginId()] ?? []) + [
      'text' => $this->t('Tooltip text'),
      'placement' => 'top',
      'arrow' => TRUE,
    ];

    $form['text'] = [
      '#type' => 'textfield',
      '#title' => t('Text'),
      '#description' => t('Default text when inserting a new tooltip in editor.'),
      '#default_value' => $settings['text'],
    ];

    $form['placement'] = [
      '#type' => 'select',
      '#title' => t('Placement'),
      '#description' => t('Default position of tooltip content.'),
      '#default_value' => $settings['placement'],
      '#options' => self::getPlacementOptions(),
    ];

    $form['arrow'] = [
      '#type' => 'checkbox',
      '#title' => t('Display arrow'),
      '#default_value' => $settings['arrow'],
    ];

    return $form;
  }

  /**
   * Get list of possible placement values for a Popper instance.
   *
   * @return array
   *   The option list, keyed by placement value.
   */
  public static function getPlacementOptions() {
    return [
      'top-start' => t('Top left'),
      'top' => t('Top'),
      'top-end' => t('Top right'),
      'right-start' => t('Right top'),
      'right' => t('Right'),
      'right-end' => t('Right bottom'),
      'bottom-start' => t('Bottom left'),
      'bottom' => t('Bottom'),
      'bottom-end' => t('Bottom right'),
      'left-start' => t('Left top'),
      'left' => t('Left'),
      'left-end' => t('Left bottom'),
    ];
  }
}
