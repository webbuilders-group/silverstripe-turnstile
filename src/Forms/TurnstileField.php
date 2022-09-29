<?php
namespace WebbuildersGroup\Turnstile\Forms;

use Psr\Log\LoggerInterface;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FormField;
use SilverStripe\i18n\i18n;
use SilverStripe\View\Requirements;
use Locale;

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
     * @var string
     * @default light
     */
    private static $default_theme = 'light';

    /**
     * Whether form submit events are handled directly by this module.
     * If false, a function is provided that can be called by user code submit handlers.
     * @var bool
     * @default true
     */
    private static $default_handle_submit = true;

    /**
     * The verification response
     * @var array
     */
    protected $verifyResponse;

    private $_theme;
    private $handleSubmitEvents;

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
        $this->handleSubmitEvents = $this->config()->default_handle_submit;
    }

    /**
     * Adds in the requirements for the field
     * @param array $properties Array of properties for the form element (not used)
     * @return string Rendered field template
     */
    public function Field($properties = [])
    {
        $siteKey = $this->getSiteKey();
        $secretKey = Injector::inst()->convertServiceProperty($this->config()->secret_key);

        if (empty($siteKey) || empty($secretKey)) {
            user_error('You must configure ' . TurnstileField::class . '.site_key and ' . TurnstileField::class . '.secret_key, you can retrieve these at https://google.com/recaptcha', E_USER_ERROR);
        }

        $this->configureRequirements();

        return parent::Field($properties);
    }

    /**
     * Configure any javascript and css requirements
     */
    protected function configureRequirements()
    {
        Requirements::customScript(
            "(function() {\n" .
                "var cf = document.createElement('script'); cf.type = 'text/javascript'; cf.async = true; cf.defer = true;\n" .
                "cf.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?hl=" .
                Locale::getPrimaryLanguage(i18n::get_locale()) .
                "&onload=turnstileFieldRender';\n" .
                "var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(cf, s);\n" .
            "})();\n",
            'TurnstileField-lib'
        );

        if ($this->getHandleSubmitEvents()) {
            $exemptActionsString = implode("' , '", $this->getForm()->getValidationExemptActions());
            Requirements::javascript('webbuilders-group/silverstripe-turnstile: javascript/TurnstileField.js');
            Requirements::customScript(
                "var _turnstileFields = _turnstileFields || [];_turnstileFields.push('" . $this->ID() . "');" .
                "var _turnstileValidationExemptActions = _turnstileValidationExemptActions || [];" .
                "_turnstileValidationExemptActions.push('" . $exemptActionsString . "');",
                "TurnstileField-" . $this->ID()
            );
        } else {
            Requirements::customScript(
                "var _turnstileFields = _turnstileFields || [];_turnstileFields.push('" . $this->ID() . "');",
                "TurnstileField-" . $this->ID()
            );
            Requirements::javascript('webbuilders-group/silverstripe-turnstile: javascript/TurnstileField_noHandler.js');
        }
    }

    /**
     * Validates the captcha against the Recaptcha API
     * @param Validator $validator Validator to send errors to
     * @return bool Returns boolean true if valid false if not
     */
    public function validate($validator)
    {
        $request = Controller::curr()->getRequest();
        $turnstileResponse = $request->requestVar('cf-turnstile-response');

        if (!isset($turnstileResponse)) {
            $validator->validationError($this->name, _t(TurnstileField::class . '.NOSCRIPT', '_"You must enable JavaScript to submit this form'), 'validation');
            return false;
        }

        if (!function_exists('curl_init')) {
            user_error('You must enable php-curl to use this field', E_USER_ERROR);
            return false;
        }

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

        if (is_array($response)) {
            $this->verifyResponse = $response;

            if (array_key_exists('success', $response) && $response['success'] == false) {
                $validator->validationError($this->name, _t(TurnstileField::class . '.VALIDATE_ERROR', '_Captcha could not be validated'), 'validation');
                return false;
            }
        } else {
            $validator->validationError($this->name, _t(TurnstileField::class . '.VALIDATE_ERROR', '_Captcha could not be validated'), 'validation');
            $logger = Injector::inst()->get(LoggerInterface::class);
            $logger->error(
                'Turnstile validation failed as request was not successful.'
            );
            return false;
        }


        return true;
    }

    /**
     * Sets whether form submit events are handled directly by this module.
     * @param bool $value
     * @return TurnstileField
     */
    public function setHandleSubmitEvents(bool $value)
    {
        $this->handleSubmitEvents = $value;
        return $this;
    }

    /**
     * Get whether form submit events are handled directly by this module.
     * @return bool
     */
    public function getHandleSubmitEvents(): bool
    {
        return $this->handleSubmitEvents;
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

    /**
     * Gets the theme for this captcha
     * @return string
     */
    public function getTheme()
    {
        return $this->_theme;
    }

    /**
     * Gets the site key configured via TurnstileField.site_key this is used in the template
     * @return string
     */
    public function getSiteKey()
    {
        return ($this->_sitekey ? $this->_sitekey : Injector::inst()->convertServiceProperty($this->config()->site_key));
    }

    /**
     * Gets the form's id
     * @return string
     */
    public function getFormID()
    {
        return ($this->form ? $this->getTemplateHelper()->generateFormID($this->form) : null);
    }

    /**
     * @return array
     */
    public function getVerifyResponse()
    {
        return $this->verifyResponse;
    }
}
