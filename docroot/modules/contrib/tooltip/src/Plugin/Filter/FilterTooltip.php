<?php

namespace Drupal\tooltip\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to instanciate tooltip elements.
 *
 * @Filter(
 *   id = "tooltip",
 *   title = @Translation("Tooltip"),
 *   description = @Translation("Will load the Tooltip JS library to instanciate <code>data-tooltip</code> elements."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class FilterTooltip extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, 'data-tooltip') !== FALSE) {
      $dom = Html::load($text);
      $result->setProcessedText(Html::serialize($dom));
      $result->addAttachments(['library' => [
        'tooltip/tooltip',
      ]]);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Use Tooltip to display HTML when user hover selected text.');
  }

}
