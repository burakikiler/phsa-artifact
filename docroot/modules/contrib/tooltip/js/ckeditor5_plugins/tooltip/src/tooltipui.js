import { ButtonView, ContextualBalloon, clickOutsideHandler } from 'ckeditor5/src/ui';
import { Plugin } from 'ckeditor5/src/core';
import { isWidget } from 'ckeditor5/src/widget';
import { ClickObserver } from 'ckeditor5/src/engine';

import { isTooltipElement } from './utils';
import icon from '../../../../icons/tooltip.svg';
import TooltipActionsViews from './ui/tooltipactionsview';
import TooltipFormView from './ui/tooltipformview';

const VISUAL_SELECTION_MARKER_NAME = 'tooltip-ui';

/**
 * Returns a tooltip element if there's one among the ancestors of the provided `Position`.
 *
 * @param {ViewPosition} position
 *   View position to analyze.
 * @return {ViewAttributeElement|null}
 *   Link element at the position or null.
 */
function findTooltipElementAncestor(position) {
  return position.getAncestors().find((ancestor) => isTooltipElement(ancestor)) || null;
}

export default class TooltipUI extends Plugin {
  /**
   * @type {TooltipActionsViews} The actions view displayed inside of the balloon.
   */
  actionsView = null;

  /**
   * @type {TooltipFormView} The form view displayed inside the balloon.
   */
  formView = null;

  /**
   * @inheritDoc
   */
  static get requires() {
    return [ContextualBalloon];
  }

  /**
   * @inheritDoc
   */
  static get pluginName() {
    return 'TooltipUI';
  }

  /**
   * @inheritDoc
   */
  init() {
    const { editor } = this;

    editor.editing.view.addObserver(ClickObserver);
    this._balloon = editor.plugins.get(ContextualBalloon);

    this._createToolbarTooltipButton();
    this._enableBallonActivators();

    // Renders a fake visual selection marker on an expanded selection.
    editor.conversion.for('editingDowncast').markerToHighlight({
      model: VISUAL_SELECTION_MARKER_NAME,
      view: {
        classes: ['tooltip-fake-link-selection'],
      },
    });

    // Renders a fake visual selection marker on a collapsed selection.
    editor.conversion.for('editingDowncast').markerToElement({
      model: VISUAL_SELECTION_MARKER_NAME,
      view: {
        name: 'span',
        classes: ['tooltip-fake-link-selection', 'tooltip-fake-link-selection_collapsed'],
      },
    });
  }

  /**
   * @inheritDoc
   */
  destroy() {
    super.destroy();

    if (this.formView) {
      this.formView.destroy();
    }

    if (this.actionsView) {
      this.actionsView.destroy();
    }
  }

  /**
   * Creates views.
   */
  _createViews() {
    this.actionsView = this._createActionsView();
    this.formView = this._createFormView();

    // Attach lifecycle actions to the the balloon.
    this._enableUserBalloonInteractions();
  }

  /**
   * Creates the {@link module:link/ui/linkactionsview~LinkActionsView} instance.
   *
   * @return {TooltipActionsViews}
   *   The tooltip actions view instance.
   */
  _createActionsView() {
    const { editor } = this;
    const { config } = editor;

    const tooltipLabel = config.get('tooltip_label');

    const actionsView = new TooltipActionsViews(editor.locale, tooltipLabel);
    const addCommand = editor.commands.get('add-tooltip');
    const removeCommand = editor.commands.get('remove-tooltip');

    // Bind the actions view label to the tooltip content.
    actionsView.bind('tooltip').to(addCommand, 'value', (tooltipData) => {
      return tooltipData?.content || '';
    });

    actionsView.editButtonView.bind('isEnabled').to(addCommand);
    actionsView.removeButtonView.bind('isEnabled').to(removeCommand);

    this.listenTo(actionsView, 'edit', () => {
      this._addFormView();
    });

    this.listenTo(actionsView, 'remove', () => {
      editor.execute('remove-tooltip');
      this._hideUI();
    });

    // Close the panel on esc key press when the **actions have focus**.
    actionsView.keystrokes.set('Esc', (data, cancel) => {
      this._hideUI();
      cancel();
    });

    return actionsView;
  }

