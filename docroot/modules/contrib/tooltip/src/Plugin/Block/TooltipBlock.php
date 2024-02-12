<?php

namespace Drupal\tooltip\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\tooltip\TooltipBlockBase;

/**
 * Example of a custom Tooltip block.
 *
 * @Block(
 *   id = "tooltip_block",
 *   admin_label = @Translation("Tooltip block"),
 *   category = @Translation("Tooltip")
 * )
 */
class TooltipBlock extends TooltipBlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['message'] ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['message'] = $form_state->getValue('message');
  }

  /**
   * {@inheritdoc}
   */
  public function tooltip(array &$build) {
    $message = $this->configuration['message'];

    $build['example'] = [
      '#markup' => Markup::create($message),
      '#access' => !empty($message),
    ];
  }

}
