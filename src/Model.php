<?php
namespace Bread\CAPTCHA;

use Bread\Types\DateTime;
use Bread\REST;
use Bread\Configuration\Manager as Configuration;

class Model extends REST\Behaviors\ACO
{
    const EXPIRE = '+1 hour';

    protected $challenge;
    protected $code;
    protected $created;
    protected $expire;
    protected $data;

    public function __construct()
    {
        $this->created = new DateTime();
        $this->expire = new DateTime(static::EXPIRE);
        $apgOptions = Configuration::get(Model::class, "challenge");
        $this->challenge = trim(shell_exec("openssl rand -base64 256 | $apgOptions"));
        $apgOptions = Configuration::get(Model::class, "code");
        $this->code = trim(shell_exec("openssl rand -base64 256 | $apgOptions"));
        parent::__construct();
    }

    public static function validateToken($token, $challenge = null, $server = null)
    {
        return Manager::driver(__CLASS__)->validateToken($token, $challenge, $server);
    }

}

Configuration::defaults('Bread\CAPTCHA\Model', array(
    'keys' => array(
        'challenge'
    ),
    'properties' => array(
        'created' => array(
            'type' => 'Bread\Types\DateTime'
        ),
        'expire' => array(
            'type' => 'Bread\Types\DateTime'
        ),
        'data' => array(
            'type' => 'text'
        )
    )
));