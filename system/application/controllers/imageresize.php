<?php

class Imageresize extends Controller {
  private static function _fpathConcat($dir, $filename) {
    $RELATIVE = 0;
    $ABSOLUTE = 1;

    $dir      = trim($dir);
    $filename = basename($filename);

    if (strlen($dir) == 0) {
      return $filename;
    }

    $pathtype = $dir[0] == DIRECTORY_SEPARATOR ? $ABSOLUTE : $RELATIVE;
    if ($pathtype == $RELATIVE) {
      return
        $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR
      . $dir                      . DIRECTORY_SEPARATOR
      . $filename;
    }
    return $dir . DIRECTORY_SEPARATOR . $filename;
  }

  private function _cachedfnameForImageWxH($cachedir, $filename, $width, $height) {
    $dir_for_res = "${width}x${height}";
    $cachedir   .= DIRECTORY_SEPARATOR . $dir_for_res;
    return $this->_fpathConcat($cachedir, $filename);
  }

  private function _parseWxHStr($WxHStr) {
    $matches = NULL;
    if(!preg_match("/(\d+)x(\d+)/", $WxHStr, $matches)) {
      show_error("bad width or/and height \"$WxHStr\"", 400);
      return NULL;
    }

    $width  = (int)$matches[1];
    $height = (int)$matches[2];

    return array($width, $height);
  }

  private static function _prepareCacheForFile($filename) {
    $dir = dirname($filename);
    $dir_exists = file_exists($dir) && is_dir($dir);

    if (!$dir_exists && !mkdir($dir, 0755, TRUE)) {
      $msg = "can't prepare cache direcotory \"$dir\"";
      log_message('error', $msg);
      show_error($msg);
    }
  }

  private static function _checkFilesPermissions($test_for_read, $test_for_write) {
    if (isset($test_for_read)) {
      if (!is_readable($test_for_read)) {
        $msg = "can't open \"$test_for_read\" for read";
        log_message('warning', $msg);
        show_error($msg);
      }
    }

    if (isset($test_for_write)) {
      if (!is_writable($test_for_write)) {
        $msg = "can't open \"$test_for_write\" for write";
        log_message('warning', $msg);
        show_error($msg);
      }
    }
  }

  public function index() {
    echo 'index';
  }

  public function resize($WxH, $filename) {
    $WxH    = $this->_parseWxHStr($WxH);
    $width  = $WxH[0];
    $height = $WxH[1];

    $this->load->library('ImageResizer');
    $this->config->load('imageresizer', TRUE);

    $srcdir   = $this->config->item('srcdir',   'imageresizer');
    $cachedir = $this->config->item('cachedir', 'imageresizer');

    $src_filename     = self::_fpathConcat($srcdir, $filename);
    $resized_filename =
      $this->_cachedfnameForImageWxH($cachedir, $filename, $width, $height);

    $this->_checkFilesPermissions($src_filename, NULL);
    $this->_prepareCacheForFile($resized_filename);

    $resizer = $this->imageresizer;
    $resizer->setWidth($width);
    $resizer->setHeight($height);
    $resizer->setSourceFilename($src_filename);
    $resizer->setTargetFilename($resized_filename);

    $resizer->process();

    //TODO: impelment rest of code.
    var_dump($src_filename);
    var_dump($resized_filename);
  }
}

?>
