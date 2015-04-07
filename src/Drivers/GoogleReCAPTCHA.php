<?php
namespace Bread\CAPTCHA\Drivers;

use Bread\CAPTCHA\Interfaces\Driver;
use Bread\Configuration\Manager as Configuration;
use Bread\Promises\Deferred;
use \ReCaptcha;

class GoogleReCAPTCHA implements Driver
{
    protected $secret;

    public function __construct($domain)
    {
        $this->secret =Configuration::get(__CLASS__, 'secret', $domain);
    }

    public function validateToken($token, $challenge = null, $server = null)
    {
        $deferred = new Deferred();
        $recaptcha = new ReCaptcha\ReCaptcha($this->secret);
        $result = $recaptcha->verify($token, $server);
        if ($result->isSuccess()) {
            return $deferred->resolve();
        } else {
            return $deferred->reject($result->getErrorCodes());
        }
    }
}