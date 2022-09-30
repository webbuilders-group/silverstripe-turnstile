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
    ->Fields()
      ->fieldByName('Captcha')
          ->setTitle('Spam protection')
          ->setDescription('Your description here');
```


## Adding Custom Attributes
Turnstile has a [few other options](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/#configurations) that this module does not out of the box provide hooks for setting, however you can set them your self using `setAttribute` for example:

```php
$form->enableSpamProtection()
    ->Fields()
      ->fieldByName('Captcha')
          ->setAttribute('data-action', 'action')
          ->setAttribute('data-cdata', 'action')
          ->setAttribute('data-callback', 'yourChallengeJSCallback')
          ->setAttribute('data-expired-callback', 'yourExpiredJSCallback')
          ->setAttribute('data-error-callback', 'youErrorJSCallback')
          ->setAttribute('data-tabindex', 0);
```
