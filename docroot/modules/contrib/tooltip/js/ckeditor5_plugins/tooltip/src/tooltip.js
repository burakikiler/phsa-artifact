import { Plugin } from 'ckeditor5/src/core';

import TooltipUI from './tooltipui';
import TooltipEditing from './tooltipediting';

export default class Tooltip extends Plugin {
  /**
   * @inheritDoc
   */
  static get requires() {
    return [TooltipEditing, TooltipUI];
  }

  /**
   * @inheritDoc
   */
  static get pluginName() {
    return 'Tooltip';
  }
}
