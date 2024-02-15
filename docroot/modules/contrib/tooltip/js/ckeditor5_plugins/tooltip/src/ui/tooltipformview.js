import {
  ButtonView,
  createLabeledInputText,
  FocusCycler,
  LabeledFieldView,
  submitHandler,
  View,
  ViewCollection,
} from 'ckeditor5/src/ui';
import { icons } from 'ckeditor5/src/core';
import { FocusTracker, KeystrokeHandler } from 'ckeditor5/src/utils';

export default class TooltipFormView extends View {
  /**
   * @type {String}
   *   Displays as the title in the tooltip.
   */
  _label = '';

  /**
   * @type {String}
   *   Displays as the hint in the tooltip.
   */
  _labelHint = '';

  constructor(locale, label = 'Tooltip', labelHint = 'Displays above selection.') {
    super(locale);

    const { t } = locale;
    this._label = label;
    this._labelHint = labelHint;

    this.focusTracker = new FocusTracker();
    this.keystrokes = new KeystrokeHandler();
    this._focusables = new ViewCollection();

    this.tooltipInputView = this._createTooltipInput();

    this.saveButtonView = this._createButton(
      t('Save'),
      icons.check,
      'tooltip-button-save',
      'submit',
    );
    this.saveButtonView.type = 'submit';

    this.cancelButtonView = this._createButton(
      t('Cancel'),
      icons.cancel,
      'tooltip-button-cancel',
      'cancel',
    );

    this.children = this._createFormChildren();

    this._focusCycler = new FocusCycler({
      focusables: this._focusables,
      focusTracker: this.focusTracker,
      keystrokeHandler: this.keystrokes,
      actions: {
        // Navigate form fields backwards using the Shift + Tab keystroke.
        focusPrevious: 'shift + tab',

        // Navigate form fields forwards using the Tab key.
        focusNext: 'tab',
      },
    });

    const classList = ['ck', 'ck-link-form', 'ck-responsive-form'];

    this.setTemplate({
      tag: 'form',

      attributes: {
        class: classList,

        // https://github.com/ckeditor/ckeditor5-link/issues/90
        tabindex: '-1',
      },

      children: this.children,
    });
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();

    submitHandler({
      view: this,
    });

    const childViews = [this.tooltipInputView, this.saveButtonView, this.cancelButtonView];

    childViews.forEach((v) => {
      this._focusables.add(v);
      this.focusTracker.add(v.element);
    });

    this.keystrokes.listenTo(this.element);
  }

  /**
   * @inheritDoc
   */
  destroy() {
    super.destroy();

    this.focusTracker.destroy();
    this.keystrokes.destroy();
  }

  /**
   * Focuses the fist {@link #_focusables} in the form.
   */
  focus() {
    this._focusCycler.focusFirst();
  }

  /**
   * Creates a labeled input view.
   * @return {LabeledFieldView<InputTextView>}
   *   Labeled field view instance.
   */
  _createTooltipInput() {
    const t = this.locale?.t;
    const labeledInput = new LabeledFieldView(this.locale, createLabeledInputText);
    labeledInput.label = t(this._label);
    labeledInput.infoText = t(this._labelHint);

    // Set a character limit
    labeledInput.fieldView.extendTemplate({
      attributes: {
        maxlength: 250,
      },
    });

    return labeledInput;
  }

  /**
   * Create a button view.
   *
   * @param {String} label
   *   The button label.
   *
   * @param {String} icon
   *   The button icon.
   *
   * @param {String} className
   *   The additional button CSS class name.
   *
   * @param {String?} eventName
   *   An event name that the `ButtonView#execute` event will be delegated to.
   *
   * @return {ButtonView}
   *   The button view instance.
   */
  _createButton(label, icon, className, eventName = null) {
    const button = new ButtonView(this.locale);
    button.set({
      label,
      icon,
      tooltip: true,
    });

    button.extendTemplate({
      attributes: {
        class: className,
      },
    });

    if (eventName) {
      button.delegate('execute').to(this, eventName);
    }

    return button;
  }

  /**
   * Populates the {@link #children} collection of the form.
   *
   * If {@link module:link/linkcommand~LinkCommand#manualDecorators manual decorators} are configured in the editor, it creates an
   * additional `View` wrapping all {@link #_manualDecoratorSwitches} switch buttons corresponding
   * to these decorators.
   *
   * @return {ViewCollection}
   *   The children of tooltip form view.
   */
  _createFormChildren() {
    const children = this.createCollection();
    children.add(this.tooltipInputView);
    children.add(this.saveButtonView);
    children.add(this.cancelButtonView);

    return children;
  }
}
