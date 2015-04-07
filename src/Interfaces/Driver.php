<?php
namespace Bread\CAPTCHA\Interfaces;

interface Driver
{

    public function validateToken($token, $challenge = null, $server = null);

}