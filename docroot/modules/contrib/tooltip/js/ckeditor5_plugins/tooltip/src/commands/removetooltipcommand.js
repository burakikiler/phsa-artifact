/* eslint-disable no-restricted-syntax */
import { Command } from 'ckeditor5/src/core';
import { first, toMap } from 'ckeditor5/src/utils';
import { findAttributeRange } from 'ckeditor5/src/typing';

import { isTooltipableElement } from '../utils';

export default class RemoveTooltipCommand extends Command {
  /**
   * @inheritDoc
   */
  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;
    const selectedElement = selection.getSelectedElement();

    if (isTooltipableElement(selectedElement, model.schema)) {
      this.isEnabled = model.schema.checkAttribute(selectedElement, 'data-tooltip');
    } else {
      this.isEnabled = model.schema.checkAttributeInSelection(selection, 'data-tooltip');
    }
  }

  /**
   * Executes the command.
   *
   * When the selection is collapsed, it removes the `linkHref` attribute from each node with the same `linkHref` attribute value.
   * When the selection is non-collapsed, it removes the `linkHref` attribute from each node in selected ranges.
   *
   * # Decorators
   *
   * If {@link module:link/linkconfig~LinkConfig#decorators `config.link.decorators`} is specified,
   * all configured decorators are removed together with the `linkHref` attribute.
   *
   * @fires execute
   */
  execute() {
    const { editor } = this;
    const { model } = editor;
    const { selection } = model.document;

    model.change((writer) => {
      const rangesToRemove = selection.isCollapsed
        ? [
            findAttributeRange(
              selection.getFirstPosition(),
              'data-tooltip',
              selection.getAttribute('data-tooltip'),
              model,
            ),
          ]
        : model.schema.getValidRanges(selection.getRanges(), 'data-tooltip');

      for (const range of rangesToRemove) {
        writer.removeAttribute('data-tooltip', range);
      }
    });
  }
}
