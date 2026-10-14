<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
* File: SimpleImage.php
* Author: Simon Jarvis
* Modified by: Miguel Fermin
* Based in: http://www.white-hat-web-design.co.uk/articles/php-image-resizing.php
*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details:
* http://www.gnu.org/licenses/gpl.html
* https://gist.github.com/908143
*/

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use function is_null;

class ImageResample
{
  private $image;
  private $image_type;

  /**
   * Constructor method.
   *
   * @param string|null $filename The name of the file to load. If null, no file will be loaded.
   * @return void
   */
  public function __construct($filename = null)
  {
    if (!empty($filename)) {
      $this->load($filename);
    }
  }

  /**
   * Loads an image file and initializes the image resource based on its file type.
   *
   * @param string $filename The path to the image file to be loaded.
   * @return void
   */
  public function load(string $filename)
  {
    $image_info = getimagesize($filename);
    $this->image_type = $image_info[2];

    if ($this->image_type == IMAGETYPE_JPEG) {
      $this->image = imagecreatefromjpeg($filename);
    } elseif ($this->image_type == IMAGETYPE_GIF) {
      $this->image = imagecreatefromgif($filename);
    } elseif ($this->image_type == IMAGETYPE_PNG) {
      $this->image = imagecreatefrompng($filename);
    } elseif ($this->image_type == IMAGETYPE_WEBP) {
      $this->image = imagecreatefromwebp($filename);
    } else {
      if (isset($_GET['ProductsAttributes'])) {
        CLICSHOPPING::redirect(null, 'A&Catalog\ProductsAttributes&ProductsAttributes&error=fileNotSupported');
      }

      if (isset($_GET['Products'])) {
        CLICSHOPPING::redirect(null, 'A&Catalog\Products&Products&error=fileNotSupported');
      }

      if (isset($_GET['Manufacturers'])) {
        CLICSHOPPING::redirect(null, 'A&Catalog\Products&Manufacturers&error=fileNotSupported');
      }

      if (isset($_GET['Suppliers'])) {
        CLICSHOPPING::redirect(null, 'A&Catalog\Products&Suppliers&error=fileNotSupported');
      }
    }
  }

  /**
   * Saves the current image resource to a file with the specified format, compression, and permissions.
   *
   * @param string $filename The name of the file where the image will be saved.
   * @param string $ext The image type/format to use for saving, such as IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG, or IMAGETYPE_WEBP. Defaults to IMAGETYPE_WEBP.
   * @param int $compression The level of compression to apply when saving the image, where applicable. Defaults to 80.
   * @param int|null $permissions Optional file permissions to set for the saved file. Defaults to null (no permissions change).
   * @return void
   */
  public function save(string $filename, string $ext = IMAGETYPE_WEBP, int $compression = 80, mixed $permissions = null)
  {
    if (\defined('CONFIGURATION_CONVERT_IMAGE') && CONFIGURATION_CONVERT_IMAGE == 'true') {
      imagewebp($this->image, $filename, $compression);
    } else {
      if ($ext == IMAGETYPE_JPEG) {
        imagejpeg($this->image, $filename, $compression);
      } elseif ($ext == IMAGETYPE_GIF) {
        imagegif($this->image, $filename);
      } elseif ($ext == IMAGETYPE_PNG) {
        imagepng($this->image, $filename);
      } elseif ($ext == IMAGETYPE_WEBP) {
        imagewebp($this->image, $filename, $compression);
      }
    }

    if (!is_null($permissions)) {
      chmod($filename, $permissions);
    }
  }

  /**
   * Outputs the image to the browser in the specified format with optional quality settings.
   *
   * @param string $ext The image type to output. Accepts IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_PNG, or IMAGETYPE_WEBP.
   * @param int $quality The quality of the output image. Only applicable for IMAGETYPE_JPEG. Default is 80.
   * @return void
   */
  public function output(string $ext = IMAGETYPE_JPEG, int $quality = 80)
  {
    if ($ext == IMAGETYPE_JPEG) {
      header("Content-type: image/jpeg");
      imagejpeg($this->image, null, $quality);
    } elseif ($ext == IMAGETYPE_GIF) {
      header("Content-type: image/gif");
      imagegif($this->image);
    } elseif ($ext == IMAGETYPE_PNG) {
      header("Content-type: image/png");
      imagepng($this->image);
    } elseif ($ext == IMAGETYPE_WEBP) {
      header("Content-type: image/webp");
      imagepng($this->image);
    }
  }

