<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Size {
  private $width;
  private $height;

  public function __construct($width, $height) {
    $this->width  = $width;
    $this->height = $height;
  }

  public function getWidth()  { return $this->width;  }
  public function getHeight() { return $this->height; }
}

class Point {
  private $x;
  private $y;

  public function __construct($x, $y) {
    $this->x = $x;
    $this->y = $y;
  }

  public function getX() { return $this->x; }
  public function getY() { return $this->y; }
}

class Rectangle {
  private $upperLeftPoint; // upper-left point
  private $size;

  public function __construct($upperLeftPoint, $size) {
    $this->upperLeftPoint = $upperLeftPoint;
    $this->size           = $size;
  }

  public function getUpperLeftPoint() { return $this->upperLeftPoint; }
  public function getSize()           { return $this->size; }

  public function getWidth()  { return $this->getSize()->getWidth();  }
  public function getHeight() { return $this->getSize()->getHeight(); }

  public function getRightBottomPoint() {
    $ulp = $this->getUpperLeftPoint();
    return new Point($ulp->getX() + $this->getWidth(), $ulp->getY() + $this->getHeight());
  }

  public function canBePlacedInside($other) {
    return $this->getWidth()  <= $other->getWidth()
        && $this->getHeight() <= $other->getHeight();
  }
}

class ImageResizer {
  private $target_width;
  private $target_height;

  private $source_file;
  private $target_file;

  private $force_overwrite = FALSE;

  public function setWidth($width) {
    $this->target_width = $width;
  }

  public function setHeight($height) {
    $this->target_height = $height;
  }

  public function setSourceFilename($filename) {
    $this->source_file = $filename;
  }

  public function setTargetFilename($filename) {
    $this->target_file = $filename;
  }

  public function setForceResultOverwrite($overwrite) {
    $this->force_overwrite = $overwrite;
  }

  private function _needProcessing() {
    if ($this->force_overwrite) {
      return TRUE;
    }

    if (!file_exists($this->target_file)) {
      return TRUE;
    }

    $srcStat = stat($this->source_file);
    $dstStat = stat($this->target_file);

    $srcMtime = $srcStat['mtime'];
    $dstMtime = $dstStat['mtime'];

    if ($srcMtime > $dstMtime) {
      return TRUE;
    }

    try {
      $dstImage = new Imagick($this->target_file);
      return $dstImage->getImageWidth()  == $this->target_width
          && $dstImage->getImageHeight() == $this->target_height;
    } catch (ImagickException $e) {
      // some bad happens (likely broken image)... we need to overwrite the image
      return TRUE;
    }

    return TRUE; // better to do extrawork than not to do necessary...
  }

  private static function _calcDstWidthAndHeight($srcW, $srcH, $dstW, $dstH) {
    $srcAspect = $srcW / (float)$srcH;
    $dstAspect = $dstW / (float)$dstH;

    if ($srcAspect == $dstAspect) {
      return new Size($srcW, $srcH);
    }

    $targetW = $srcW;
    $targetH = $srcH;

    if ($dstAspect > $srcAspect) {
      // crop vertically
      $targetH = (int)($srcW * ($dstH / (float)$dstW));
    } else if ($dstAspect < $srcAspect) {
      // crop horizontally
      $targetW = (int)($srcH * $dstAspect);
    }

    return new Size($targetW, $targetH);
  }

  private static function _calcCropRectange($srcW, $srcH, $dstW, $dstH) {
    $s = self::_calcDstWidthAndHeight($srcW, $srcH, $dstW, $dstH);

    $x = ($s->getWidth()  == $srcW) ? 0 : ($srcW - $s->getWidth())  / 2;
    $y = ($s->getHeight() == $srcH) ? 0 : ($srcH - $s->getHeight()) / 2;

    $ulp = new Point($x, $y);

    return new Rectangle($ulp, $s);
  }

  private static function _cropImageWithAspectKeeping($image, $dstWidth, $dstHeight) {
    $srcWidth  = $image->getImageWidth();
    $srcHeight = $image->getImageHeight();

    $cropRectangle = self::_calcCropRectange
    (
      $srcWidth,
      $srcHeight,
      $dstWidth,
      $dstHeight
    );

    $needCrop = $cropRectangle->getUpperLeftPoint()->getX() != 0
             || $cropRectangle->getUpperLeftPoint()->getY() != 0
             || $cropRectangle->getWidth()  != $srcWidth
             || $cropRectangle->getHeight() != $srcHeight;
    if ($needCrop) {
      $image->cropImage
      (
        $cropRectangle->getWidth(),
        $cropRectangle->getHeight(),
        $cropRectangle->getUpperLeftPoint()->getX(),
        $cropRectangle->getUpperLeftPoint()->getY()
      );
    }

    return $cropRectangle;
  }

  private static function _upscaleImage($image, $dstWidth, $dstHeight) {
    $image->resizeImage($dstWidth, $dstHeight, Imagick::FILTER_QUADRATIC , 1);
    $image->unsharpMaskImage(0 , 0.5 , 1 , 0.05);
  }

  private static function _downscaleImage($image, $dstWidth, $dstHeight) {
    $image->resizeImage($dstWidth, $dstHeight, Imagick::FILTER_QUADRATIC , 1);
  }

  public function process() {
    assert($this->target_width > 0 && $this->target_height > 0);

    if (!$this->_needProcessing()) {
      return;
    }

    $image = new Imagick($this->source_file);

    $srcWidth  = $image->getImageWidth();
    $srcHeight = $image->getImageHeight();
    $dstWidth  = $this->target_width;
    $dstHeight = $this->target_height;

    $targetRectangle = new Rectangle(new Point(0,0), new Size($dstWidth, $dstHeight));
    $cropRectangle   =
      $this->_cropImageWithAspectKeeping($image, $dstWidth, $dstHeight);

    if ($targetRectangle->canBePlacedInside($cropRectangle)) { // target size is smaller
      $this->_downscaleImage($image, $dstWidth, $dstHeight);
    } else { // target size is bigger
      $this->_upscaleImage($image, $dstWidth, $dstHeight);
    }

    $image->writeImage($this->target_file);
  }
}

/**
 * ============= EXAMPLE OF USAGE ============
 * $x = new ImageResizer();
 * $x->setWidth(800);
 * $x->setHeight(600);
 * $x->setSourceFilename($argv[1]);
 * $x->setTargetFilename("/tmp/res.jpg");
 * $x->process();
 *
 */
?>