  /**
   * Creates the {@link module:link/ui/linkformview~LinkFormView} instance.
   *
   * @return {TooltipFormView}
   *   The `TooltipFormView` instance.
   *
   */
  _createFormView() {
    const { editor } = this;
    const { config } = editor;

    const tooltipLabel = editor.config.get('tooltip_label');
    const tooltipHint = config.get('tooltip_hint');

    const formView = new TooltipFormView(editor.locale, tooltipLabel, tooltipHint);
    const addCommand = editor.commands.get('add-tooltip');

    // The first creation of the from view.
    formView.tooltipInputView.fieldView.bind('value').to(addCommand, 'value', (tooltipData) => {
      return tooltipData?.content || '';
    });

    // Form elements should be read-only when corresponding commands are disabled.
    formView.tooltipInputView.bind('isEnabled').to(addCommand, 'isEnabled');
    formView.saveButtonView.bind('isEnabled').to(addCommand);

    // Execute link command after clicking the "Save" button.
    this.listenTo(formView, 'submit', () => {
      const { value } = formView.tooltipInputView.fieldView.element;
      editor.execute('add-tooltip', value);
      this._hideUI();
    });

    // Hide the panel after clicking the "Cancel" button.
    this.listenTo(formView, 'cancel', () => {
      this._closeFormView();
    });

    // Close the panel on esc key press when the **form has focus**.
    formView.keystrokes.set('Esc', (data, cancel) => {
      this._closeFormView();
      cancel();
    });

    return formView;
  }

  /**
   * Creates a toolbar Link button. Clicking this button will show
   * a {@link #_balloon} attached to the selection.
   */
  _createToolbarTooltipButton() {
    const { editor } = this;
    const { t, config } = editor;
    const label = config.get('tooltip_label') || 'Tooltip';

    const addCommand = editor.commands.get('add-tooltip');

    editor.ui.componentFactory.add('tooltip', (locale) => {
      const button = new ButtonView(locale);

      button.isEnabled = true;
      button.label = t(`Insert ${label}`);
      button.icon = icon;
      button.tooltip = true;
      button.isToggleable = true;

      // Bind button to the command.
      button.bind('isEnabled').to(addCommand, 'isEnabled');
      button.bind('isOn').to(addCommand, 'value', (value) => !!value);

      // Show the panel on button click.
      this.listenTo(button, 'execute', () => this._showUI(true));

      return button;
    });
  }

  /**
   * Attaches actions that control whether the balloon panel containing the
   * {@link #formView} should be displayed.
   */
  _enableBallonActivators() {
    const { editor } = this;
    const viewDocument = editor.editing.view.document;

    this.listenTo(viewDocument, 'click', () => {
      const parentTooltip = this._getSelectedTooltipElement();

      if (parentTooltip) {
        this._showUI();
      }
    });
  }

  /**
   * Attaches actions that control whether the balloon panel containing the
   * {@link #formView} is visible or not.
   */
  _enableUserBalloonInteractions() {
    this.editor.keystrokes.set(
      'Tab',
      (data, cancel) => {
        if (this._areActionsVisible && !this.actionsView?.focusTracker.isFocused) {
          this.actionsView?.focus();
          cancel();
        }
      },
      {
        priority: 'high',
      },
    );

    this.editor.keystrokes.set('Esc', (data, cancel) => {
      if (this._isUIVisible) {
        this._hideUI();
        cancel();
      }
    });

    clickOutsideHandler({
      emitter: this.formView,
      contextElements: [this._balloon.view.element],
      activator: () => this._isUIVisible,
      callback: () => this._hideUI(),
    });
  }

  /**
   * Adds the {@link #actionsView} to the {@link #_balloon}.
   *
   * @internal
   */
  _addActionsView() {
    if (!this.actionsView) {
      this._createViews();
    }

    if (this._areActionsInPanel) {
      return;
    }

    this._balloon.add({
      view: this.actionsView,
      position: this._getBalloonPositionData(),
    });
  }

  /**
   * Adds the {@link #formView} to the {@link #_balloon}.
   */
  _addFormView() {
    if (!this.formView) {
      this._createViews();
    }

    if (this._isFormInPanel) {
      return;
    }

    const { editor } = this;
    const addCommand = editor.commands.get('add-tooltip');

    this._balloon.add({
      view: this.formView,
      position: this._getBalloonPositionData(),
    });

    if (this._balloon.visibleView === this.formView) {
      this.formView.tooltipInputView.fieldView.select();
    }

    this.formView.tooltipInputView.fieldView.element.value = addCommand.value?.content || '';
  }