  /**
   * Retrieves the width of the current image.
   *
   * @return int The width of the image in pixels.
   */
  public function getWidth()
  {
    return imagesx($this->image);
  }

  /**
   * Retrieves the height of the currently loaded image.
   *
   * @return int The height of the image in pixels.
   */
  public function getHeight()
  {
    return imagesy($this->image);
  }

  /**
   * Resizes the current image to the specified height while maintaining its aspect ratio.
   *
   * @param int $height The new height to resize the image to.
   * @return void
   */
  public function resizeToHeight(int $height)
  {
    $ratio = $height / $this->getHeight();
    $width = round($this->getWidth() * $ratio);

    $this->resize($width, $height);
  }

  /**
   * Resizes the image to the specified width while maintaining the aspect ratio.
   *
   * @param int $width The desired width to resize the image to.
   * @return void
   */
  public function resizeToWidth(int $width)
  {
    $ratio = $width / $this->getWidth();
    $height = round($this->getHeight() * $ratio);

    $this->resize($width, $height);
  }

  /**
   * Resizes the image to a square format with the given size, maintaining the aspect ratio.
   *
   * @param int $size The length of the square's side in pixels.
   * @return void
   */
  public function square(int $size)
  {
    $new_image = imagecreatetruecolor($size, $size);

    if ($this->getWidth() > $this->getHeight()) {
      $this->resizeToHeight($size);

      imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
      imagealphablending($new_image, false);
      imagesavealpha($new_image, true);
      imagecopy($new_image, $this->image, 0, 0, ($this->getWidth() - $size) / 2, 0, $size, $size);
    } else {
      $this->resizeToWidth($size);

      imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
      imagealphablending($new_image, false);
      imagesavealpha($new_image, true);
      imagecopy($new_image, $this->image, 0, 0, 0, ($this->getHeight() - $size) / 2, $size, $size);
    }

    $this->image = $new_image;
  }

  /**
   * Scales the dimensions of the image proportionally based on the specified scale percentage.
   *
   * @param int $scale The scale percentage to resize the image. Must be a value greater than 0.
   * @return void
   */
  public function scale(int $scale)
  {
    $width = $this->getWidth() * $scale / 100;
    $height = $this->getHeight() * $scale / 100;
    $this->resize($width, $height);
  }

  /**
   * Resizes the current image to the specified width and height.
   *
   * @param int $width The desired width of the resized image.
   * @param int $height The desired height of the resized image.
   * @return void
   */
  public function resize(int $width, int $height)
  {
    $new_image = imagecreatetruecolor($width, $height);

    imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
    imagealphablending($new_image, false);
    imagesavealpha($new_image, true);

    imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());

