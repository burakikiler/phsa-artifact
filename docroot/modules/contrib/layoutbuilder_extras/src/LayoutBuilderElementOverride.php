<?php

namespace Drupal\layoutbuilder_extras;

use Drupal\Core\Link;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layoutbuilder_extras\Form\LayoutBuilderExtrasSettingsForm;
use Drupal\section_library\SectionLibraryRender;

/**
 * Overrides render element LayoutBuilder
 *
 * Hides the text, so we can have ICON Only buttons.
 */
class LayoutBuilderElementOverride implements TrustedCallbackInterface {

  public static function trustedCallbacks() {
    return [
      'preRenderOverride',
    ];
  }

  /**
   * Pre-render callback: Renders the Layout Builder UI.
   */
  public static function preRenderOverride($element) {
    $lbeConfig = \Drupal::config(LayoutBuilderExtrasSettingsForm::SETTINGSNAME);
    if (!$lbeConfig->get('enable_admin_css')) {
      return $element;
    }

    $isSectionLibraryEnabled = \Drupal::moduleHandler()->moduleExists('section_library');

    // Attach admin css.
    if ($isSectionLibraryEnabled) {
      $element['#attached']['library'][] = 'layoutbuilder_extras/admin_section_library';
    } else {
      $element['#attached']['library'][] = 'layoutbuilder_extras/admin';
    }

    $positionOfSectionActions = $lbeConfig->get('section_actions_position', 'left');
    if ($positionOfSectionActions === 'left') {
      $element['#attached']['library'][] = 'layoutbuilder_extras/admin_section_actions_left';
    } else {
      $element['#attached']['library'][] = 'layoutbuilder_extras/admin_section_actions_top';
    }

    foreach ($element['layout_builder'] as $key => &$lbElement) {
      if (!is_array($lbElement)) {
        continue;
      }

      // Add section.
      if (isset($lbElement['link']) && !$isSectionLibraryEnabled) {
        /** @var \Drupal\Core\Url $url */
        $url = $lbElement['link']['#url'];
        if ($url->getRouteName() !== 'layout_builder.choose_section') {
          continue;
        }

        $lbElement['link']['#title'] = new TranslatableMarkup('<span class="visually-hidden">'
          . $lbElement['link']['#title'] . '</span>');
      }
      elseif (isset($lbElement['link']) && isset($lbElement['choose_template_from_library']) && $isSectionLibraryEnabled) {
        /** @var Link $sectionActionsLink */
        $sectionActionsLink
          = Link::createFromRoute('+', 'layoutbuilder_extras.section_actions',
            $lbElement['link']['#url']->getRouteParameters())->toRenderable();
        $sectionActionsLink['#attributes']['class'] = ['toggle-sections-actions__plus', 'use-ajax'];
        $sectionActionsLink['#attributes']['data-dialog-type'] = 'dialog';
        $sectionActionsLink['#attributes']['data-dialog-renderer'] = 'off_canvas';

        $lbElement['actions_add_section_button'] = [
          '#type' => 'button',
          '#value' => '',
          '#suffix' => \Drupal::service('renderer')->render($sectionActionsLink),
          '#attributes' => [
            'aria-label' => new TranslatableMarkup('Add or import section'),
            'class' => [
              'toggle-section-actions',
            ],
          ],
        ];
        unset($lbElement['link'], $lbElement['choose_template_from_library']);
      }

      if (isset($lbElement['#type']) && $lbElement['#type'] === 'container' &&
        isset($lbElement['layout-builder__section'])) {

        // Wrap remove / configure buttons.
        $lbElement['actions'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'section-actions',
            ],
          ],
          [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'section-actions__inner',
              ],
            ],
            $lbElement['configure'] ?? [],
            $lbElement['remove'] ??  [],
          ],
        ];
        unset($lbElement['configure'], $lbElement['remove']);

        if ($isSectionLibraryEnabled && isset($lbElement['add_to_library'])) {
          $lbElement['actions'][0][] = $lbElement['add_to_library'];
          unset($lbElement['add_to_library']);
        }
      }
    }

    return $element;
  }

}
