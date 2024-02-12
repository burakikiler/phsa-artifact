<?php

namespace Drupal\layoutbuilder_extras\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form.
 */
class LayoutBuilderExtrasSettingsForm extends ConfigFormBase {

  const SETTINGSNAME = 'layoutbuilder_extras.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layoutbuilder_extras';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::SETTINGSNAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config(self::SETTINGSNAME);

    $form['section_actions_position'] = [
      '#type' => 'select',
      '#options' => [
        'left' => $this->t('left'),
        'top' => $this->t('top'),
      ],
      '#title' => $this->t('Position of the section actions'),
      '#default_value' => $config->get('section_actions_position') ?? 'top',
    ];

    $form['enable_redirect_on_save'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable redirect to layout builder on node save'),
      '#description' => $this->t('Redirects to layout builder on node save (Where layout builder is enabled for).'),
      '#default_value' => $config->get('enable_redirect_on_save') ?? FALSE,
    ];

    $form['enable_configure_ajax_save'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable layout builder configure form live changes'),
      '#description' => $this->t('On a LB section form this will enable live changes.'),
      '#default_value' => $config->get('enable_configure_ajax_save') ?? FALSE,
    ];

    $form['remove_empty_divs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove empty DIVS'),
      '#description' => $this->t('On rendering LB there might be empty fields, this will remove the empty wrappers left behind by the TPLs and stuff.'),
      '#default_value' => $config->get('remove_empty_divs') ?? FALSE,
    ];

    $form['enable_drag_handle_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable drag handle icon'),
      '#description' => $this->t('The layout builder sidebar can be dragged, this just adds an icon to improve UX and visibility.'),
      '#default_value' => $config->get('enable_drag_handle_icon') ?? FALSE,
    ];

    $form['enable_admin_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable admin css'),
      '#description' => $this->t('Changes the look & feel of the add section link.'),
      '#default_value' => $config->get('enable_admin_css') ?? FALSE,
    ];

    $form['contextual_links'] = [
      '#type' => 'details',
      '#title' => $this->t('Contextual links'),
    ];
    $form['contextual_links']['contextual_links_only_lb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only allow contextual links on layout builder'),
      '#description' => $this->t('You can also configure for which roles the contextual links should still be shown.'),
      '#default_value' => $config->get('contextual_links_only_lb') ?? FALSE,
    ];
    $roles = \Drupal\user\Entity\Role::loadMultiple();
    $roleOptions = [];
    foreach ($roles as $roleID => $roleObject) {
      /** @var \Drupal\user\Entity\Role $roleObject */
      $roleOptions[$roleID] = $roleObject->label();
    }
    $form['contextual_links']['contextual_links_roles'] = [
      '#type' => 'checkboxes',
      '#options' => $roleOptions,
      '#title' => $this->t('For which roles should the contextual links still be visible?'),
      '#default_value' => $config->get('contextual_links_roles') ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory->getEditable(self::SETTINGSNAME);

    $config->set('section_actions_position', $form_state->getValue('section_actions_position', FALSE));
    $config->set('enable_redirect_on_save', $form_state->getValue('enable_redirect_on_save', FALSE));
    $config->set('enable_configure_ajax_save', $form_state->getValue('enable_configure_ajax_save', FALSE));
    $config->set('remove_empty_divs', $form_state->getValue('remove_empty_divs', FALSE));
    $config->set('enable_drag_handle_icon', $form_state->getValue('enable_drag_handle_icon', FALSE));
    $config->set('enable_admin_css', $form_state->getValue('enable_admin_css', FALSE));
    $config->set('contextual_links_only_lb', $form_state->getValue('contextual_links_only_lb', FALSE));
    $config->set('contextual_links_roles', $form_state->getValue('contextual_links_roles', FALSE));

    $config->save();
  }

}