    $this->image = $new_image;
  }

  /**
   * Crops a portion of the image to the specified dimensions and position.
   *
   * @param int $x The x-coordinate of the top-left corner of the cropping region.
   * @param int $y The y-coordinate of the top-left corner of the cropping region.
   * @param int $width The width of the cropping region.
   * @param int $height The height of the cropping region.
   * @return void
   */
  public function cut(int $x, int $y, int $width, int $height)
  {
    $new_image = imagecreatetruecolor($width, $height);

    imagecolortransparent($new_image, imagecolorallocate($new_image, 0, 0, 0));
    imagealphablending($new_image, false);
    imagesavealpha($new_image, true);

    imagecopy($new_image, $this->image, 0, 0, $x, $y, $width, $height);

    $this->image = $new_image;
  }

  /**
   * Resizes the image to fit within the specified dimensions while maintaining aspect ratio.
   *
   * @param int $width The maximum width the image can have after resizing.
   * @param int|null $height The maximum height the image can have after resizing. Defaults to the provided width if null.
   * @return void
   */
  public function maxarea(int $width,  int|null $height = null)
  {
    $height = $height ? $height : $width;

    if ($this->getWidth() > $width) {
      $this->resizeToWidth($width);
    }
    if ($this->getHeight() > $height) {
      $this->resizeToheight($height);
    }
  }

  /**
   * Ensures the image has a minimum width and height by resizing if necessary.
   *
   * @param int $width The minimum width the image should have.
   * @param int|null $height The minimum height the image should have. If null, the height will be set equal to the width.
   * @return void
   */
  public function minarea(int $width,  int|null $height = null)
  {
    $height = $height ?: $width;

    if ($this->getWidth() < $width) {
      $this->resizeToWidth($width);
    }
    if ($this->getHeight() < $height) {
      $this->resizeToheight($height);
    }
  }

  /**
   * Cuts a portion of the image from the center with the specified width and height.
   *
   * @param int $width The width of the portion to cut.
   * @param int $height The height of the portion to cut.
   * @return mixed The resulting image after cutting the specified portion.
   */
  public function cutFromCenter(int $width, int $height)
  {

    if ($width < $this->getWidth() && $width > $height) {
      $this->resizeToWidth($width);
    }
    if ($height < $this->getHeight() && $width < $height) {
      $this->resizeToHeight($height);
    }

    $x = ($this->getWidth() / 2) - ($width / 2);
    $y = ($this->getHeight() / 2) - ($height / 2);

    return $this->cut($x, $y, $width, $height);
  }

  /**
   * Resizes the image to fit within the specified dimensions while preserving the aspect ratio
   * and fills the remaining area with a specified background color.
   *
   * @param int $width The desired width for the final image.
   * @param int $height The desired height for the final image.
   * @param int $red The red component (0-255) of the background color. Default is 0.
   * @param int $green The green component (0-255) of the background color. Default is 0.
   * @param int $blue The blue component (0-255) of the background color. Default is 0.
   * @return void
   */
  public function maxareafill(int $width, int $height, int $red = 0, int $green = 0, int $blue = 0)
  {
    $this->maxarea($width, $height);
    $new_image = imagecreatetruecolor($width, $height);
    $color_fill = imagecolorallocate($new_image, $red, $green, $blue);
    imagefill($new_image, 0, 0, $color_fill);

    imagecopyresampled($new_image,
      $this->image,
      floor(($width - $this->getWidth()) / 2),
      floor(($height - $this->getHeight()) / 2),
      0, 0,
      $this->getWidth(),
      $this->getHeight(),
      $this->getWidth(),
      $this->getHeight()
    );

    $this->image = $new_image;
  }
}




/* usage
The first example below will load a file named picture.jpg resize it to 250 pixels wide and 400 pixels high and resave it as picture2.jpg
   $image = new SimpleImage();
   $image->load('picture.jpg');
   $image->resize(250,400);
   $image->save('picture2.jpg');

If you want to resize to a specifed width but keep the dimensions ratio the same then the script can work out the required height for you, just use the resizeToWidth function.
   $image = new SimpleImage();
   $image->load('picture.jpg');
   $image->resizeToWidth(250);
   $image->save('picture2.jpg');

You may wish to scale an image to a specified percentage like the following which will resize the image to 50% of its original width and height
   $image = new SimpleImage();
   $image->load('picture.jpg');
   $image->scale(50);
   $image->save('picture2.jpg');

You can of course do more than one thing at once. The following example will create two new images with heights of 200 pixels and 500 pixels
   $image = new SimpleImage();
   $image->load('picture.jpg');
   $image->resizeToHeight(500);
   $image->save('picture2.jpg');
   $image->resizeToHeight(200);
   $image->save('picture3.jpg');

// Resize the canvas and fill the empty space with a color of your choice
  $image->maxareafill(600,400, 32, 39, 240);
  $image->save('lemon_filled.jpg');

//  The output function lets you output the image straight to the browser without having to save the file. Its useful for on the fly thumbnail generation


<?php
   if( isset($_POST['submit'])) {
      include_once('SimpleImage.php');
      $image = new SimpleImage();
      $image->load($_FILES['uploaded_image']['tmp_name']);
      $image->resizeToWidth(150);
      $image->output();
   } else {
?>

   <form action="upload.php" method="post" enctype="multipart/form-data">
      <input type="file" name="uploaded_image" />
      <input type="submit" name="submit" value="Upload" />
   </form>

<?php
   }
?>

*/

