<?php

namespace Drupal\tooltip_example\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an example Ajax form.
 */
class TooltipExampleAjaxForm extends FormBase {

  const TRIGGER = 'subscribe';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tooltip_example_ajax_form';
  }

  /**
   * Builds the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = $this->getFormId() . '--wrapper';
    $form['#prefix'] = '<div id="' . $wrapper_id . '">';
    $form['#suffix'] = '</div>';

    $trigger = $form_state->getTriggeringElement()['#op'] ?? NULL;

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Subscribe to our newsletter'),
      '#required' => TRUE,
      '#default_value' => \Drupal::currentUser()->getEmail(),
      '#attributes' => ['size' => 30],
      '#access' => $trigger !== self::TRIGGER,
    ];

    $subscribed = $form_state->get('subscribed');

    $form['message'] = [
      '#type' => 'item',
      '#markup' => $this->t('You will love our <strike>spam</strike>... emails!'),
      '#attributes' => ['class' => ['messages', 'messages--success']],
      '#access' => $subscribed === true,
    ];

    $form['error'] = [
      '#type' => 'item',
      '#markup' => $this->t('You will love our <strike>spam</strike>... emails!'),
      '#attributes' => ['class' => ['messages', 'messages--error']],
      '#access' => $subscribed === false,
    ];

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#op' => self::TRIGGER,
      '#ajax' => [
        'callback' => '::ajaxRefresh',
        'wrapper' => $wrapper_id,
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.ajax';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Subscribe the user.
    $form_state->set('subscribed', TRUE); // FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    return $form;
  }

}
