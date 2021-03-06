<?php

class MalformedParamError extends Exception {}

class FileSystemError     extends Exception       {}
class FSAccessError       extends FileSystemError {}
class DirectoryCreatError extends FSAccessError   {}
class FewPermissionError  extends FSAccessError   {}

class FSObjectExistanceError extends FileSystemError        {}
class FileNotExistsError     extends FSObjectExistanceError {}

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
      $msg = "bad width or/and height \"$WxHStr\"";
      throw new MalformedParamError($msg, 400);
    }

    $width  = (int)$matches[1];
    $height = (int)$matches[2];

    return array($width, $height);
  }

  private static function _prepareCacheForFile($filename) {
    $dir = dirname($filename);
    $dir_exists = file_exists($dir) && is_dir($dir);

    if (!$dir_exists && !mkdir($dir, 0755, TRUE)) {
      $msg = "can't create cache direcotory \"$dir\"";
      throw new DirectoryCreatError($msg);
    }
  }

  private static function _assertExistanceAndAccessibility($filepath) {
    if (!file_exists($filepath)) {
      $msg = "file \"${filepath}\" not found";
      throw new FileNotExistsError($msg);
    }

    if (!is_readable($filepath)) {
      $msg = "can't open \"$filepath\" for read";
      throw new FewPermissionError($msg);
    }
  }

  private function _doResizing($src, $dst, $w, $h) {
    $this->load->library('ImageResizer');

    $resizer = $this->imageresizer;
    $resizer->setWidth($w);
    $resizer->setHeight($h);
    $resizer->setSourceFilename($src);
    $resizer->setTargetFilename($dst);

    $resizer->process();
  }

  private function _redirectToCachedImage($filename, $W, $H) {
    $this->load->helper('url');

    $cachedir    = $this->config->item('cachedir', 'imageresizer');
    $server_name = $this->input->server("SERVER_NAME");

    $url = "http://${server_name}/${cachedir}/${W}x${H}/${filename}";
    redirect($url);
  }

  private function _resizeImpl($WxH, $filename) {
    $WxH    = $this->_parseWxHStr($WxH);
    $width  = $WxH[0];
    $height = $WxH[1];

    $this->config->load('imageresizer', TRUE);

    $srcdir   = $this->config->item('srcdir',   'imageresizer');
    $cachedir = $this->config->item('cachedir', 'imageresizer');

    $src_filename     = $this->_fpathConcat($srcdir, $filename);
    $resized_filename =
      $this->_cachedfnameForImageWxH($cachedir, $filename, $width, $height);

    $this->_assertExistanceAndAccessibility($src_filename);
    $this->_prepareCacheForFile($resized_filename);

    $this->_doResizing
    (
      $src_filename,
      $resized_filename,
      $width,
      $height
    );

    $this->_redirectToCachedImage($filename, $width, $height);
  }

  private static function _logError($exception) {
    log_message('error', $exception->getMessage());
  }

  public function resize($WxH, $filename) {
    try {
      try {
        return $this->_resizeImpl($WxH, $filename);
      } catch (Exception $e) {
        $this->_logError($e);
        throw $e;
      }
    } catch (MalformedParamError $e) {
      show_error($e->getMessage(), 400);
    } catch (FSObjectExistanceError $e) {
      show_error($e->getMessage(), 404);
    } catch (FSAccessError $e) {
      show_error($e->getMessage(), 403);
    } catch (Exception $e) {
      show_error($e->getMessage());
    }
  }
}

?>
