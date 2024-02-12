<?php

namespace Drupal\layout_builder_iframe_modal\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for Layout Builder Iframe Modal settings.
 */
class LayoutBuilderIframeModalSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_iframe_modal';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['layout_builder_iframe_modal.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('layout_builder_iframe_modal.settings');

    $options = [
      'layout_builder.configure_section' => $this->t('Configure section'),
      'layout_builder.remove_section' => $this->t('Remove section'),
      'layout_builder.remove_block' => $this->t('Remove block'),
      'layout_builder.add_section' => $this->t('Add section'),
      'layout_builder.add_block' => $this->t('Add block'),
      'layout_builder.update_block' => $this->t('Update block'),
      'layout_builder.move_sections_form' => $this->t('Reorder sections'),
      'layout_builder.move_block_form' => $this->t('Move block'),
      'layout_builder.translate_block' => $this->t('Translate block'),
      'layout_builder.translate_inline_block' => $this->t('Translate inline block'),
    ];

    foreach ($options as $key => $label) {
      $options[$key] = $label . " ($key)";
    }

    $defaults = [];
    $defaults = $config->get('layout_builder_iframe_routes');

    $form['layout_builder_iframe_routes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Layout Builder Routes'),
      '#description' => $this->t('Select Layout Builder routes to apply iframe to.'),
      '#options' => $options,
      '#default_value' => $defaults,
    ];

    $custom_routes = $config->get('custom_routes') ?? [];
    $form['custom_routes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom routes'),
      '#description' => $this->t('Add custom routes (one per line) that should also open in an iframe modal.'),
      '#default_value' => implode("\r\n", $custom_routes),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('layout_builder_iframe_modal.settings');
    $options = array_filter(array_values($form_state->getValue('layout_builder_iframe_routes')));
    $config->set('layout_builder_iframe_routes', $options);

    $custom_routes = explode("\r\n", $form_state->getValue('custom_routes'));
    $config->set('custom_routes', array_values(array_filter($custom_routes)));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
