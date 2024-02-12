<?php

namespace Drupal\tooltip_example\Plugin\Block;

use Drupal\tooltip\TooltipBlockBase;
use Drupal\tooltip_example\Form\TooltipExampleAjaxForm;

/**
 * Example of a custom Tooltip block.
 *
 * @Block(
 *   id = "tooltip_form_example_block",
 *   admin_label = @Translation("Tooltip block with Newsletter form inside (example)"),
 *   category = @Translation("Tooltip")
 * )
 */
class TooltipFormExampleBlock extends TooltipBlockBase {

  /**
   * {@inheritdoc}
   */
  public function tooltip(array &$build) {
    // Forms must be fully rendered otherwise block will return '#lazy_builder'.
    // With lazy builder, our Tooltip JS library will not work correctly because
    // it requires elements to be placed AND rendered on page already.
    $form = \Drupal::formBuilder()->getForm(TooltipExampleAjaxForm::class);
    $output = \Drupal::service('renderer')->renderPlain($form);
    $build['form'] = ['#markup' => $output];
  }

}