  /**
   * Closes the form view. Decides whether the balloon should be hidden completely or if the action view should be shown. This is
   * decided upon the link command value (which has a value if the document selection is in the link).
   *
   * Additionally, if any {@link module:link/linkconfig~LinkConfig#decorators} are defined in the editor configuration, the state of
   * switch buttons responsible for manual decorator handling is restored.
   */
  _closeFormView() {
    const addCommand = this.editor.commands.get('add-tooltip');

    if (addCommand.value !== undefined) {
      this._removeFormView();
    } else {
      this._hideUI();
    }
  }

  /**
   * Removes the {@link #formView} from the {@link #_balloon}.
   */
  _removeFormView() {
    if (!this._isFormInPanel) {
      return;
    }

    this.formView.saveButtonView.focus();
    this._balloon.remove(this.formView);
    this.editor.editing.view.focus();
  }

  /**
   * Shows the correct UI type. It is either {@link #formView} or {@link #actionsView}.
   *
   * @internal
   *
   * @param {Boolean?} forceVisible
   *   Should this view be force shown.
   */
  _showUI(forceVisible = false) {
    if (!this.formView) {
      this._createViews();
    }

    if (!this._getSelectedTooltipElement()) {
      this._addActionsView();

      if (forceVisible) {
        this._balloon.showStack('main');
      }

      this._addFormView();
    } else {
      if (this._areActionsVisible) {
        this._addFormView();
      } else {
        this._addActionsView();
      }

      if (forceVisible) {
        this._balloon.showStack('main');
      }
    }

    this._startUpdatingUI();
  }

  /**
   * Removes the {@link #formView} from the {@link #_balloon}.
   *
   * See {@link #_addFormView}, {@link #_addActionsView}.
   */
  _hideUI() {
    if (!this._isUIInPanel) {
      return;
    }

    const { editor } = this;

    this.stopListening(editor.ui, 'update');
    this.stopListening(this._balloon, 'change:visibleView');

    // Make sure the focus always gets back to the editable _before_ removing the focused form view.
    // Doing otherwise causes issues in some browsers. See https://github.com/ckeditor/ckeditor5-link/issues/193.
    editor.editing.view.focus();

    // Remove form first because it's on top of the stack.
    this._removeFormView();

    // Then remove the actions view because it's beneath the form.
    this._balloon.remove(this.actionsView);

    // Set these to null so they can be re generated next time.
    this.formView = null;
    this.actionsView = null;
  }

  /**
   * Makes the UI react to the {@link module:ui/editorui/editorui~EditorUI#event:update} event to
   * reposition itself when the editor UI should be refreshed.
   *
   * See: {@link #_hideUI} to learn when the UI stops reacting to the `update` event.
   */
  _startUpdatingUI() {
    const { editor } = this;
    const viewDocument = editor.editing.view.document;

    function getSelectionParent() {
      return viewDocument.selection.focus
        .getAncestors()
        .reverse()
        .find((node) => node.is('element'));
    }

    let prevSelectedTooltip = this._getSelectedTooltipElement();
    let prevSelectionParent = getSelectionParent();
    let index = 0;

    const update = () => {
      const selectedTooltip = this._getSelectedTooltipElement();
      const selectionParent = getSelectionParent();

      // Hide the panel if:
      //
      // * the selection went out of the EXISTING link element. E.g. user moved the caret out
      //   of the link,
      // * the selection went to a different parent when creating a NEW link. E.g. someone
      //   else modified the document.
      // * the selection has expanded (e.g. displaying link actions then pressing SHIFT+Right arrow).
      //
      // Note: #_getSelectedLinkElement will return a link for a non-collapsed selection only
      // when fully selected.
      if (
        (prevSelectedTooltip && !selectedTooltip) ||
        (!prevSelectedTooltip && selectionParent !== prevSelectionParent)
      ) {
        this._hideUI();
      }
      // Update the position of the panel when:
      //  * link panel is in the visible stack
      //  * the selection remains in the original link element,
      //  * there was no link element in the first place, i.e. creating a new link
      else if (this._isUIVisible) {
        // If still in a link element, simply update the position of the balloon.
        // If there was no link (e.g. inserting one), the balloon must be moved
        // to the new position in the editing view (a new native DOM range).
        this._balloon.updatePosition(this._getBalloonPositionData());
      }

      prevSelectedTooltip = selectedTooltip;
      prevSelectionParent = selectionParent;
    };

    this.listenTo(editor.ui, 'update', update);
    this.listenTo(this._balloon, 'change:visibleView', update);
  }

