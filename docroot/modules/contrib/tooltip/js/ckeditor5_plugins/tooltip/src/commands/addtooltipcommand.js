/* eslint-disable class-methods-use-this */
/* eslint-disable no-restricted-syntax */
import { Command } from 'ckeditor5/src/core';
import { first, toMap } from 'ckeditor5/src/utils';
import { findAttributeRange } from 'ckeditor5/src/typing';

import { isTooltipableElement, jsonToValue, valueToJson } from '../utils';

function extractTooltipFromSelection(selection) {
  if (selection.isCollapsed) {
    const firstPosition = selection.getFirstPosition();

    if (!firstPosition) {
      return null;
    }

    return firstPosition.textNode && firstPosition.textNode.data;
  }

  const firstRange = selection.getFirstRange();

  if (!firstRange) {
    return null;
  }

  const rangeItems = Array.from(selection.getFirstRange().getItems());

  if (rangeItems.length > 1) {
    return null;
  }

  const firstNode = rangeItems[0];

  if (firstNode.is('$text') || firstNode.is('$textProxy')) {
    return firstNode.data;
  }

  return null;
}

export default class AddTooltipCommand extends Command {
  /**
   * The value of the `'data-tooltip'` attribute if the start of the selection is located in a node with this attribute.
   *
   * @observable
   * @readonly
   */
  value = undefined;

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;
    const selectedElement = selection.getSelectedElement() || first(selection.getSelectedBlocks());

    if (isTooltipableElement(selectedElement, model.schema)) {
      this.value = selectedElement.getAttribute('data-tooltip');
      this.isEnabled = model.schema.checkAttribute(selectedElement, 'data-tooltip');
    } else {
      this.value = selection.getAttribute('data-tooltip');
      this.isEnabled = model.schema.checkAttributeInSelection(selection, 'data-tooltip');
    }

    // Check to see if a value was imported.  If it was, we need to convert it
    // into a usable JSON object.
    if (this.value) {
      this.value = valueToJson(this.value);
    }
  }

  execute(tooltip) {
    const { model } = this.editor;
    const { selection } = model.document;

    // Convert the tooltip json object into a string.
    tooltip = jsonToValue({ content: tooltip });

    model.change((writer) => {
      if (selection.isCollapsed) {
        const position = selection.getFirstPosition();

        if (selection.hasAttribute('data-tooltip')) {
          const tooltipText = extractTooltipFromSelection(selection);
          let tooltipRange = findAttributeRange(
            position,
            'data-tooltip',
            selection.getAttribute('data-tooltip'),
            model,
          );

          if (selection.getAttribute('data-tooltip') === tooltipText) {
            tooltipRange = this._updateTooltipContent(model, writer, tooltipRange, tooltip);
          }

          writer.setAttribute('data-tooltip', tooltip, tooltipRange);

          // Put the selection at the end of the updated link.
          writer.setSelection(writer.createPositionAfter(tooltipRange.end.nodeBefore));
        } else if (tooltip !== '') {
          const attributes = toMap(selection.getAttributes());

          attributes.set('data-tooltip', tooltip);

          const { end: positionAfter } = model.insertContent(
            writer.createText(tooltip, attributes),
            position,
          );

          // Put the selection at the end of the inserted link.
          // Using end of range returned from insertContent in case nodes with the same attributes got merged.
          writer.setSelection(positionAfter);
        }

        ['data-tooltip'].forEach((item) => {
          writer.removeSelectionAttribute(item);
        });
      } else {
        const ranges = model.schema.getValidRanges(selection.getRanges(), 'data-tooltip');

        const allowedRanges = [];

        for (const element of selection.getSelectedBlocks()) {
          if (model.schema.checkAttribute(element, 'data-tooltip')) {
            allowedRanges.push(writer.createRangeOn(element));
          }
        }

        const rangesToUpdate = allowedRanges.slice();

        for (const range of ranges) {
          if (this._isRangeToUpdate(range, allowedRanges)) {
            rangesToUpdate.push(range);
          }
        }

        for (const range of rangesToUpdate) {
          let tooltipRange = range;

          if (rangesToUpdate.length === 1) {
            // Current text of the link in the document.
            const tooltipText = extractTooltipFromSelection(selection);

            if (selection.getAttribute('data-tooltip') === tooltipText) {
              tooltipRange = this._updateTooltipContent(model, writer, range, tooltip);
              writer.setSelection(writer.createSelection(tooltipRange));
            }
          }

          writer.setAttribute('data-tooltip', tooltip, tooltipRange);
        }
      }
    });
  }

  /**
   * Checks whether specified `range` is inside an element that accepts the `linkHref` attribute.
   *
   * @param {Range} range
   *   A range to check.
   *
   * @param {Range[]} allowedRanges
   *   An array of ranges created on elements where the attribute is accepted.
   *
   * @return {Boolean}
   *  Wehter range is in allowedRanges
   */
  _isRangeToUpdate(range, allowedRanges) {
    for (const allowedRange of allowedRanges) {
      // A range is inside an element that will have the `linkHref` attribute. Do not modify its nodes.
      if (allowedRange.containsRange(range)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Updates selected tootlip with a new value as its content and as its data-tooltip attribute.
   *
   * @param {Model} model
   *   Model is needed to insert content.
   *
   * @param {Writer} writer
   *   Writer is needed to create text element in model.
   *
   * @param {Range} range
   *   A range where the new content should be instered,
   *
   * @param {String} tooltip
   *   The tooltip vlaue which should be in the data-tooltip attribute in the content.
   *
   * @return {Range}
   *   The updated range
   */
  _updateTooltipContent(model, writer, range, tooltip) {
    const text = writer.createText(tooltip, { 'data-tooltip': tooltip });
    return model.insertContent(text, range);
  }
}
