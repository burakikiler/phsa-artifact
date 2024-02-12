# Tooltip

Display always well-positionned tooltip when users hover texts.

Use simple text or an advanced custom block.

**No dependencies** - it uses the _Popper_ JS library from Drupal core.

## Installation

After having activated the module, you can use it with and/or without a text editor.

If you want to allow content editors to easily add a tooltip in their text, you need to install the `tooltip` filter in CKEditor (e.g. `ckeditor` v4 is the only editor currently supported out-of-the-box).

* Go to Admin > Configuration > Text format and Editors
* Select the desired HTML editor (e.g. usually `full_html`)
* Drag and drop ![The tooltip icon](./js/plugins/tooltip/icons/tooltip.png) the tooltip icon in the editor toolbar
* Activate the _Tooltip_ filter
* Save the configuration

Now, content editors can access the Tooltip button in the WYSIWYG.

## How to use

### Tooltip in Twig

Basically, you just need to add a `data-tooltip` attribute on a DOM element.

This attribute expects a JSON-encoded object with the following:

```json
{
  // Use a Block by ID or UUID.
  "block": "aCustomBlockID", 
  // Use arbitrary markup,
  "content": "My tooltip text",
  // Set the tooltip position
  "placememt": "top-start", // or "top","top-end","right-start", "right"...etc.
  // Set your own list of modifiers.
  // @see https://popper.js.org/docs/v2/modifiers/
  "modifiers": [], 
}
```

Important: do not forget to attach the `tooltip/tooltip` library.

```twig
{{ attach_library('tooltip/tooltip') }}
{% set blockId = "mycustomblockID" %}
{% set options = {"block": blockId, "placement": "top"} %}
<div class="tooltip visually-hidden" data-tooltip-id="{{ blockId }}">
  This is the block displayed on hover.
  {# Uncomment next line to display Tooltip's arrow #}
  {# <div data-popper-arrow></div> #}
</div>

<button data-tooltip="{{ options|json_encode }}">Hover me!</button>
```

### Tooltip in PHP

```php
use Drupal\Component\Serialization\Json;

$block_id_or_uuid = 'tooltip_custom_block_id';

$variables['tooltip'] = [
  '#type' => 'inline_template',
  '#template' => '<i class="icon icon-info" data-tooltip="{{ settings }}"></i>',
  '#context' => [
    'settings' => Json::encode([
      'block' => $block_id_or_uuid,
      'placement' => 'bottom',
      'modifiers' => [
        // Pixel-perfect the tooltip position.
        ['name' => 'offset', 'options' => ['offset' => [null, 8]]],
      ],
    ]),
  ],
];
```

## Custom tooltip blocks

To make things easier and more flexible, we recommend the use of custom Tooltip blocks. It's simple to create and make things more scalable, in particular when it comes to deliver your projects on stage/production. 

This module provides two basic blocks out-of-the-box:

* **Tooltip block** to create simple markup
* **Tooltip from entity** to display any given _Content Entity_

If you need to create other advanced HTML markup or anything else dynamically, you need to create your own Tooltip block extending the `TooltipBlockBase` class. The simplest way of doing so is to copy the existing Tooltip block in your own module, as follow: 

```bash
cp web/modules/contrib/tooltip/src/Plugin/Block/TooltipBlock.php web/modules/custom/mymodule/src/Plugin/Block/MyTooltipBlock.php
```

Edit `MyTooltipBlock.php` with your module's namespace and change the block ID:
```php
namespace Drupal\mymodule\Plugin\Block;

use Drupal\tooltip\TooltipBlockBase;

/**
 * Example of a custom Tooltip block.
 *
 * @Block(
 *   id = "tooltip_mymodule_block",
 *   admin_label = @Translation("Tooltip from my custom module"),
 *   category = @Translation("Tooltip")
 * )
 */
class TooltipBlock extends TooltipBlockBase {

  public function tooltip(&$build) {
    $build['hello'] = ['#markup' => 'moto'];
  }

}
```

Now, **place your block in a region of any of your installed themes**.

If no region suites your needs, we recommend to create a new region to place your block.

The _best_ solution is to create a `tooltips` region and print it in your `page.html.twig` template so that content will actually be rendered _before_ being transformed into a tooltip by the JS library. 

Edit your `mytheme.info.yml` file as follow:

```yaml
regions:
  tooltips: "Tooltips"
```

Alternatively, create a _Hidden_  region to place your blocks. In the backoffice, your tooltip blocks will be available for selection in the CKEditor dialog form. On frontend, blocks won't be rendered so the Tooltip module will try to load them dynamically with an Ajax call. 

```yaml
regions:
  hidden: 'Hidden'
```

## Credits

<a href="https://www.flaticon.com/free-icons/info" title="info icons">Info icons created by Freepik - Flaticon</a>
