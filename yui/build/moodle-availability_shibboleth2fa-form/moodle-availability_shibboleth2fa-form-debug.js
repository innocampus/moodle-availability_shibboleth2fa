YUI.add('moodle-availability_shibboleth2fa-form', function (Y, NAME) {

/**
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global M */
/**
 * JavaScript for form editing profile conditions.
 *
 * @module moodle-availability_shibboleth2fa-form
 */
M.availability_shibboleth2fa = M.availability_shibboleth2fa || {};

/*
 * @class M.availability_shibboleth2fa.form
 * @extends M.core_availability.plugin
 */
M.availability_shibboleth2fa.form = Y.Object(M.core_availability.plugin);

/**
 * Initialises this plugin.
 *
 * @method initInner
 */
M.availability_shibboleth2fa.form.initInner = function() {
    // Do nothing.
};

M.availability_shibboleth2fa.form.getNode = function(json) {
    var strings = M.str.availability_shibboleth2fa;
    var node = Y.Node.create('<span>' + strings.fulltitle + '</span>');

    return node;
};

/**
 * Called whenever M.core_availability.form.update() is called - this is used to
 * save the value from the form into the hidden availability data.
 *
 * @param {Object} value
 * @param {Object} node
 */
M.availability_shibboleth2fa.form.fillValue = function(value, node) {
    // Do nothing.
};

M.availability_shibboleth2fa.form.focusAfterAdd = function(node) {
    // Do nothing.
};


}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
