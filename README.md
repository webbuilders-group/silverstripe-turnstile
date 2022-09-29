Turnstile for Silverstripe
=================
Adds a "spam protection" field to SilverStripe userforms using Cloudflare's [Turnstile](https://www.cloudflare.com/lp/turnstile/) service.

## Maintainer Contact
* Ed Chipman ([UndefinedOffset](https://github.com/UndefinedOffset))

## Requirements
* [Silverstripe Framework](https://github.com/silverstripe/silverstripe-framework) 4.0+
* [silverstripe/spamprotection](https://github.com/silverstripe/silverstripe-spamprotection) 3.0+


## Installation
```
composer require webbuilders-group/silverstripe-turnstile
```


## Configuration
There are multiple configuration options for the field, you must set the site_key and the secret_key which you can get from [Cloudflare](https://www.cloudflare.com/lp/turnstile/)/. These configuration options must be added to your site's yaml config typically this is `app/\_config/config.yml`.

```yml
WebbuildersGroup\Turnstile\Forms\TurnstileField:
  site_key: '`TURNSTILE_SITE_KEY`' #Your site key (required)
  secret_key: '`TURNSTILE_SECRET_KEY`' #Your secret key (required)
  verify_ssl: true #Allows you to disable php-curl's SSL peer verification by setting this to false (optional, defaults to true)
  default_theme: "light" #Default theme color (optional, light, dark or auto, defaults to light)
  default_handle_submit: true #Default setting for whether Turnstile should handle form submission. See "Handling form submission" below.
  proxy_server: "`SS_OUTBOUND_PROXY_SERVER`" #Your proxy server address (optional)
  proxy_port: "`SS_OUTBOUND_PROXY_PORT`" #Your proxy server address port (optional)
  proxy_auth: "`SS_OUTBOUND_PROXY_AUTH`" #Your proxy server authentication information (optional)
```

## Adding field labels

If you want to add a field label or help text to the Captcha field you can do so like this:

```php
$form->enableSpamProtection()
    ->fields()
    ->fieldByName('Captcha')
        ->setTitle('Spam protection')
        ->setDescription('Your description here');
```



## Handling form submission
By default, the javascript included with this module will add a submit event handler to your form.

If you need to handle form submissions in a special way (for example to support front-end validation), you can choose to handle form submit events yourself.

This can be configured site-wide using the Config API
```yml
WebbuildersGroup\Turnstile\Forms\TurnstileField:
  default_handle_submit: false
```

Or on a per form basis:
```php
$captchaField = $form->Fields()->fieldByName('Captcha');
$captchaField->setHandleSubmitEvents(false);
```

With this configuration no event handlers will be added by this module to your form. Instead, a
function will be provided called `turnstile_handleCaptcha` which you can call from your code
when you're ready to submit your form. It has the following signature:
```js
function turnstile_handleCaptcha(form, callback)
```
`form` must be the form element, and `callback` should be a function that finally submits the form, though it is optional.

In the simplest case, you can use it like this:
```js
document.addEventListener("DOMContentLoaded", function(event) {
    // where formID is the element ID for your form
    const form = document.getElementById(formID);
    const submitListener = function(event) {
        event.preventDefault();
        let valid = true;
        /* Your validation logic here */
        if (valid) {
            turnstile_handleCaptcha(form, form.submit.bind(form));
        }
    };
    form.addEventListener('submit', submitListener);
});
```
