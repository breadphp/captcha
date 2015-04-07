<?php
namespace Bread\CAPTCHA\Drivers;

use Bread\CAPTCHA\Interfaces\Driver;
use Bread\Configuration\Manager as Configuration;
use Bread\Promises\Deferred;
use Bread\Promises\When;

class GoogleReCAPTCHAv1 implements Driver
{

    const API_SERVER = 'http://www.google.com/recaptcha/api';

    const API_SECURE_SERVER = 'https://www.google.com/recaptcha/api';

    const VERIFY_SERVER = 'www.google.com';

    protected $secret;

    public function __construct($domain)
    {
        $this->secret = Configuration::get(__CLASS__, 'secret', $domain);
    }

    public function validateToken($token, $challenge = null, $server = null)
    {
        if (! $this->secret) {
            return When::reject('To use reCAPTCHA you must get an API key');
        }
        if (! $server) {
            return When::reject('For security reasons, you must pass the remote ip to reCAPTCHA');
        }
        if (! $token) {
            return When::reject('Incorrect CAPTCHA');
        }
        return $this->recaptcha_http_post(self::VERIFY_SERVER, "/recaptcha/api/verify", array(
            'privatekey' => $this->secret,
            'remoteip' => $server,
            'challenge' => $challenge,
            'response' => $token
        ));
    }

    protected function recaptcha_http_post($host, $path, $data, $port = 80)
    {
        $deferred = new Deferred();
        $req = $this->recaptcha_qsencode($data);

        $http_request = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($req) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        $response = '';
        if (false == ($fs = @fsockopen($host, $port, $errno, $errstr, 10))) {
            return $deferred->reject('Could not open socket');
        }
        fwrite($fs, $http_request);
        while (! feof($fs))
            $response .= fgets($fs, 1160); // One TCP-IP packet
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);
        $answers = explode("\n", $response[1]);
        if (trim($answers[0]) == 'true') {
            return $deferred->resolve();
        } else {
            return $deferred->reject($answers[1]);
        }
    }

    protected function recaptcha_qsencode($data)
    {
        $req = "";
        foreach ($data as $key => $value)
            $req .= $key . '=' . urlencode(stripslashes($value)) . '&';
            // Cut the last '&'
        $req = substr($req, 0, strlen($req) - 1);
        return $req;
    }
}