  /**
   * Returns `true` when {@link #formView} is in the {@link #_balloon}.
   */
  get _isFormInPanel() {
    return !!this.formView && this._balloon.hasView(this.formView);
  }

  /**
   * Returns `true` when {@link #actionsView} is in the {@link #_balloon}.
   */
  get _areActionsInPanel() {
    return !!this.actionsView && this._balloon.hasView(this.actionsView);
  }

  /**
   * Returns `true` when {@link #actionsView} is in the {@link #_balloon} and it is
   * currently visible.
   */
  get _areActionsVisible() {
    return !!this.actionsView && this._balloon.visibleView === this.actionsView;
  }

  /**
   * Returns `true` when {@link #actionsView} or {@link #formView} is in the {@link #_balloon}.
   */
  get _isUIInPanel() {
    return this._isFormInPanel || this._areActionsInPanel;
  }

  /**
   * Returns `true` when {@link #actionsView} or {@link #formView} is in the {@link #_balloon} and it is
   * currently visible.
   */
  get _isUIVisible() {
    const { visibleView } = this._balloon;
    return (!!this.formView && visibleView == this.formView) || this._areActionsVisible;
  }

  /**
   * Returns positioning options for the {@link #_balloon}. They control the way the balloon is attached
   * to the target element or selection.
   *
   * If the selection is collapsed and inside a link element, the panel will be attached to the
   * entire link element. Otherwise, it will be attached to the selection.
   *
   * @return {Partial<PositionOptions>}
   *   The partial position instance.
   */
  _getBalloonPositionData() {
    const { view } = this.editor.editing;
    const { model } = this.editor;
    const viewDocument = view.document;
    let target = null;

    if (model.markers.has(VISUAL_SELECTION_MARKER_NAME)) {
      // There are cases when we highlight selection using a marker (#7705, #4721).
      const markerViewElements = Array.from(
        this.editor.editing.mapper.markerNameToElements(VISUAL_SELECTION_MARKER_NAME),
      );
      const newRange = view.createRange(
        view.createPositionBefore(markerViewElements[0]),
        view.createPositionAfter(markerViewElements[markerViewElements.length - 1]),
      );

      target = view.domConverter.viewRangeToDom(newRange);
    } else {
      // Make sure the target is calculated on demand at the last moment because a cached DOM range
      // (which is very fragile) can desynchronize with the state of the editing view if there was
      // any rendering done in the meantime. This can happen, for instance, when an inline widget
      // gets unlinked.
      target = () => {
        const targetLink = this._getSelectedTooltipElement();

        return targetLink
          ? // When selection is inside link element, then attach panel to this element.
            view.domConverter.mapViewToDom(targetLink)
          : // Otherwise attach panel to the selection.
            view.domConverter.viewRangeToDom(viewDocument.selection.getFirstRange());
      };
    }

    return { target };
  }

  /**
   * Returns the link {@link module:engine/view/attributeelement~AttributeElement} under
   * the {@link module:engine/view/document~Document editing view's} selection or `null`
   * if there is none.
   *
   * **Note**: For a nonâ€“collapsed selection, the link element is returned when **fully**
   * selected and the **only** element within the selection boundaries, or when
   * a linked widget is selected.
   *
   * @return {ViewAttributeElement|null}
   *   The selected tooltip element or null.
   */
  _getSelectedTooltipElement() {
    const { view } = this.editor.editing;
    const { selection } = view.document;
    const selectedElement = selection.getSelectedElement();

    if (selection.isCollapsed || (selectedElement && isWidget(selectedElement))) {
      return findTooltipElementAncestor(selection.getFirstPosition());
    }

    if (selection.getFirstRange()) {
      const range = selection.getFirstRange().getTrimmed();
      const startTooltip = findTooltipElementAncestor(range.start);
      const endTooltip = findTooltipElementAncestor(range.end);

      if (!startTooltip || startTooltip !== endTooltip) {
        return null;
      }

      if (view.createRangeIn(startTooltip).getTrimmed().isEqual(range)) {
        return startTooltip;
      }
    }

    return null;
  }
}
