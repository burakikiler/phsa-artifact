import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';

import { createTooltipElement } from './utils';
import AddTooltipCommand from './commands/addtooltipcommand';
import RemoveTooltipCommand from './commands/removetooltipcommand';

export default class TooltipEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  init() {
    this._defineScheme();
    this._defineConverters();

    this.editor.commands.add('add-tooltip', new AddTooltipCommand(this.editor));
    this.editor.commands.add('remove-tooltip', new RemoveTooltipCommand(this.editor));
  }

  /**
   * Define where the tooltip can be used.
   */
  _defineScheme() {
    const { schema } = this.editor.model;
    schema.extend('$text', { allowAttributes: 'data-tooltip' });
  }

  /**
   * Define the data conversions required when moving the tooltip between html
   * and the formate ckeditor uses.
   */
  _defineConverters() {
    const { editor } = this;

    editor.conversion.for('dataDowncast').attributeToElement({
      model: 'data-tooltip',
      view: createTooltipElement,
    });

    editor.conversion.for('editingDowncast').attributeToElement({
      model: 'data-tooltip',
      view: (data, writer) => {
        return createTooltipElement(data, writer);
      },
    });

    editor.conversion.for('upcast').elementToAttribute({
      view: {
        name: 'span',
        attributes: {
          'data-tooltip': true,
        },
      },
      model: {
        key: 'data-tooltip',
        value: (viewElement) => viewElement.getAttribute('data-tooltip'),
      },
    });
  }
}
