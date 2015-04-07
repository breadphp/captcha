<?php
namespace Bread\CAPTCHA\Drivers;

use Bread\CAPTCHA\Model;
use Bread\CAPTCHA\Interfaces\Driver;
use Bread\Configuration\Manager as Configuration;
use Exception;
use Bread\Promises\Deferred;
use Bread\Promises\When;
use Bread\Types\DateTime;

class SaivCAPTCHA implements Driver
{

    const SI_CAPTCHA_STRING = 0;

    const SI_CAPTCHA_MATHEMATIC = 1;

    const SI_CAPTCHA_WORDS = 2;

    protected $width = 215;

    protected $height = 80;

    protected $iscale = 8;

    protected $fontRatio = 0.4;

    protected $backgroundColor = 'ece8e8';

    protected $textColor = '424242';

    protected $lineColor = '969292';

    protected $noiseColor = '2b1818';

    protected $perturbation = 1;

    protected $lines = 5;

    protected $noiseLevel = 20;

    public function validateToken($token, $challenge = null, $server = null)
    {
        return Model::first(array('challenge' => $challenge))->then(function($model) use($token) {
            $deferred = new Deferred();
            if ($model->expire < new DateTime()) {
                return $deferred->reject("Captcha is expired");
            }
            if (strtolower($model->code) != strtolower($token)) {
                return $deferred->reject("Wrong captcha");
            }
            $model->expire = new DateTime();
            $model->store();
            return $deferred->resolve();
        });
    }

    public function getHTML()
    {
        $model = new Model();
        ob_start();
        $image = imagecreate($this->width, $this->height);
        $tmpimg = imagecreate($this->width * $this->iscale, $this->height * $this->iscale);
        imagepalettecopy($tmpimg, $image);
        $this->setBackground($image, $this->width, $this->height);
        $this->setBackground($tmpimg, $this->width * $this->iscale, $this->height * $this->iscale);
        $this->drawNoise($tmpimg, $this->width * $this->iscale, $this->height * $this->iscale);
        $this->drawWord($tmpimg, $model->code, $this->width * $this->iscale, $this->height * $this->iscale);
        $this->distortedCopy($image, $tmpimg);
        $this->drawLines($image, $this->width, $this->height);
        imagedestroy($tmpimg);
        imagepng($image);
        $model->data = base64_encode(ob_get_contents());
        ob_end_clean();
        return $model->store();
    }

    protected function setBackground($image, $width, $height)
    {
        $rgb = $this->getRGB($this->backgroundColor);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
    }

    protected function drawNoise($image, $width, $height)
    {
        $rgb = $this->getRGB($this->noiseColor);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        $points = $width * $this->noiseLevel;
        for ($i = 0; $i < $points; ++ $i) {
            $x = mt_rand(1, $width);
            $y = mt_rand(1, $height);
            $size = 2;
            imagefilledarc($image, $x, $y, $size, $size, 0, 360, $color, IMG_ARC_PIE);
        }
    }

