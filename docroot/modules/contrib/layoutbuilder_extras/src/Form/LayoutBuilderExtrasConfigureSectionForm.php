<?php

namespace Drupal\layoutbuilder_extras\Form;

use Drupal\layout_builder\Form\ConfigureSectionForm;

/**
 * Class LayoutBuilderExtrasConfigureSectionForm.
 */
class LayoutBuilderExtrasConfigureSectionForm extends ConfigureSectionForm {

  /**
   * Get delta variable.
   *
   * @return int
   *   The delta.
   */
  public function getDelta(): int {
    return $this->delta;
  }

  /**
   * Get isUpdate variable.
   *
   * @return bool
   *   is it updated or not?
   */
  public function isUpdate(): bool {
    return $this->isUpdate;
  }

}
