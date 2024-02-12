<?php

namespace Drupal\tooltip\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginElementsSubsetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Tooltip plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class Tooltip extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface, CKEditor5PluginElementsSubsetInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The default configuration for this plugin.
   *
   * @var string[]
   */
  const DEFAULT_CONFIGURATION = [
    'tooltip_label' => 'Tooltip',
    'tooltip_hint' => 'Displays above selection.',
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * {@inheritdoc}
   *
   * Form for choosing which heading tags are available.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['tooltip_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tooltip settings'),
    ];

    $form['tooltip_settings']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tooltip label'),
      '#default_value' => $this->configuration['tooltip_label'] ?? $this->defaultConfiguration()['tooltip_label'],
    ];

    $form['tooltip_settings']['hint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tooltip hint'),
      '#default_value' => $this->configuration['tooltip_hint'] ?? $this->defaultConfiguration()['tooltip_hint'],
    ];

    $form['tooltip_settings']['label']['#label_attributes']['class'][] = 'ck';
    $form['tooltip_settings']['hint']['#label_attributes']['class'][] = 'ck';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Match the config schema structure at ckeditor5.plugin.ckeditor5_heading.
    $form_value = $form_state->getValue('tooltip_settings');
    $form_state->setValue('label', $form_value['label']);
    $form_state->setValue('hint', $form_value['hint']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['tooltip_label'] = $form_state->getValue('label');
    $this->configuration['tooltip_hint'] = $form_state->getValue('hint');

  }

  /**
   * {@inheritdoc}
   *
   * Filters the header options to those chosen in editor config.
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    return [
      'tooltip_label' => $this->configuration['tooltip_label'],
      'tooltip_hint' => $this->configuration['tooltip_hint'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getElementsSubset(): array {
    return ['<span>', '<span data-tooltip>'];
  }

}