    protected function drawWord($image, $code, $width, $height)
    {
        $font = implode(DIRECTORY_SEPARATOR, array(
            __DIR__,
            'SaivCAPTCHA',
            'AHGBold.ttf'
        ));
        $fontSize = $height * $this->fontRatio;
        $rgb = $this->getRGB($this->textColor);
        $bb = imageftbbox($fontSize, 0, $font, $code);
        $tx = $bb[4] - $bb[0];
        $ty = $bb[5] - $bb[1];
        $x = floor($width / 2 - $tx / 2 - $bb[0]);
        $y = round($height / 2 - $ty / 2 - $bb[1]);
        imagettftext($image, $fontSize, 0, $x, $y, imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']), $font, $code);
    }

    protected function distortedCopy($image, $tmpimg)
    {
        $numpoles = 3; // distortion factor
                       // make array of poles AKA attractor points
        for ($i = 0; $i < $numpoles; ++ $i) {
            $px[$i] = mt_rand($this->width * 0.2, $this->width * 0.8);
            $py[$i] = mt_rand($this->height * 0.2, $this->height * 0.8);
            $rad[$i] = mt_rand($this->height * 0.2, $this->height * 0.8);
            $tmp = ((- $this->frand()) * 0.15) - .15;
            $amp[$i] = $this->perturbation * $tmp;
        }

        $bgCol = imagecolorat($tmpimg, 0, 0);
        $width2 = $this->iscale * $this->width;
        $height2 = $this->iscale * $this->height;
        imagepalettecopy($image, $tmpimg);
        for ($ix = 0; $ix < $this->width; ++ $ix) {
            for ($iy = 0; $iy < $this->height; ++ $iy) {
                $x = $ix;
                $y = $iy;
                for ($i = 0; $i < $numpoles; ++ $i) {
                    $dx = $ix - $px[$i];
                    $dy = $iy - $py[$i];
                    if ($dx == 0 && $dy == 0) {
                        continue;
                    }
                    $r = sqrt($dx * $dx + $dy * $dy);
                    if ($r > $rad[$i]) {
                        continue;
                    }
                    $rscale = $amp[$i] * sin(3.14 * $r / $rad[$i]);
                    $x += $dx * $rscale;
                    $y += $dy * $rscale;
                }
                $c = $bgCol;
                $x *= $this->iscale;
                $y *= $this->iscale;
                if ($x >= 0 && $x < $width2 && $y >= 0 && $y < $height2) {
                    $c = imagecolorat($tmpimg, $x, $y);
                }
                if ($c != $bgCol) { // only copy pixels of letters to preserve any background image
                    imagesetpixel($image, $ix, $iy, $c);
                }
            }
        }
    }

    protected function drawLines($image, $width, $height)
    {
        $rgb = $this->getRGB($this->lineColor);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        for ($line = 0; $line < $this->lines; ++ $line) {
            $x = $width * (1 + $line) / ($this->lines + 1);
            $x += (0.5 - $this->frand()) * $width / $this->lines;
            $y = mt_rand($height * 0.1, $height * 0.9);

            $theta = ($this->frand() - 0.5) * M_PI * 0.7;
            $w = $width;
            $len = mt_rand($w * 0.4, $w * 0.7);
            $lwid = mt_rand(0, 2);
            $k = $this->frand() * 0.6 + 0.2;
            $k = $k * $k * 0.5;
            $phi = $this->frand() * 6.28;
            $step = 0.5;
            $dx = $step * cos($theta);
            $dy = $step * sin($theta);
            $n = $len / $step;
            $amp = 1.5 * $this->frand() / ($k + 5.0 / $len);
            $x0 = $x - 0.5 * $len * cos($theta);
            $y0 = $y - 0.5 * $len * sin($theta);
            $ldx = round(- $dy * $lwid);
            $ldy = round($dx * $lwid);
            for ($i = 0; $i < $n; ++ $i) {
                $x = $x0 + $i * $dx + $amp * $dy * sin($k * $i * $step + $phi);
                $y = $y0 + $i * $dy - $amp * $dx * sin($k * $i * $step + $phi);
                imagefilledrectangle($image, $x, $y, $x + $lwid, $y + $lwid, $color);
            }
        }
    }

    protected function output($image)
    {
        imagepng($image);
        imagedestroy($image);
    }

    protected function getRGB($color)
    {
        if (strlen($color) == 3) {
            $red = str_repeat(substr($color, 0, 1), 2);
            $green = str_repeat(substr($color, 1, 1), 2);
            $blue = str_repeat(substr($color, 2, 1), 2);
        } else {
            $red = substr($color, 0, 2);
            $green = substr($color, 2, 2);
            $blue = substr($color, 4, 2);
        }
        return array(
            'r' => hexdec($red),
            'g' => hexdec($green),
            'b' => hexdec($blue)
        );
    }

    protected function frand()
    {
        return 0.0001 * mt_rand(0, 9999);
    }

}