<?php

$this->view->options->disableView = true;

// Verify register page was loaded
if (empty($_SESSION['formToken'])) {
    App::throw404();
}

// Generate image
$backgrounds = glob(dirname(__FILE__) . '/backgrounds/*.png');
shuffle($backgrounds);
$background = imagecreatefrompng($backgrounds[0]);
$image =  imagecreatetruecolor(125, 50);
imagesettile($image, $background);
imagefilledrectangle($image, 0, 0, 125, 50, IMG_COLOR_TILED);

// Apply text
$fontFiles = glob(dirname(__FILE__) . '/fonts/*.ttf');
shuffle($fontFiles);
$colorGrey = imagecolorallocate($image, 85, 85, 85);
imagettftext($image, 16, rand(-5, 5), 10, 33, $colorGrey, $fontFiles[0], $_SESSION['captchaText']);

// Output image
header("Content-Type: image/png");
imagepng($image);

// Release memory and delete temp file
imagedestroy($image);