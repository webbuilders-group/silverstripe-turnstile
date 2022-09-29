<?php
namespace WebbuildersGroup\Turnstile\Forms;

use SilverStripe\SpamProtection\SpamProtector;

class TurnstileProtector implements SpamProtector
{
    /**
     * Return the Field that we will use in this protector
     * @return TurnstileField
     */
    public function getFormField($name = "TurnstileField", $title = 'Captcha', $value = null)
    {
        return TurnstileField::create($name, $title);
    }

    /**
     * Not used by Turnstile
     */
    public function setFieldMapping($fieldMapping)
    {
    }
}
