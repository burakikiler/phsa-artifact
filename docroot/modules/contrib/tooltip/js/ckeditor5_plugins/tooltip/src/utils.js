/**
 * Method for converting a string into a json object.
 *
 * @param {String} jsonString
 *   The string to convert into JSON.
 *
 * @return {JSON}
 *   The converted JSON object.
 */
function valueToJson(jsonString) {
  if (!(typeof jsonString === 'string') || jsonString.length === 0 || !jsonString.startsWith('{')) {
    return null;
  }

  try {
    return JSON.parse(jsonString);
  } catch {
    return null;
  }
}

/**
 * Convert a JSON object into a string.
 *
 * @param {Object} jsonData
 *   The JSON object to convert into a string.
 *
 * @return {String|null}
 *   The converted string.
 */
function jsonToValue(jsonData) {
  if (!(typeof jsonData === 'object')) {
    return null;
  }

  return JSON.stringify(jsonData);
}

/**
 * Method for updating ckeditor 4 data to ckeditor 5 data.
 *
 * @param {String} tooltip
 *   A string containing tooltip information.
 *
 * @return {String|null}
 *   Ckeditor 5 compatible string.
 */
function handleDataMigration(tooltip) {
  // Check that there is values being parsed
  if (!tooltip) {
    return null;
  }

  // Json must start with {}
  if (!tooltip.startsWith('{')) {
    return tooltip;
  }

  // Try and parse the json data
  try {
    const jsonData = JSON.parse(tooltip);
    tooltip = jsonData.content ?? tooltip;
  } catch {}

  return tooltip;
}

/**
 * Method for creating an arbitery tooltip.
 *
 * @param {string} tooltipData
 *   The tooltip data definition.
 *
 * @param {Object} writer
 *   The ckeditor writer
 *
 * @return {Element}
 *   The tooltip element.
 */
function createTooltipElement(tooltip, { writer }) {
  // Make sure we convert the json object into a string
  if (typeof tooltip === 'object') {
    tooltip = jsonToValue(tooltip);
  }

  const tooltipElement = writer.createAttributeElement(
    'span',
    { 'data-tooltip': tooltip },
    { priority: 6 },
  );

  writer.setCustomProperty('tooltip', true, tooltipElement);
  return tooltipElement;
}

/**
 * Check if a node is a tooltip.
 *
 * @param {ViewNode|ViewDocumentFragment} node
 *   The node to be chcked
 * @return {Boolean}
 *   Returns `true` if a given view node is the link element.
 */
function isTooltipElement(node) {
  return node.is('attributeElement') && !!node.getCustomProperty('tooltip');
}

/**
 *
 * @param {HTMLElement} element
 *   The element to check.
 *
 * @param {Object} schema
 *   The tooltip schema.
 *
 * @return {Boolean}
 *   Whether this is a tooltip element.
 */
function isTooltipableElement(element, schema) {
  if (!element) {
    return false;
  }

  return schema.checkAttribute(element.name, 'data-tooltip');
}

export {
  createTooltipElement,
  isTooltipableElement,
  isTooltipElement,
  handleDataMigration,
  jsonToValue,
  valueToJson,
};
