<?php

class Imagecache extends Controller {
  private function _isURLToImage($methodname) {
    return preg_match("/\d+x\d+/", $methodname);
  }

  public function index() {
    echo 'index';
  }

  public function resize($WxH, $filename) {
    echo $WxH, $filename;
    $width  = 0;
    $height = 0;
  }
}

?>
