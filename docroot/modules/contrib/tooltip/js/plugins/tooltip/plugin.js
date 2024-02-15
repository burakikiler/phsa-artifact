/**
 * @file
 * Tooltip plugin.
 */
(function (Drupal, CKEDITOR) {

  /**
   * Get the surrounding element of current selection.
   *
   * @param {CKEDITOR.editor} editor
   *   The CKEditor editor object
   * @param string tag
   *   A DOM tag name (ex: span).
   *
   * @return {?HTMLElement}
   *   The selected element, or null.
   *
   */
  function getSelectedElementByTag(editor, tag) {
    const selection = editor.getSelection();
    const selectedElement = selection.getSelectedElement();
    if (selectedElement && selectedElement.is(tag)) {
      return selectedElement;
    }

    const range = selection.getRanges(true)[0];

    if (range) {
      range.shrink(CKEDITOR.SHRINK_TEXT);
      return editor.elementPath(range.getCommonAncestor()).contains(tag, 1);
    }

    return null;
  }

  /**
   * Parse current selected element or text in Editor.
   *
   * Decoded list of tooltip settings from [data-tooltip].
   *
   * Also always add the selection's inner HTML.
   *
   * @return {Object}
   *   List of tooltip settings and the text.
   */
  function getExistingValues(editor) {
    let existingValues = {};

    let span = getSelectedElementByTag(editor, 'span');

    // Parse focused element.
    if (span && span.$) {
      existingValues['text'] = span.getHtml() || span.$.innerHTML;

      if (span.hasAttribute('data-tooltip')) {
        let attr = JSON.parse(span.getAttribute('data-tooltip') || '{}');
        for (let k in attr) {
          existingValues[k] = attr[k];
        }
      }
    }

    // Parse focused text only.
    else {
      let selection = editor.getSelection();
      existingValues['text'] = selection.getSelectedText();
    }

    return existingValues;
  }


  /**
   * Inject Tooltip in CKEditor.
   */
  CKEDITOR.plugins.add('tooltip', {
    icons: 'tooltip',

    init: function (editor) {
      // Stop now if not allowed to use editor.
      if (!editor.config.tooltip.modal_url) {
        console.log(
          Drupal.t('Tooltip button hidden because access to editor:@editor forbidden.', {
            '@editor': editor.config.drupal.format
          })
        );
        return;
      }

      // Toolbar button execution.
      editor.addCommand('tooltip', {
        allowedContent: 'span[!data-tooltip]',
        requiredContent: 'span[data-tooltip]',
        exec: function exec(editor) {
          const saveCallback = function (values) {
            editor.fire('saveSnapshot');

            // Get tooltip span.
            let span = getSelectedElementByTag(editor, 'span');
            let isElement = span && span.$;

            // Create and insert a new span, if nothing selected.
            if (!isElement && values.trigger == 'submit') {
              span = new CKEDITOR.dom.element('span');
              span.setHtml(editor.getSelectedHtml(true) || editor.config.tooltip.text);
              isElement = span && span.$;

              const selection = editor.getSelection();
              const range = selection.getRanges(true)[0];
              range.shrink(CKEDITOR.SHRINK_TEXT);
              range.deleteContents();
              range.insertNode(span);
            }

            // Stop now remove action and nothing selected.
            if (!isElement && values.trigger == 'remove') {
              return;
            }

            // Add tooltip data on span.
            if (values.trigger == 'submit') {
              span.setAttribute('data-tooltip', JSON.stringify({
                'block': values.block || null,
                'content': values.content || null,
                'placement': values.placement || editor.config.tooltip.placement,
                'arrow': Boolean(values.arrow ?? editor.config.tooltip.arrow),
              }));
            }
            else if (values.trigger == 'remove') {
              span.removeAttribute('data-tooltip');
            }

            editor.fire('saveSnapshot');
          };

          let existingValues = getExistingValues(editor);

          let dialogSettings = {
            'dialogClass': 'tooltip-dialog',
            'title': Drupal.t('Tooltip')
          };

          Drupal.ckeditor.openDialog(
            editor,
            editor.config.tooltip.modal_url,
            existingValues,
            saveCallback,
            dialogSettings
          );
        }
      });

      if (editor.ui.addButton) {
        editor.ui.addButton('tooltip', {
          label: Drupal.t('Insert tooltip'),
          command: 'tooltip'
        });
      }

      // On "Ctrl+T" press.
      editor.setKeystroke(CKEDITOR.CTRL + 116, 'tooltip');

      // Select tooltip on doubleclick.
      editor.on('doubleclick', function (evt) {
        var element = getSelectedElementByTag(editor, 'span') || evt.data.element;

        if (!element.isReadOnly()) {
          if (element.is('span') && element.hasAttribute('data-tooltip')) {
            editor.getSelection().selectElement(element);
            editor.getCommand('tooltip').exec();
          }
        }
      });
    }
  });
})(Drupal, CKEDITOR);
