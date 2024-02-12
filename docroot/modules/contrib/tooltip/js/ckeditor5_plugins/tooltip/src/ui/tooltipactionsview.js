import { ButtonView, View, ViewCollection, FocusCycler, LabelView } from 'ckeditor5/src/ui';
import { FocusTracker, KeystrokeHandler } from 'ckeditor5/src/utils';
import { icons } from 'ckeditor5/src/core';

export default class TooltipActionsViews extends View {
  /**
   * @type {String}
   *   The tooltip label.
   */
  _label = '';

  constructor(locale, label = 'Tooltip') {
    super(locale);

    this.focusTracker = new FocusTracker();
    this.keystrokes = new KeystrokeHandler();
    this._focusables = new ViewCollection();

    const { t } = this;
    this._label = label;

    this.previewButtonView = this._createPreviewButton();
    this.removeButtonView = this._createButton(t('Remove'), icons.eraser, 'remove');
    this.editButtonView = this._createButton(
      t(`Edit ${this._label.toLowerCase()}`),
      icons.pencil,
      'edit',
    );

    this.set('tooltip', undefined);

    this._focusCycler = new FocusCycler({
      focusables: this._focusables,
      focusTracker: this.focusTracker,
      keystrokeHandler: this.keystrokes,
      actions: {
        focusPrevious: 'shift + tab',
        focusNext: 'tab',
      },
    });

    this.setTemplate({
      tag: 'div',

      attributes: {
        class: ['ck', 'ck-link-actions', 'ck-responsive-form'],

        // https://github.com/ckeditor/ckeditor5-link/issues/90
        tabindex: '-1',
      },

      children: [this.previewButtonView, this.editButtonView, this.removeButtonView],
    });
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();

    const childViews = [this.previewButtonView, this.editButtonView, this.removeButtonView];

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
   * Create a new button view.
   *
   * @param {String} label
   *   The button label.
   *
   * @param {String} icon
   *   The button icon.
   *
   * @param {String} eventName
   *   An event name that the `ButtonView#execute` event will be delegated to.
   *
   * @return {ButtonView}
   *   The button view instance.
   */
  _createButton(label, icon, eventName = '') {
    const buttonView = new ButtonView(this.locale);

    buttonView.set({
      label,
      icon,
      tooltip: true,
    });

    buttonView.delegate('execute').to(this, eventName);
    return buttonView;
  }

  /**
   * Creates a link for tooltip preview button.
   *
   * @return {ButtonView}
   *   The button view instance.
   */
  _createPreviewButton() {
    const labelView = new LabelView(this.locale);
    const { t } = this;

    labelView.set({
      withText: true,
    });

    labelView.extendTemplate({
      attributes: {
        class: 'tooltip-label-label',
      },
    });

    // Update the label with migrated data
    labelView.bind('text').to(this, 'tooltip', (tooltip) => {
      return tooltip || t(`This has no ${this._label.toLowerCase()}`);
    });

    labelView.bind('isEnabled').to(this, 'tooltip', (tooltip) => !!tooltip);

    if (labelView.template) {
      labelView.template.tag = 'span';
      labelView.template.eventListeners = {};
    }

    return labelView;
  }
}
