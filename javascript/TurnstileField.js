var _turnstileFields=_turnstileFields || [];
var _turnstileValidationExemptActions = _turnstileValidationExemptActions || [];

function turnstileFieldRender() {
    var render = function(field) {
        var options = {
            'sitekey': field.getAttribute('data-sitekey'),
            'theme': field.getAttribute('data-theme'),
        };

        if (field.getAttribute('data-callback')) {
            options.callback = field.getAttribute('data-callback');
        }

        var widget_id = turnstile.render(field, options);
        field.setAttribute("data-widgetid", widget_id);
    }

    for (var i = 0; i < _turnstileFields.length; i++) {
        render(document.getElementById('Turnstile-' + _turnstileFields[i]));
    }
}
