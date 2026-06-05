<?php

namespace WebbuildersGroup\Turnstile\Forms;

use Psr\Log\LoggerInterface;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use InvalidArgumentException;

class TurnstileField extends FormField
{
    /**
     * Recaptcha Site Key
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.site_key
     */
    private static $site_key;

    /**
     * Recaptcha Secret Key
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.secret_key
     */
    private static $secret_key;

    private static $disable_js;

    /**
     * CURL Proxy Server location
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.proxy_server
     * @var string
     */
    private static $proxy_server;

    /**
     * CURL Proxy authentication
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.proxy_auth
     * @var string
     */
    private static $proxy_auth;

    /**
     * CURL Proxy port
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.proxy_port
     * @var int
     */
    private static $proxy_port;

    /**
     * Verify SSL Certificates
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.verify_ssl
     * @var bool
     * @default true
     */
    private static $verify_ssl = true;

    /**
     * Captcha theme, currently options are light and dark
     * @config WebbuildersGroup\Turnstile\Forms\TurnstileField.default_theme
     * @var string
     * @default light
     */
    private static $default_theme = 'light';

    /**
     * Onload callback to be called when the JS for Turnstile is loaded
     * @var string
     */
    private static $js_onload_callback = null;

    /**
     * The verification response
     * @var array
     */
    protected $verifyResponse;

    private $_theme;


    /**
     * Creates a new Recaptcha 2 field.
     * @param string $name The internal field name, passed to forms.
     * @param string $title The human-readable field label.
     * @param mixed $value The value of the field (unused)
     */
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);

        $this->title = $title;
        $this->_theme = $this->config()->default_theme;
    }

    /**
     * Adds in the requirements for the field
     * @param array $properties Array of properties for the form element (not used)
     * @return string Rendered field template
     */
    public function Field($properties = [])
    {
        $siteKey = Injector::inst()->convertServiceProperty($this->config()->site_key);
        $secretKey = Injector::inst()->convertServiceProperty($this->config()->secret_key);

        if (empty($siteKey) || empty($secretKey)) {
            throw new InvalidArgumentException(
                'You must configure ' . TurnstileField::class . '.site_key and '
                . TurnstileField::class . '.secret_key, you can retrieve these at https://dash.cloudflare.com/?to=/:account/turnstile',
            );
        }


        if (!$this->config()->disable_js) {
            Requirements::javascript(
                'https://challenges.cloudflare.com/turnstile/v0/api.js?'
                    . ($this->config()->js_onload_callback ? 'onload=' . $this->config()->js_onload_callback : ''),
                [
                'async' => true,
                'defer' => true,
            ]
            );
        }

        return parent::Field($properties);
    }

    /**
     * Gets the attributes with data-sitekey and data-theme added as attributes
     * @return array
     */
    public function getAttributes()
    {
        return array_merge(
            parent::getAttributes(),
            [
                'class' => ($this->config()->disable_js || $this->config()->js_onload_callback) ? 'js-turnstile' : 'cf-turnstile',
                'data-sitekey'  => Injector::inst()->convertServiceProperty($this->config()->site_key),
                'data-theme' => $this->_theme,
            ]
        );
    }

    /**
     * Gets the verifiy response from cloudflare's api
     * @return array
     */
    protected function getVerifyResponse()
    {
        if ($this->verifyResponse) {
            return $this->verifyResponse;
        }

        $request = Controller::curr()->getRequest();
        $turnstileResponse = $request->requestVar('cf-turnstile-response');

        $secret_key = Injector::inst()->convertServiceProperty($this->config()->secret_key);
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $ch = curl_init($url);

        $proxy_server = Injector::inst()->convertServiceProperty($this->config()->proxy_server);
        if (!empty($proxy_server)) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_server);

            $proxy_auth = Injector::inst()->convertServiceProperty($this->config()->proxy_auth);
            if (!empty($proxy_auth)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_auth);
            }

            $proxy_port = Injector::inst()->convertServiceProperty($this->config()->proxy_port);
            if (!empty($proxy_port)) {
                curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_port);
            }
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->config()->verify_ssl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            http_build_query([
                'secret' => $secret_key,
                'response' => $turnstileResponse,
                'remoteip' => $request->getIP(),
            ])
        );
        curl_setopt($ch, CURLOPT_USERAGENT, 'Silverstripe ' . LeftAndMain::singleton()->getVersionProvider()->getVersion());

        $response = json_decode(curl_exec($ch), true);
        $this->verifyResponse = $response;

        return $this->verifyResponse;
    }
    /**
     * Validates the captcha against the Recaptcha API
     * @return ValidationResult Returns boolean result and optional error messages
     */
    public function validate(): ValidationResult
    {
        $request = Controller::curr()->getRequest();
        $turnstileResponse = $request->requestVar('cf-turnstile-response');
        $result = new ValidationResult();

        if (!isset($turnstileResponse)) {
            $result->addError(
                _t(
                    TurnstileField::class . '.NOSCRIPT',
                    '_"You must enable JavaScript to submit this form'
                ),
                ValidationResult::TYPE_ERROR,
            );

            return $result;
        }


        $error = _t(TurnstileField::class . '.VALIDATE_ERROR', '_Captcha could not be validated');
        $response = $this->getVerifyResponse();

        if (is_array($response)) {
            if (!array_key_exists('success', $response) || $response['success'] == false) {
                if (isset($response['error-codes']) && is_array($response['error-codes'])) {
                    $error .= ' '.implode(' ', $response['error-codes']);
                }

                $result->addError(
                    $error,
                    ValidationResult::TYPE_ERROR,
                );

                return $result;
            }
        } else {
            $result->addError(
                $error,
                ValidationResult::TYPE_ERROR,
            );

            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error(
                'Turnstile validation failed as request was not successful.'
            );

            return $result;
        }


        return $result;
    }

    /**
     * Sets the theme for this captcha
     * @param string $value Theme to set it to, currently the api supports light and dark
     * @return TurnstileField
     */
    public function setTheme($value)
    {
        $this->_theme = $value;

        return $this;
    }
}
