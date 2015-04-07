<?php
namespace Bread\CAPTCHA;

use Bread\Promises\When;
use Bread\REST;

class Controller extends REST\Controller
{

    public function getHTML()
    {
        return Manager::driver(Model::class)->getHTML()->then(function ($model) {
            return parent::get($model, array('challenge', 'data', 'expire'));
        });
    }

    public function controlledResource(array $parameters = array())
    {
        return When::resolve(array());
    }
}