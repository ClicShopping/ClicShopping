<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*******************************************************************************
 * FPDF                                                                         *
 *                                                                              *
 * Version : 1.6                                                                *
 * Date :    2008-08-03                                                         *
 * Auteur :  Olivier PLATHEY                                                    *
 *******************************************************************************/

namespace ClicShopping\Sites\Common;

use function count;
use function defined;
use function is_array;
use function is_string;
use function strlen;
/**
 * Class FPDF
 *
 * Main class for generating PDF documents programmatically.
 * Provides functionalities for setting page formats, margins, orientation,
 * fonts, colors, and other features required for creating PDFs.
 */
class FPDF
{
  public $page;               //current page number
  public $n;                  //current object number
  public $offsets;            //array of object offsets
  public $buffer;             //buffer holding in-memory PDF
  public $pages;              //array containing pages
  public $state;              //current document state
  public $compress;           //compression flag
  public $k;                  //scale factor (number of points in user unit)
  public $DefOrientation;     //default orientation
  public $CurOrientation;     //current orientation
  public $PageFormats;        //available page formats
  public $DefPageFormat;      //default page format
  public $CurPageFormat;      //current page format
  public $PageSizes;          //array storing non-default page sizes
  public $wPt, $hPt;           //dimensions of current page in points
  public $w, $h;               //dimensions of current page in user unit
  public $lMargin;            //left margin
  public $tMargin;            //top margin
  public $rMargin;            //right margin
  public $bMargin;            //page break margin
  public $cMargin;            //cell margin
  public $x, $y;               //current position in user unit
  public $lasth;              //height of last printed cell
  public $LineWidth;          //line width in user unit
  public $includesFonts;          //array of standard font names
  public $fonts;              //array of used fonts
  public $FontFiles;          //array of font files
  public $diffs;              //array of encoding differences
  public $FontFamily;         //current font family
  public $FontStyle;          //current font style
  public $underline;          //underlining flag
  public $CurrentFont;        //current font info
  public $FontSizePt;         //current font size in points
  public $FontSize;           //current font size in user unit
  public $DrawColor;          //commands for drawing color
  public $FillColor;          //commands for filling color
  public $TextColor;          //commands for text color
  public $ColorFlag;          //indicates whether fill and text colors are different
  public $ws;                 //word spacing
  public $images;             //array of used images
  public $PageLinks;          //array of links in pages
  public $links;              //array of internal links
  public $AutoPageBreak;      //automatic page breaking
  public $PageBreakTrigger;   //threshold used to trigger page breaks
  public $InHeader;           //flag set when processing header
  public $InFooter;           //flag set when processing footer
  public $ZoomMode;           //zoom display mode
  public $LayoutMode;         //layout display mode
  public $title;              //title
  public $subject;            //subject
  public $author;             //author
  public $keywords;           //keywords
  public $creator;            //creator
  public $AliasNbPages;       //alias for total number of pages
  public $PDFVersion;         //PDF version number

  /**
   * Constructor for the PDF class.
   *
   * @param string $orientation The orientation of the document ('P' for portrait or 'L' for landscape). Default is 'P'.
   * @param string $unit The unit of measurement for dimensions ('pt', 'mm', 'cm', 'in'). Default is 'mm'.
   * @param mixed $format The page format (e.g., 'A4', 'A3') or an array of dimensions. Default is 'A4'.
   *
   * @return void
   */
  public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4')
  {
    //Some checks
    $this->_dochecks();
    //Initialization of properties
    $this->page = 0;
    $this->n = 2;
    $this->buffer = '';
    $this->pages = array();
    $this->PageSizes = array();
    $this->state = 0;
    $this->fonts = array();
    $this->FontFiles = array();
    $this->diffs = array();
    $this->images = array();
    $this->links = array();
    $this->InHeader = false;
    $this->InFooter = false;
    $this->lasth = 0;
    $this->FontFamily = '';
    $this->FontStyle = '';
    $this->FontSizePt = 12;
    $this->underline = false;
    $this->DrawColor = '0 G';
    $this->FillColor = '0 g';
    $this->TextColor = '0 g';
    $this->ColorFlag = false;
    $this->ws = 0;
//Standard fonts

    $this->includesFonts = array('courier' => 'Courier', 'courierB' => 'Courier-Bold', 'courierI' => 'Courier-Oblique', 'courierBI' => 'Courier-BoldOblique',
      'helvetica' => 'Helvetica', 'helveticaB' => 'Helvetica-Bold', 'helveticaI' => 'Helvetica-Oblique', 'helveticaBI' => 'Helvetica-BoldOblique',
      'times' => 'Times-Roman', 'timesB' => 'Times-Bold', 'timesI' => 'Times-Italic', 'timesBI' => 'Times-BoldItalic',
      'symbol' => 'Symbol', 'zapfdingbats' => 'ZapfDingbats');
    //Scale factor
    if ($unit == 'pt')
      $this->k = 1;
    elseif ($unit == 'mm')
      $this->k = 72 / 25.4;
    elseif ($unit == 'cm')
      $this->k = 72 / 2.54;
    elseif ($unit == 'in')
      $this->k = 72;
    else
      $this->Error('Incorrect unit: ' . $unit);
    //Page format
    $this->PageFormats = array('a3' => array(841.89, 1190.55), 'a4' => array(595.28, 841.89), 'a5' => array(420.94, 595.28),
      'letter' => array(612, 792), 'legal' => array(612, 1008));
    if (is_string($format))
      $format = $this->_getpageformat($format);
    $this->DefPageFormat = $format;
    $this->CurPageFormat = $format;
    //Page orientation
    $orientation = mb_strtolower($orientation);
    if ($orientation == 'p' || $orientation == 'portrait') {
      $this->DefOrientation = 'P';
      $this->w = $this->DefPageFormat[0];
      $this->h = $this->DefPageFormat[1];
    } elseif ($orientation == 'l' || $orientation == 'landscape') {
      $this->DefOrientation = 'L';
      $this->w = $this->DefPageFormat[1];
      $this->h = $this->DefPageFormat[0];
    } else
      $this->Error('Incorrect orientation: ' . $orientation);
    $this->CurOrientation = $this->DefOrientation;
    $this->wPt = $this->w * $this->k;
    $this->hPt = $this->h * $this->k;
    //Page margins (1 cm)
    $margin = 28.35 / $this->k;
    $this->SetMargins($margin, $margin);
    //Interior cell margin (1 mm)
    $this->cMargin = $margin / 10;
    //Line width (0.2 mm)
    $this->LineWidth = .567 / $this->k;
    //Automatic page break
    $this->SetAutoPageBreak(true, 2 * $margin);
    //Full width display mode
    $this->SetDisplayMode('fullwidth');
    //Enable compression
    $this->SetCompression(true);
    //Set default PDF version number
    $this->PDFVersion = '1.3';
  }

  /**
   * Sets the left, top, and right margins for the document.
   *
   * @param float $left The left margin.
   * @param float $top The top margin.
   * @param float|null $right The right margin. If not provided, the left margin value will be used.
   * @return void
   */
  public function SetMargins($left, $top, $right = null)
  {
    //Set left, top and right margins
    $this->lMargin = $left;
    $this->tMargin = $top;
    if ($right === null)
      $right = $left;
    $this->rMargin = $right;
  }

  /**
   * Sets the left margin of the document.
   *
   * @param float $margin The value to set as the left margin.
   * @return void
   */
  public function SetLeftMargin($margin)
  {
    //Set left margin
    $this->lMargin = $margin;
    if ($this->page > 0 && $this->x < $margin)
      $this->x = $margin;
  }

  /**
   * Sets the top margin of a document.
   *
   * @param float $margin The top margin value to set.
   * @return void
   */
  public function SetTopMargin($margin)
  {
    //Set top margin
    $this->tMargin = $margin;
  }

  /**
   * Sets the right margin of the document.
   *
   * @param float $margin The value of the right margin to be set, specified in user units.
   * @return void
   */
  public function SetRightMargin($margin)
  {
    //Set right margin
    $this->rMargin = $margin;
  }

  /**
   * Enables or disables the automatic page break mode and sets the margin at which the page break is triggered.
   *
   * @param bool $auto Indicates whether auto page break mode is enabled (true) or disabled (false).
   * @param float $margin The margin from the bottom of the page at which a page break is triggered. Default is 0.
   * @return void
   */
  public function SetAutoPageBreak($auto, $margin = 0)
  {
    //Set auto page break mode and triggering margin
    $this->AutoPageBreak = $auto;
    $this->bMargin = $margin;
    $this->PageBreakTrigger = $this->h - $margin;
  }

  /**
   * Sets the display mode in the viewer.
   *
   * @param string $zoom Specifies the zoom mode. Possible values are 'fullpage', 'fullwidth', 'real', 'default', or a custom string.
   * @param string $layout Specifies the layout mode. Possible values are 'single', 'continuous', 'two', or 'default'. Defaults to 'continuous'.
   * @return void
   */
  public function SetDisplayMode($zoom, $layout = 'continuous')
  {
    //Set display mode in viewer
    if ($zoom == 'fullpage' || $zoom == 'fullwidth' || $zoom == 'real' || $zoom == 'default' || !is_string($zoom))
      $this->ZoomMode = $zoom;
    else
      $this->Error('Incorrect zoom display mode: ' . $zoom);
    if ($layout == 'single' || $layout == 'continuous' || $layout == 'two' || $layout == 'default')
      $this->LayoutMode = $layout;
    else
      $this->Error('Incorrect layout display mode: ' . $layout);
  }

  /**
   * Sets the page compression setting.
   *
   * @param bool $compress Determines whether page compression is enabled.
   * @return void
   */
  public function SetCompression($compress)
  {
    //Set page compression
    if (function_exists('gzcompress'))
      $this->compress = $compress;
    else
      $this->compress = false;
  }

  /**
   * Sets the title of the document.
   *
   * @param string $title The title to be set for the document.
   * @param bool $isUTF8 Indicates whether the given title is in UTF-8 encoding. Defaults to false.
   * @return void
   */
  public function SetTitle($title, $isUTF8 = false)
  {
    //Title of document
    if ($isUTF8)
      $title = $this->_UTF8toUTF16($title);
    $this->title = $title;
  }

  /**
   * Sets the subject of the document.
   *
   * @param string $subject The subject to be set for the document.
   * @param bool $isUTF8 Indicates whether the subject is UTF-8 encoded. Defaults to false.
   * @return void
   */
  public function SetSubject($subject, $isUTF8 = false)
  {
    //Subject of document
    if ($isUTF8)
      $subject = $this->_UTF8toUTF16($subject);
    $this->subject = $subject;
  }

  /**
   * Sets the author of the document.
   *
   * @param string $author The name of the author.
   * @param bool $isUTF8 Optional. Indicates if the input string is encoded in UTF-8. Defaults to false.
   * @return void
   */
  public function SetAuthor($author, $isUTF8 = false)
  {
    //Author of document
    if ($isUTF8)
      $author = $this->_UTF8toUTF16($author);
    $this->author = $author;
  }

  /**
   * Sets the keywords for the document.
   *
   * @param string $keywords The keywords to be set for the document.
   * @param bool $isUTF8 Indicates whether the provided keywords are in UTF-8 encoding. Defaults to false.
   * @return void
   */
  public function SetKeywords($keywords, $isUTF8 = false)
  {
    //Keywords of document
    if ($isUTF8)
      $keywords = $this->_UTF8toUTF16($keywords);
    $this->keywords = $keywords;
  }

  /**
   * Sets the creator of the document.
   *
   * @param string $creator The name of the creator for the document.
   * @param bool $isUTF8 Determines if the provided $creator string is in UTF-8 format. Defaults to false.
   * @return void
   */
  public function SetCreator($creator, $isUTF8 = false)
  {
    //Creator of document
    if ($isUTF8)
      $creator = $this->_UTF8toUTF16($creator);
    $this->creator = $creator;
  }

  /**
   * Defines an alias for the total number of pages in the document.
   *
   * @param string $alias The alias to be used for the total number of pages. Default is '{nb}'.
   * @return void
   */
  public function AliasNbPages($alias = '{nb}')
  {
    //Define an alias for total number of pages
    $this->AliasNbPages = $alias;
  }

  /**
   * Triggers a fatal error with the provided message and terminates the script execution.
   *
   * @param string $msg The error message to display.
   * @return void
   */
  public function Error($msg)
  {
    //Fatal error
    die('<b>FPDF error:</b> ' . $msg);
  }

  /**
   * Begins the document by setting the initial state.
   *
   * @return void
   */
  public function Open()
  {
    //Begin document
    $this->state = 1;
  }

  /**
   * Closes the document by finalizing its content and structure.
   * This method ensures the document is properly terminated, including adding a page
   * if none exists, generating the footer, and performing any necessary final operations.
   *
   * @return void
   */
  public function Close()
  {
    //Terminate document
    if ($this->state == 3)
      return;
    if ($this->page == 0)
      $this->AddPage();
    //Page footer
    $this->InFooter = true;
    $this->Footer();
    $this->InFooter = false;
    //Close page
    $this->_endpage();
    //Close document
    $this->_enddoc();
  }

  /**
   * Adds a new page to the document.
   *
   * @param string $orientation Orientation of the page ('P' for Portrait, 'L' for Landscape). If left empty, the default orientation is used.
   * @param mixed $format Format of the page (e.g., 'A4', 'Letter'). If left empty, the default format is used.
   * @return void
   */
  public function AddPage($orientation = '', $format = '')
  {
    //Start a new page
    if ($this->state == 0)
      $this->Open();
    $family = $this->FontFamily;
    $style = $this->FontStyle . ($this->underline ? 'U' : '');
    $size = $this->FontSizePt;
    $lw = $this->LineWidth;
    $dc = $this->DrawColor;
    $fc = $this->FillColor;
    $tc = $this->TextColor;
    $cf = $this->ColorFlag;
    if ($this->page > 0) {
      //Page footer
      $this->InFooter = true;
      $this->Footer();
      $this->InFooter = false;
      //Close page
      $this->_endpage();
    }
    //Start new page
    $this->_beginpage($orientation, $format);
    //Set line cap style to square
    $this->_out('2 J');
    //Set line width
    $this->LineWidth = $lw;
    $this->_out(sprintf('%.2F w', $lw * $this->k));
    //Set font
    if ($family)
      $this->SetFont($family, $style, $size);
    //Set colors
    $this->DrawColor = $dc;
    if ($dc != '0 G')
      $this->_out($dc);
    $this->FillColor = $fc;
    if ($fc != '0 g')
      $this->_out($fc);
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
    //Page header
    $this->InHeader = true;
    $this->Header();
    $this->InHeader = false;
    //Restore line width
    if ($this->LineWidth != $lw) {
      $this->LineWidth = $lw;
      $this->_out(sprintf('%.2F w', $lw * $this->k));
    }
    //Restore font
    if ($family)
      $this->SetFont($family, $style, $size);
    //Restore colors
    if ($this->DrawColor != $dc) {
      $this->DrawColor = $dc;
      $this->_out($dc);
    }
    if ($this->FillColor != $fc) {
      $this->FillColor = $fc;
      $this->_out($fc);
    }
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
  }

  /**
   * This method is intended to be implemented in an inherited class
   * to generate a custom header for documents. It is called automatically
   * and should be customized as per requirements.
   *
   * @return void
   */
  public function Header()
  {
    //To be implemented in your own inherited class
  }

  /**
   * Method intended to define the footer content of a document.
   *
   * @return void
   */
  public function Footer()
  {
    //To be implemented in your own inherited class
  }

  /**
   * Retrieves the current page number.
   *
   * @return int The current page number.
   */
  public function PageNo()
  {
    //Get current page number
    return $this->page;
  }

  /**
   * Sets the color for all stroking operations.
   *
   * @param int $r Red component of the color (0-255).
   * @param int|null $g Green component of the color (0-255). If null, a grayscale color will be set.
   * @param int|null $b Blue component of the color (0-255). If null, a grayscale color will be set.
   * @return void
   */
  public function SetDrawColor($r, $g = null, $b = null)
  {
    //Set color for all stroking operations
    if (($r == 0 && $g == 0 && $b == 0) || $g === null)
      $this->DrawColor = sprintf('%.3F G', $r / 255);
    else
      $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
    if ($this->page > 0)
      $this->_out($this->DrawColor);
  }

  /**
   * Sets the fill color for all filling operations.
   *
   * @param int $r The red component of the color (0-255).
   * @param int|null $g The green component of the color (0-255). If null, grayscale mode is used.
   * @param int|null $b The blue component of the color (0-255). If null, grayscale mode is used.
   * @return void
   */
  public function SetFillColor($r, $g = null, $b = null)
  {
    //Set color for all filling operations
    if (($r == 0 && $g == 0 && $b == 0) || $g === null)
      $this->FillColor = sprintf('%.3F g', $r / 255);
    else
      $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    $this->ColorFlag = ($this->FillColor != $this->TextColor);
    if ($this->page > 0)
      $this->_out($this->FillColor);
  }

  /**
   * Sets the color for text to be used in the document.
   *
   * @param int $r The red component of the color, ranging from 0 to 255.
   * @param int|null $g The green component of the color, ranging from 0 to 255. Optional.
   * @param int|null $b The blue component of the color, ranging from 0 to 255. Optional.
   * @return void
   */
  public function SetTextColor($r, $g = null, $b = null)
  {
    //Set color for text
    if (($r == 0 && $g == 0 && $b == 0) || $g === null)
      $this->TextColor = sprintf('%.3F g', $r / 255);
    else
      $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    $this->ColorFlag = ($this->FillColor != $this->TextColor);
  }

  /**
   * Calculates and returns the width of a given string in the current font and size.
   *
   * @param string $s The string for which to calculate the width.
   * @return float The width of the string in the current font and size.
   */
  public function GetStringWidth($s)
  {
    //Get width of a string in the current font
    $s = (string)$s;
    $cw =& $this->CurrentFont['cw'];
    $w = 0;
    $l = strlen($s);
    for ($i = 0; $i < $l; $i++)
      $w += $cw[$s[$i]];
    return $w * $this->FontSize / 1000;
  }

  /**
   * Sets the line width for drawing operations.
   *
   * @param float $width The desired line width.
   * @return void
   */
  public function SetLineWidth($width)
  {
    //Set line width
    $this->LineWidth = $width;
    if ($this->page > 0)
      $this->_out(sprintf('%.2F w', $width * $this->k));
  }

  /**
   * Draws a line between two points.
   *
   * @param float $x1 The x-coordinate of the starting point.
   * @param float $y1 The y-coordinate of the starting point.
   * @param float $x2 The x-coordinate of the ending point.
   * @param float $y2 The y-coordinate of the ending point.
   * @return void
   */
  public function Line($x1, $y1, $x2, $y2)
  {
    //Draw a line
    $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
  }

  /**
   * Draws a rectangle with the specified dimensions and style.
   *
   * @param float $x The X-coordinate of the top-left corner.
   * @param float $y The Y-coordinate of the top-left corner.
   * @param float $w The width of the rectangle.
   * @param float $h The height of the rectangle.
   * @param string $style The style of the rectangle: 'F' for filled, 'FD'/'DF' for filled with border, or an empty string for border only.
   * @return void
   */
  public function Rect($x, $y, $w, $h, $style = '')
  {
    //Draw a rectangle
    if ($style == 'F')
      $op = 'f';
    elseif ($style == 'FD' || $style == 'DF')
      $op = 'B';
    else
      $op = 'S';
    $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
  }

  /**
   * Adds a TrueType or Type1 font to the document.
   *
   * @param string $family The font family name. Automatic substitutions may occur (e.g., "Arial" to "Helvetica").
   * @param string $style The font style (e.g., '', 'B', 'I', 'BI'). Defaults to an empty string for normal style.
   * @param string $file The font definition file. If not provided, it will be automatically derived based on the family and style.
   *
   * @return void
   */
  public function AddFont($family, $style = '', $file = '')
  {
    //Add a TrueType or Type1 font
    $family = mb_strtolower($family);
    if ($file == '')
      $file = str_replace(' ', '', $family) . mb_strtolower($style) . '.php';
    if ($family == 'arial')
      $family = 'helvetica';
    $style = mb_strtoupper($style);
    if ($style == 'IB')
      $style = 'BI';
    $fontkey = $family . $style;
    if (isset($this->fonts[$fontkey]))
      return;
    include($this->_getfontpath() . $file);
    if (!isset($name))
      $this->Error('Could not include font definition file');
    $i = count($this->fonts) + 1;
    $this->fonts[$fontkey] = array('i' => $i, 'type' => $type, 'name' => $name, 'desc' => $desc, 'up' => $up, 'ut' => $ut, 'cw' => $cw, 'enc' => $enc, 'file' => $file);
    if ($diff) {
      //Search existing encodings
      $d = 0;
      $nb = count($this->diffs);
      for ($i = 1; $i <= $nb; $i++) {
        if ($this->diffs[$i] == $diff) {
          $d = $i;
          break;
        }
      }
      if ($d == 0) {
        $d = $nb + 1;
        $this->diffs[$d] = $diff;
      }
      $this->fonts[$fontkey]['diff'] = $d;
    }
    if ($file) {
      if ($type == 'TrueType')
        $this->FontFiles[$file] = array('length1' => $originalsize);
      else
        $this->FontFiles[$file] = array('length1' => $size1, 'length2' => $size2);
    }
  }

  /**
   * Sets the font to be used in the document, including its family, style, and size.
   *
   * @param string $family The name of the font to be used, e.g., 'Arial', 'Helvetica'. It is case-insensitive.
   * @param string $style Optional. The font style to be applied, e.g., 'B' for bold, 'I' for italic, or 'BI' for bold italic. Defaults to an empty string for regular style. It is case-insensitive.
   * @param float $size Optional. The size of the font in points. If set to 0, the previously defined font size will be used. Defaults to 0.
   *
   * @return void
   */
  public function SetFont($family, $style = '', $size = 0)
  {
    //Select a font; size given in points
    global $fpdf_charwidths;

    $family = mb_strtolower($family);
    if ($family == '')
      $family = $this->FontFamily;
    if ($family == 'arial')
      $family = 'helvetica';
    elseif ($family == 'symbol' || $family == 'zapfdingbats')
      $style = '';
    $style = mb_strtoupper($style);
    if (str_contains($style, 'U')) {
      $this->underline = true;
      $style = str_replace('U', '', $style);
    } else
      $this->underline = false;
    if ($style == 'IB')
      $style = 'BI';
    if ($size == 0)
      $size = $this->FontSizePt;
    //Test if font is already selected
    if ($this->FontFamily == $family && $this->FontStyle == $style && $this->FontSizePt == $size)
      return;
    //Test if used for the first time
    $fontkey = $family . $style;
    if (!isset($this->fonts[$fontkey])) {
      //Check if one of the standard fonts
      if (isset($this->includesFonts[$fontkey])) {
        if (!isset($fpdf_charwidths[$fontkey])) {
          //Load metric file
          $file = $family;
          if ($family == 'times' || $family == 'helvetica')
            $file .= mb_strtolower($style);
          include($this->_getfontpath() . $file . '.php');
          if (!isset($fpdf_charwidths[$fontkey]))
            $this->Error('Could not include font metric file');
        }
        $i = count($this->fonts) + 1;
        $name = $this->includesFonts[$fontkey];
        $cw = $fpdf_charwidths[$fontkey];
        $this->fonts[$fontkey] = array('i' => $i, 'type' => 'core', 'name' => $name, 'up' => -100, 'ut' => 50, 'cw' => $cw);
      } else
        $this->Error('Undefined font: ' . $family . ' ' . $style);
    }
    //Select it
    $this->FontFamily = $family;
    $this->FontStyle = $style;
    $this->FontSizePt = $size;
    $this->FontSize = $size / $this->k;
    $this->CurrentFont =& $this->fonts[$fontkey];
    if ($this->page > 0)
      $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
  }

  /**
   * Sets the font size in points for the document.
   *
   * @param float $size The desired font size in points.
   * @return void
   */
  public function SetFontSize($size)
  {
    //Set font size in points
    if ($this->FontSizePt == $size)
      return;
    $this->FontSizePt = $size;
    $this->FontSize = $size / $this->k;
    if ($this->page > 0)
      $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
  }

  /**
   * Creates a new internal link and adds it to the list of links.
   *
   * @return int The identifier of the newly created link.
   */
  public function AddLink()
  {
    //Create a new internal link
    $n = count($this->links) + 1;
    $this->links[$n] = array(0, 0);
    return $n;
  }

  /**
   * Sets the destination of an internal link.
   *
   * @param int $link The link identifier.
   * @param float|int $y The y-coordinate in the destination page. Defaults to the current y coordinate if set to 0.
   * @param int $page The destination page number. Defaults to the current page if set to -1.
   * @return void
   */
  public function SetLink($link, $y = 0, $page = -1)
  {
    //Set destination of internal link
    if ($y == -1)
      $y = $this->y;
    if ($page == -1)
      $page = $this->page;
    $this->links[$link] = array($page, $y);
  }

  /**
   * Creates a link on the page at the specified position and dimensions.
   *
   * @param float $x The X-coordinate of the link's origin.
   * @param float $y The Y-coordinate of the link's origin.
   * @param float $w The width of the link.
   * @param float $h The height of the link.
   * @param string $link The URL or internal destination of the link.
   * @return void
   */
  public function Link($x, $y, $w, $h, $link)
  {
    //Put a link on the page
    $this->PageLinks[$this->page][] = array($x * $this->k, $this->hPt - $y * $this->k, $w * $this->k, $h * $this->k, $link);
  }

  /**
   * Outputs a string at the specified position with optional formatting.
   *
   * @param float $x The x-coordinate of the position where the text begins.
   * @param float $y The y-coordinate of the position where the text begins.
   * @param string $txt The text string to output.
   * @return void
   */
  public function Text($x, $y, $txt)
  {
    //Output a string
    $s = sprintf('BT %.2F %.2F Td (%s) Tj ET', $x * $this->k, ($this->h - $y) * $this->k, $this->_escape($txt));
    if ($this->underline && $txt != '')
      $s .= ' ' . $this->_dounderline($x, $y, $txt);
    if ($this->ColorFlag)
      $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
    $this->_out($s);
  }

  /**
   * Determines whether to accept automatic page breaks.
   *
   * This method checks if the AutoPageBreak property is enabled
   * and returns its value to determine if automatic page breaks
   * should occur.
   *
   * @return bool Returns true if automatic page breaks are accepted, false otherwise.
   */
  public function AcceptPageBreak()
  {
    //Accept automatic page break or not
    return $this->AutoPageBreak;
  }

  /**
   *
   */
  public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
  {
    //Output a cell
    $k = $this->k;
    if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
      //Automatic page break
      $x = $this->x;
      $ws = $this->ws;
      if ($ws > 0) {
        $this->ws = 0;
        $this->_out('0 Tw');
      }
      $this->AddPage($this->CurOrientation, $this->CurPageFormat);
      $this->x = $x;
      if ($ws > 0) {
        $this->ws = $ws;
        $this->_out(sprintf('%.3F Tw', $ws * $k));
      }
    }
    if ($w == 0)
      $w = $this->w - $this->rMargin - $this->x;
    $s = '';
    if ($fill || $border == 1) {
      if ($fill)
        $op = ($border == 1) ? 'B' : 'f';
      else
        $op = 'S';
      $s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
    }
    if (is_string($border)) {
      $x = $this->x;
      $y = $this->y;
      if (str_contains($border, 'L'))
        $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, $x * $k, ($this->h - ($y + $h)) * $k);
      if (str_contains($border, 'T'))
        $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - $y) * $k);
      if (str_contains($border, 'R'))
        $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
      if (str_contains($border, 'B'))
        $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - ($y + $h)) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
    }
    if ($txt !== '') {
      if ($align == 'R')
        $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
      elseif ($align == 'C')
        $dx = ($w - $this->GetStringWidth($txt)) / 2;
      else
        $dx = $this->cMargin;
      if ($this->ColorFlag)
        $s .= 'q ' . $this->TextColor . ' ';
      $txt2 = str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $txt)));
      $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + .5 * $h + .3 * $this->FontSize)) * $k, $txt2);
      if ($this->underline)
        $s .= ' ' . $this->_dounderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->FontSize, $txt);
      if ($this->ColorFlag)
        $s .= ' Q';
      if ($link)
        $this->Link($this->x + $dx, $this->y + .5 * $h - .5 * $this->FontSize, $this->GetStringWidth($txt), $this->FontSize, $link);
    }
    if ($s)
      $this->_out($s);
    $this->lasth = $h;
    if ($ln > 0) {
      //Go to next line
      $this->y += $h;
      if ($ln == 1)
        $this->x = $this->lMargin;
    } else
      $this->x += $w;
  }

  /**
   * Outputs text with automatic or explicit line breaks, handling multi-line functionality.
   *
   * @param float $w Width of the cell. If 0, it extends to the right margin.
   * @param float $h Height of one line of text.
   * @param string $txt The string to be output.
   * @param mixed $border Indicates if borders are to be drawn around the cell.
   *                      0 means no border, 1 means a frame, and a string specifies which
   *                      borders to draw (L, T, R, B).
   * @param string $align Alignment of the text. Possible values:
   *                      'L' (left align), 'C' (center), 'R' (right align), 'J' (justification).
   * @param bool $fill Determines if the cell background should be painted. Default is false.
   * @return void
   */
  public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
  {
    //Output text with automatic or explicit line breaks
    $cw =& $this->CurrentFont['cw'];
    if ($w == 0)
      $w = $this->w - $this->rMargin - $this->x;
    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
    $s = str_replace("\r", '', $txt);
    $nb = strlen($s);
    if ($nb > 0 && $s[$nb - 1] == "\n")
      $nb--;
    $b = 0;
    if ($border) {
      if ($border == 1) {
        $border = 'LTRB';
        $b = 'LRT';
        $b2 = 'LR';
      } else {
        $b2 = '';
        if (str_contains($border, 'L'))
          $b2 .= 'L';
        if (str_contains($border, 'R'))
          $b2 .= 'R';
        $b = (str_contains($border, 'T')) ? $b2 . 'T' : $b2;
      }
    }
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $ns = 0;
    $nl = 1;
    while ($i < $nb) {
      //Get next character
      $c = $s[$i];
      if ($c == "\n") {
        //Explicit line break
        if ($this->ws > 0) {
          $this->ws = 0;
          $this->_out('0 Tw');
        }
        $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $i++;
        $sep = -1;
        $j = $i;
        $l = 0;
        $ns = 0;
        $nl++;
        if ($border && $nl == 2)
          $b = $b2;
        continue;
      }
      if ($c == ' ') {
        $sep = $i;
        $ls = $l;
        $ns++;
      }
      $l += $cw[$c];
      if ($l > $wmax) {
        //Automatic line break
        if ($sep == -1) {
          if ($i == $j)
            $i++;
          if ($this->ws > 0) {
            $this->ws = 0;
            $this->_out('0 Tw');
          }
          $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        } else {
          if ($align == 'J') {
            $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->FontSize / ($ns - 1) : 0;
            $this->_out(sprintf('%.3F Tw', $this->ws * $this->k));
          }
          $this->Cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
          $i = $sep + 1;
        }
        $sep = -1;
        $j = $i;
        $l = 0;
        $ns = 0;
        $nl++;
        if ($border && $nl == 2)
          $b = $b2;
      } else
        $i++;
    }
    //Last chunk
    if ($this->ws > 0) {
      $this->ws = 0;
      $this->_out('0 Tw');
    }
    if ($border && str_contains($border, 'B'))
      $b .= 'B';
    $this->Cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
    $this->x = $this->lMargin;
  }

  /**
   * Outputs text in flowing mode with automatic or explicit line breaks.
   *
   * @param float $h The line height.
   * @param string $txt The text string to be written.
   * @param string $link An optional URL for a hyperlink. Default is an empty string.
   * @return void
   */
  public function Write($h, $txt, $link = '')
  {
    //Output text in flowing mode
    $cw =& $this->CurrentFont['cw'];
    $w = $this->w - $this->rMargin - $this->x;
    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
    $s = str_replace("\r", '', $txt);
    $nb = strlen($s);
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $nl = 1;
    while ($i < $nb) {
      //Get next character
      $c = $s[$i];
      if ($c == "\n") {
        //Explicit line break
        $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
        $i++;
        $sep = -1;
        $j = $i;
        $l = 0;
        if ($nl == 1) {
          $this->x = $this->lMargin;
          $w = $this->w - $this->rMargin - $this->x;
          $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        }
        $nl++;
        continue;
      }
      if ($c == ' ')
        $sep = $i;
      $l += $cw[$c];
      if ($l > $wmax) {
        //Automatic line break
        if ($sep == -1) {
          if ($this->x > $this->lMargin) {
            //Move to next line
            $this->x = $this->lMargin;
            $this->y += $h;
            $w = $this->w - $this->rMargin - $this->x;
            $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
            $i++;
            $nl++;
            continue;
          }
          if ($i == $j)
            $i++;
          $this->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
        } else {
          $this->Cell($w, $h, substr($s, $j, $sep - $j), 0, 2, '', 0, $link);
          $i = $sep + 1;
        }
        $sep = -1;
        $j = $i;
        $l = 0;
        if ($nl == 1) {
          $this->x = $this->lMargin;
          $w = $this->w - $this->rMargin - $this->x;
          $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        }
        $nl++;
      } else
        $i++;
    }
    //Last chunk
    if ($i != $j)
      $this->Cell($l / 1000 * $this->FontSize, $h, substr($s, $j), 0, 0, '', 0, $link);
  }

  /**
   * Performs a line break by moving the current position to the beginning of the next line.
   *
   * @param float|null $h The height of the line break. If null, the height of the last cell is used.
   * @return void
   */
  public function Ln($h = null)
  {
    //Line feed; default value is last cell height
    $this->x = $this->lMargin;
    if ($h === null)
      $this->y += $this->lasth;
    else
      $this->y += $h;
  }

  /**
   * Places an image on the page.
   *
   * @param string $file The path to the image file.
   * @param float|null $x The x-coordinate of the image. If null, it is automatically determined.
   * @param float|null $y The y-coordinate of the image. If null, it is automatically determined.
   * @param float $w The width of the image in user units. If 0, it is automatically calculated.
   * @param float $h The height of the image in user units. If 0, it is automatically calculated.
   * @param string $type The image type (e.g., 'jpg', 'png'). If empty, it is inferred from the file extension.
   * @param string $link A URL or identifier for a link the image should point to. If empty, no link is added.
   * @return void
   */
  public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
  {
    //Put an image on the page
    if (!isset($this->images[$file])) {
      //First use of this image, get info
      if ($type == '') {
        $pos = strrpos($file, '.');
        if (!$pos)
          $this->Error('Image file has no extension and no type was specified: ' . $file);
        $type = substr($file, $pos + 1);
      }
      $type = mb_strtolower($type);
      if ($type == 'jpeg')
        $type = 'jpg';
      $mtd = '_parse' . $type;
      if (!method_exists($this, $mtd))
        $this->Error('Unsupported image type: ' . $type);
      $info = $this->$mtd($file);
      $info['i'] = count($this->images) + 1;
      $this->images[$file] = $info;
    } else
      $info = $this->images[$file];
    //Automatic width and height calculation if needed
    if ($w == 0 && $h == 0) {
      //Put image at 72 dpi
      $w = $info['w'] / $this->k;
      $h = $info['h'] / $this->k;
    } elseif ($w == 0)
      $w = $h * $info['w'] / $info['h'];
    elseif ($h == 0)
      $h = $w * $info['h'] / $info['w'];
    //Flowing mode
    if ($y === null) {
      if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
        //Automatic page break
        $x2 = $this->x;
        $this->AddPage($this->CurOrientation, $this->CurPageFormat);
        $this->x = $x2;
      }
      $y = $this->y;
      $this->y += $h;
    }
    if ($x === null)
      $x = $this->x;
    $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));
    if ($link)
      $this->Link($x, $y, $w, $h, $link);
  }

  /**
   * Retrieves the current x-coordinate position.
   *
   * @return float The x-coordinate.
   */
  public function GetX()
  {
    //Get x position
    return $this->x;
  }

  /**
   * Sets the x position.
   *
   * @param float|int $x The x position. If the value is greater than or equal to 0,
   * it sets the position to this value. Otherwise, calculates the position
   * relative to the width.
   * @return void
   */
  public function SetX($x)
  {
    //Set x position
    if ($x >= 0)
      $this->x = $x;
    else
      $this->x = $this->w + $x;
  }

  /**
   * Retrieves the current y position.
   *
   * @return float The y position coordinate.
   */
  public function GetY()
  {
    //Get y position
    return $this->y;
  }

  /**
   * Sets the y-coordinate position and resets the x-coordinate to the left margin.
   *
   * @param float $y The y-coordinate position. If a negative value is provided, the position is calculated relative to the bottom of the page.
   * @return void
   */
  public function SetY($y)
  {
    //Set y position and reset x
    $this->x = $this->lMargin;
    if ($y >= 0)
      $this->y = $y;
    else
      $this->y = $this->h + $y;
  }

  /**
   * Sets the x and y positions.
   *
   * @param float $x The x-coordinate position to set.
   * @param float $y The y-coordinate position to set.
   * @return void
   */
  public function SetXY($x, $y)
  {
    //Set x and y positions
    $this->SetY($y);
    $this->SetX($x);
  }

  /**
   * Outputs the generated PDF to a specified destination.
   *
   * @param string $name The name of the file. If not specified, defaults to 'doc.pdf'.
   * @param string $dest The output destination. Options are:
   *                     'I': Send the file inline to the browser.
   *                     'D': Download the file as an attachment.
   *                     'F': Save the file to the local filesystem.
   *                     'S': Return the PDF as a string.
   *                     If not specified, defaults to 'I' (inline for browser).
   * @return string Returns the PDF document as a string if the destination is 'S'. Returns an empty string for all other destinations.
   */
  public function Output($name = '', $dest = '')
  {
    //Output PDF to some destination
    if ($this->state < 3)
      $this->Close();
    $dest = mb_strtoupper($dest);
    if ($dest == '') {
      if ($name == '') {
        $name = 'doc.pdf';
        $dest = 'I';
      } else
        $dest = 'F';
    }
    switch ($dest) {
      case 'I':
        //Send to standard output
        if (ob_get_length())
          $this->Error('Some data has already been output, can\'t send PDF file');
        if (php_sapi_name() != 'cli') {
          //We send to a browser
          header('Content-Type: application/pdf');
          if (headers_sent())
            $this->Error('Some data has already been output, can\'t send PDF file');
          header('Content-Length: ' . strlen($this->buffer));
          header('Content-Disposition: inline; filename="' . $name . '"');
          header('Cache-Control: private, max-age=0, must-revalidate');
          header('Pragma: public');
          ini_set('zlib.output_compression', '0');
        }
        echo $this->buffer;
        break;
      case 'D':
        //Download file
        if (ob_get_length())
          $this->Error('Some data has already been output, can\'t send PDF file');
        header('Content-Type: application/x-download');
        if (headers_sent())
          $this->Error('Some data has already been output, can\'t send PDF file');
        header('Content-Length: ' . strlen($this->buffer));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        ini_set('zlib.output_compression', '0');
        echo $this->buffer;
        break;
      case 'F':
        //Save to local file
        $f = fopen($name, 'wb');
        if (!$f)
          $this->Error('Unable to create output file: ' . $name);
        fwrite($f, $this->buffer, strlen($this->buffer));
        fclose($f);
        break;
      case 'S':
        //Return as a string
        return $this->buffer;
      default:
        $this->Error('Incorrect output destination: ' . $dest);
    }
    return '';
  }

  /**
   * Performs a series of system checks to ensure compatibility and proper operation.
   *
   * @return void
   */
  public function _dochecks()
  {
    //Check availability of %F
    if (sprintf('%.1F', 1.0) != '1.0')
      $this->Error('This version of PHP is not supported');
    //Check mbstring overloading
    if (ini_get('mbstring.func_overload') & 2)
      $this->Error('mbstring overloading must be disabled');
  }

  /**
   * Retrieves the dimensions of a specified page format.
   *
   * @param string $format The name of the page format to retrieve dimensions for.
   *                        Expected to be a predefined key in the PageFormats array.
   * @return array An array containing the width and height of the specified page format,
   *               scaled using the current unit conversion factor.
   */
  public function _getpageformat($format)
  {
    $format = mb_strtolower($format);
    if (!isset($this->PageFormats[$format]))
      $this->Error('Unknown page format: ' . $format);
    $a = $this->PageFormats[$format];
    return array($a[0] / $this->k, $a[1] / $this->k);
  }

  /**
   * Retrieves the font path used by the system. If the font path is not defined,
   * it attempts to set a default path based on the current directory's 'font' folder.
   *
   * @return string The defined font path, or an empty string if no path is set.
   */
  public function _getfontpath()
  {
    if (!defined('FPDF_FONTPATH') && is_dir(__DIR__ . '/font'))
      define('FPDF_FONTPATH', __DIR__ . '/font/');
    return defined('FPDF_FONTPATH') ? FPDF_FONTPATH : '';
  }

  /**
   * Initializes a new page in the document with specified orientation and format.
   * Updates page size, margins, and orientation if different from the default or current settings.
   *
   * @param string $orientation The orientation of the page ('P' for portrait, 'L' for landscape). If empty, defaults to the document's default orientation.
   * @param mixed $format The format of the page. Accepts predefined format names (e.g., 'A4', 'Letter') or an array of dimensions [width, height]. If empty, defaults to the document's default page format.
   *
   * @return void
   */
  public function _beginpage($orientation, $format)
  {
    $this->page++;
    $this->pages[$this->page] = '';
    $this->state = 2;
    $this->x = $this->lMargin;
    $this->y = $this->tMargin;
    $this->FontFamily = '';
    //Check page size
    if ($orientation == '')
      $orientation = $this->DefOrientation;
    else
      $orientation = mb_strtoupper($orientation[0]);
    if ($format == '')
      $format = $this->DefPageFormat;
    else {
      if (is_string($format))
        $format = $this->_getpageformat($format);
    }
    if ($orientation != $this->CurOrientation || $format[0] != $this->CurPageFormat[0] || $format[1] != $this->CurPageFormat[1]) {
      //New size
      if ($orientation == 'P') {
        $this->w = $format[0];
        $this->h = $format[1];
      } else {
        $this->w = $format[1];
        $this->h = $format[0];
      }
      $this->wPt = $this->w * $this->k;
      $this->hPt = $this->h * $this->k;
      $this->PageBreakTrigger = $this->h - $this->bMargin;
      $this->CurOrientation = $orientation;
      $this->CurPageFormat = $format;
    }
    if ($orientation != $this->DefOrientation || $format[0] != $this->DefPageFormat[0] || $format[1] != $this->DefPageFormat[1])
      $this->PageSizes[$this->page] = array($this->wPt, $this->hPt);
  }

  /**
   * Marks the end of the page process by updating the state.
   *
   * @return void
   */
  public function _endpage()
  {
    $this->state = 1;
  }

  /**
   * Escapes special characters in a string to ensure safe processing or output.
   *
   * @param string $s The input string to be escaped.
   * @return string The escaped string with special characters replaced.
   */
  public function _escape($s)
  {
    //Escape special characters in strings
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace('(', '\\(', $s);
    $s = str_replace(')', '\\)', $s);
    $s = str_replace("\r", '\\r', $s);
    return $s;
  }

  /**
   * Formats a text string by escaping special characters and wrapping it in parentheses.
   *
   * @param string $s The input string to be formatted.
   * @return string The formatted text string.
   */
  public function _textstring($s)
  {
    //Format a text string
    return '(' . $this->_escape($s) . ')';
  }

  /**
   * Converts a UTF-8 encoded string to a UTF-16BE encoded string with BOM.
   *
   * @param string $s The UTF-8 encoded string to be converted.
   * @return string The UTF-16BE encoded string with BOM.
   */
  public function _UTF8toUTF16($s)
  {
    //Convert UTF-8 to UTF-16BE with BOM
    $res = "\xFE\xFF";
    $nb = strlen($s);
    $i = 0;
    while ($i < $nb) {
      $c1 = ord($s[$i++]);
      if ($c1 >= 224) {
        //3-byte character
        $c2 = ord($s[$i++]);
        $c3 = ord($s[$i++]);
        $res .= chr((($c1 & 0x0F) << 4) + (($c2 & 0x3C) >> 2));
        $res .= chr((($c2 & 0x03) << 6) + ($c3 & 0x3F));
      } elseif ($c1 >= 192) {
        //2-byte character
        $c2 = ord($s[$i++]);
        $res .= chr(($c1 & 0x1C) >> 2);
        $res .= chr((($c1 & 0x03) << 6) + ($c2 & 0x3F));
      } else {
        //Single-byte character
        $res .= "\0" . chr($c1);
      }
    }
    return $res;
  }

  /**
   * Generates the underline text path for drawing in the PDF document.
   *
   * @param float $x The x-coordinate position where the text starts.
   * @param float $y The y-coordinate position where the text starts.
   * @param string $txt The text to be underlined.
   * @return string The formatted underline path as a string.
   */
  public function _dounderline($x, $y, $txt)
  {
    //Underline text
    $up = $this->CurrentFont['up'];
    $ut = $this->CurrentFont['ut'];
    $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ');
    return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
  }

  /**
   * Extracts information from a JPEG file and returns its properties.
   *
   * @param string $file The path to the JPEG file to be parsed.
   *
   * @return array An associative array containing the following keys:
   *               - 'w': The width of the image in pixels.
   *               - 'h': The height of the image in pixels.
   *               - 'cs': The color space of the image (e.g., 'DeviceRGB', 'DeviceCMYK', 'DeviceGray').
   *               - 'bpc': The bits per component (e.g., 8).
   *               - 'f': The filter applied to the image data (e.g., 'DCTDecode').
   *               - 'data': The raw binary data of the image.
   */
  public function _parsejpg($file)
  {
    //Extract info from a JPEG file
    $a = GetImageSize($file);
    if (!$a)
      $this->Error('Missing or incorrect image file: ' . $file);
    if ($a[2] != 2)
      $this->Error('Not a JPEG file: ' . $file);
    if (!isset($a['channels']) || $a['channels'] == 3)
      $colspace = 'DeviceRGB';
    elseif ($a['channels'] == 4)
      $colspace = 'DeviceCMYK';
    else
      $colspace = 'DeviceGray';
    $bpc = isset($a['bits']) ? $a['bits'] : 8;
    //Read whole file
    $f = fopen($file, 'rb');
    $data = '';
    while (!feof($f))
      $data .= fread($f, 8192);
    fclose($f);
    return array('w' => $a[0], 'h' => $a[1], 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'DCTDecode', 'data' => $data);
  }

  /**
   * Parses a PNG file and extracts pertinent image information.
   *
   * @param string $file The path to the PNG file to be parsed.
   * @return array An associative array containing the extracted image information:
   *               - 'w': Width of the image.
   *               - 'h': Height of the image.
   *               - 'cs': Color space (e.g., DeviceGray, DeviceRGB, Indexed).
   *               - 'bpc': Bits per component.
   *               - 'f': Decoding filter used, typically 'FlateDecode'.
   *               - 'parms': Parameters for decoding.
   *               - 'pal': Palette data, if applicable.
   *               - 'trns': Transparency information, if applicable.
   *               - 'data': Encoded image data.
   */
  public function _parsepng($file)
  {
    //Extract info from a PNG file
    $f = fopen($file, 'rb');
    if (!$f)
      $this->Error('Can\'t open image file: ' . $file);
    //Check signature
    if ($this->_readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10))
      $this->Error('Not a PNG file: ' . $file);
    //Read header chunk
    $this->_readstream($f, 4);
    if ($this->_readstream($f, 4) != 'IHDR')
      $this->Error('Incorrect PNG file: ' . $file);
    $w = $this->_readint($f);
    $h = $this->_readint($f);
    $bpc = ord($this->_readstream($f, 1));
    if ($bpc > 8)
      $this->Error('16-bit depth not supported: ' . $file);
    $ct = ord($this->_readstream($f, 1));
    if ($ct == 0)
      $colspace = 'DeviceGray';
    elseif ($ct == 2)
      $colspace = 'DeviceRGB';
    elseif ($ct == 3)
      $colspace = 'Indexed';
    else
      $this->Error('Alpha channel not supported: ' . $file);
    if (ord($this->_readstream($f, 1)) != 0)
      $this->Error('Unknown compression method: ' . $file);
    if (ord($this->_readstream($f, 1)) != 0)
      $this->Error('Unknown filter method: ' . $file);
    if (ord($this->_readstream($f, 1)) != 0)
      $this->Error('Interlacing not supported: ' . $file);
    $this->_readstream($f, 4);
    $parms = '/DecodeParms <</Predictor 15 /Colors ' . ($ct == 2 ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w . '>>';
    //Scan chunks looking for palette, transparency and image data
    $pal = '';
    $trns = '';
    $data = '';
    do {
      $n = $this->_readint($f);
      $type = $this->_readstream($f, 4);
      if ($type == 'PLTE') {
        //Read palette
        $pal = $this->_readstream($f, $n);
        $this->_readstream($f, 4);
      } elseif ($type == 'tRNS') {
        //Read transparency info
        $t = $this->_readstream($f, $n);
        if ($ct == 0)
          $trns = array(ord(substr($t, 1, 1)));
        elseif ($ct == 2)
          $trns = array(ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1)));
        else {
          $pos = strpos($t, chr(0));
          if ($pos !== false)
            $trns = array($pos);
        }
        $this->_readstream($f, 4);
      } elseif ($type == 'IDAT') {
        //Read image data block
        $data .= $this->_readstream($f, $n);
        $this->_readstream($f, 4);
      } elseif ($type == 'IEND')
        break;
      else
        $this->_readstream($f, $n + 4);
    } while ($n);
    if ($colspace == 'Indexed' && empty($pal))
      $this->Error('Missing palette in ' . $file);
    fclose($f);
    return array('w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'parms' => $parms, 'pal' => $pal, 'trns' => $trns, 'data' => $data);
  }

  /**
   * Reads a specified number of bytes from a given stream.
   *
   * @param resource $f The stream resource to read from.
   * @param int $n The number of bytes to read from the stream.
   * @return string The data read from the stream.
   */
  public function _readstream($f, $n)
  {
    //Read n bytes from stream
    $res = '';
    while ($n > 0 && !feof($f)) {
      $s = fread($f, $n);
      if ($s === false)
        $this->Error('Error while reading stream');
      $n -= strlen($s);
      $res .= $s;
    }
    if ($n > 0)
      $this->Error('Unexpected end of stream');
    return $res;
  }

  /**
   * Reads a 4-byte integer from a binary stream.
   *
   * @param resource $f A file handle or stream resource from which the 4-byte integer will be read.
   * @return int The 4-byte integer value read from the stream.
   */
  public function _readint($f)
  {
    //Read a 4-byte integer from stream
    $a = unpack('Ni', $this->_readstream($f, 4));
    return $a['i'];
  }

  /**
   * Extracts information from a GIF file by converting it to PNG format.
   *
   * @param string $file The path to the GIF file to be processed.
   * @return array The extracted information from the GIF file, obtained via PNG conversion.
   */
  public function _parsegif($file)
  {
    //Extract info from a GIF file (via PNG conversion)
    if (!function_exists('imagepng'))
      $this->Error('GD extension is required for GIF support');
    if (!function_exists('imagecreatefromgif'))
      $this->Error('GD has no GIF read support');
    $im = imagecreatefromgif($file);
    if (!$im)
      $this->Error('Missing or incorrect image file: ' . $file);
    imageinterlace($im, 0);
    $tmp = tempnam('.', 'gif');
    if (!$tmp)
      $this->Error('Unable to create a temporary file');
    if (!imagepng($im, $tmp))
      $this->Error('Error while saving to temporary file');
    unset($im);
    $info = $this->_parsepng($tmp);
    unlink($tmp);
    return $info;
  }

  /**
   * Begins a new object in the document.
   *
   * @return void
   */
  public function _newobj()
  {
    //Begin a new object
    $this->n++;
    $this->offsets[$this->n] = strlen($this->buffer);
    $this->_out($this->n . ' 0 obj');
  }

  /**
   * Outputs a stream of data to the document.
   *
   * @param string $s The data to be streamed.
   * @return void
   */
  public function _putstream($s)
  {
    $this->_out('stream');
    $this->_out($s);
    $this->_out('endstream');
  }

  /**
   * Adds a line of text to the document, either to the current page or to the buffer,
   * depending on the state of the document.
   *
   * @param string $s The line of text to be added.
   * @return void
   */
  public function _out($s)
  {
    //Add a line to the document
    if ($this->state == 2)
      $this->pages[$this->page] .= $s . "\n";
    else
      $this->buffer .= $s . "\n";
  }

  /**
   * Finalizes the page objects and builds the PDF page tree structure.
   *
   * This method manages the replacement of aliases with the actual number of pages,
   * handles the creation of page objects with their respective dimensions and resources,
   * incorporates annotations and links if applicable, and creates the Pages root object
   * to organize the document's hierarchical structure.
   *
   * @return void
   */
  public function _putpages()
  {
    $nb = $this->page;
    if (!empty($this->AliasNbPages)) {
      //Replace number of pages
      for ($n = 1; $n <= $nb; $n++)
        $this->pages[$n] = str_replace($this->AliasNbPages, $nb, $this->pages[$n]);
    }
    if ($this->DefOrientation == 'P') {
      $wPt = $this->DefPageFormat[0] * $this->k;
      $hPt = $this->DefPageFormat[1] * $this->k;
    } else {
      $wPt = $this->DefPageFormat[1] * $this->k;
      $hPt = $this->DefPageFormat[0] * $this->k;
    }
    $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
    for ($n = 1; $n <= $nb; $n++) {
      //Page
      $this->_newobj();
      $this->_out('<</Type /Page');
      $this->_out('/Parent 1 0 R');
      if (isset($this->PageSizes[$n]))
        $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageSizes[$n][0], $this->PageSizes[$n][1]));
      $this->_out('/Resources 2 0 R');
      if (isset($this->PageLinks[$n])) {
        //Links
        $annots = '/Annots [';
        foreach ($this->PageLinks[$n] as $pl) {
          $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
          $annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
          if (is_string($pl[4]))
            $annots .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';
          else {
            $l = $this->links[$pl[4]];
            $h = isset($this->PageSizes[$l[0]]) ? $this->PageSizes[$l[0]][1] : $hPt;
            $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', 1 + 2 * $l[0], $h - $l[1] * $this->k);
          }
        }
        $this->_out($annots . ']');
      }
      $this->_out('/Contents ' . ($this->n + 1) . ' 0 R>>');
      $this->_out('endobj');
      //Page content
      $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
      $this->_newobj();
      $this->_out('<<' . $filter . '/Length ' . strlen($p) . '>>');
      $this->_putstream($p);
      $this->_out('endobj');
    }
    //Pages root
    $this->offsets[1] = strlen($this->buffer);
    $this->_out('1 0 obj');
    $this->_out('<</Type /Pages');
    $kids = '/Kids [';
    for ($i = 0; $i < $nb; $i++)
      $kids .= (3 + 2 * $i) . ' 0 R ';
    $this->_out($kids . ']');
    $this->_out('/Count ' . $nb);
    $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
    $this->_out('>>');
    $this->_out('endobj');
  }

  /**
   * Embeds fonts into the document by creating the necessary PDF objects.
   * Handles standard fonts, TrueType, Type1 fonts, and other additional font types.
   *
   * It works through encodings, embedding font files, and generating font objects,
   * including descriptors and widths for non-core fonts.
   *
   * @return void
   */
  public function _putfonts()
  {
    $nf = $this->n;
    foreach ($this->diffs as $diff) {
      //Encodings
      $this->_newobj();
      $this->_out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $diff . ']>>');
      $this->_out('endobj');
    }
    foreach ($this->FontFiles as $file => $info) {
      //Font file embedding
      $this->_newobj();
      $this->FontFiles[$file]['n'] = $this->n;
      $font = '';
      $f = fopen($this->_getfontpath() . $file, 'rb', 1);
      if (!$f)
        $this->Error('Font file not found');
      while (!feof($f))
        $font .= fread($f, 8192);
      fclose($f);
      $compressed = (substr($file, -2) == '.z');
      if (!$compressed && isset($info['length2'])) {
        $header = (ord($font[0]) == 128);
        if ($header) {
          //Strip first binary header
          $font = substr($font, 6);
        }
        if ($header && ord($font[$info['length1']]) == 128) {
          //Strip second binary header
          $font = substr($font, 0, $info['length1']) . substr($font, $info['length1'] + 6);
        }
      }
      $this->_out('<</Length ' . strlen($font));
      if ($compressed)
        $this->_out('/Filter /FlateDecode');
      $this->_out('/Length1 ' . $info['length1']);
      if (isset($info['length2']))
        $this->_out('/Length2 ' . $info['length2'] . ' /Length3 0');
      $this->_out('>>');
      $this->_putstream($font);
      $this->_out('endobj');
    }
    foreach ($this->fonts as $k => $font) {
      //Font objects
      $this->fonts[$k]['n'] = $this->n + 1;
      $type = $font['type'];
      $name = $font['name'];
      if ($type == 'core') {
        //Standard font
        $this->_newobj();
        $this->_out('<</Type /Font');
        $this->_out('/BaseFont /' . $name);
        $this->_out('/Subtype /Type1');
        if ($name != 'Symbol' && $name != 'ZapfDingbats')
          $this->_out('/Encoding /WinAnsiEncoding');
        $this->_out('>>');
        $this->_out('endobj');
      } elseif ($type == 'Type1' || $type == 'TrueType') {
        //Additional Type1 or TrueType font
        $this->_newobj();
        $this->_out('<</Type /Font');
        $this->_out('/BaseFont /' . $name);
        $this->_out('/Subtype /' . $type);
        $this->_out('/FirstChar 32 /LastChar 255');
        $this->_out('/Widths ' . ($this->n + 1) . ' 0 R');
        $this->_out('/FontDescriptor ' . ($this->n + 2) . ' 0 R');
        if ($font['enc']) {
          if (isset($font['diff']))
            $this->_out('/Encoding ' . ($nf + $font['diff']) . ' 0 R');
          else
            $this->_out('/Encoding /WinAnsiEncoding');
        }
        $this->_out('>>');
        $this->_out('endobj');
        //Widths
        $this->_newobj();
        $cw =& $font['cw'];
        $s = '[';
        for ($i = 32; $i <= 255; $i++)
          $s .= $cw[chr($i)] . ' ';
        $this->_out($s . ']');
        $this->_out('endobj');
        //Descriptor
        $this->_newobj();
        $s = '<</Type /FontDescriptor /FontName /' . $name;

        foreach ($font['desc'] as $k => $v)
          $s .= ' /' . $k . ' ' . $v;
        $file = $font['file'];
        if ($file)
          $s .= ' /FontFile' . ($type == 'Type1' ? '' : '2') . ' ' . $this->FontFiles[$file]['n'] . ' 0 R';
        $this->_out($s . '>>');
        $this->_out('endobj');
      } else {
        //Allow for additional types
        $mtd = '_put' . mb_strtolower($type);
        if (!method_exists($this, $mtd))
          $this->Error('Unsupported font type: ' . $type);
        $this->$mtd($font);
      }
    }
  }

  /**
   * Embeds images into the output document by processing information stored in the images array.
   * The method handles image properties such as dimensions, color space, compression, and mask transparency.
   * It creates necessary PDF objects for each image and optionally includes a color palette for indexed color spaces.
   *
   * @return void
   */
  public function _putimages()
  {
    $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
//while(list($file,$info)=each($this->images))
    foreach ($this->images as $file => $info) {
      $this->_newobj();
      $this->images[$file]['n'] = $this->n;
      $this->_out('<</Type /XObject');
      $this->_out('/Subtype /Image');
      $this->_out('/Width ' . $info['w']);
      $this->_out('/Height ' . $info['h']);
      if ($info['cs'] == 'Indexed')
        $this->_out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
      else {
        $this->_out('/ColorSpace /' . $info['cs']);
        if ($info['cs'] == 'DeviceCMYK')
          $this->_out('/Decode [1 0 1 0 1 0 1 0]');
      }
      $this->_out('/BitsPerComponent ' . $info['bpc']);
      if (isset($info['f']))
        $this->_out('/Filter /' . $info['f']);
      if (isset($info['parms']))
        $this->_out($info['parms']);
      if (isset($info['trns']) && is_array($info['trns'])) {
        $trns = '';
        for ($i = 0, $iMax = count($info['trns']); $i < $iMax; $i++)
          $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
        $this->_out('/Mask [' . $trns . ']');
      }
      $this->_out('/Length ' . strlen($info['data']) . '>>');
      $this->_putstream($info['data']);
      unset($this->images[$file]['data']);
      $this->_out('endobj');
      //Palette
      if ($info['cs'] == 'Indexed') {
        $this->_newobj();
        $pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
        $this->_out('<<' . $filter . '/Length ' . strlen($pal) . '>>');
        $this->_putstream($pal);
        $this->_out('endobj');
      }
    }
  }

  /**
   * Outputs the XObject dictionary for the document by iterating through all images and writing their references.
   *
   * @return void
   */
  public function _putxobjectdict()
  {
    foreach ($this->images as $image)
      $this->_out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
  }

  /**
   * Adds the resource dictionary to the PDF output.
   * This includes procedure sets, fonts, and external objects such as images.
   *
   * @return void
   */
  public function _putresourcedict()
  {
    $this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
    $this->_out('/Font <<');
    foreach ($this->fonts as $font)
      $this->_out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
    $this->_out('>>');
    $this->_out('/XObject <<');
    $this->_putxobjectdict();
    $this->_out('>>');
  }

  /**
   * Adds resources such as fonts and images to the PDF document.
   * This method initializes and constructs the resource dictionary for the PDF document
   * and outputs it to the document's buffer.
   *
   * @return void
   */
  public function _putresources()
  {
    $this->_putfonts();
    $this->_putimages();
    //Resource dictionary
    $this->offsets[2] = strlen($this->buffer);
    $this->_out('2 0 obj');
    $this->_out('<<');
    $this->_putresourcedict();
    $this->_out('>>');
    $this->_out('endobj');
  }

  /**
   * Outputs metadata information for the PDF document such as producer, title, subject,
   * author, keywords, creator, and creation date.
   *
   * @return void
   */
  public function _putinfo()
  {
    $this->_out('/Producer ' . $this->_textstring('FPDF ' . FPDF_VERSION));
    if (!empty($this->title))
      $this->_out('/Title ' . $this->_textstring($this->title));
    if (!empty($this->subject))
      $this->_out('/Subject ' . $this->_textstring($this->subject));
    if (!empty($this->author))
      $this->_out('/Author ' . $this->_textstring($this->author));
    if (!empty($this->keywords))
      $this->_out('/Keywords ' . $this->_textstring($this->keywords));
    if (!empty($this->creator))
      $this->_out('/Creator ' . $this->_textstring($this->creator));
    $this->_out('/CreationDate ' . $this->_textstring('D:' . @date('YmdHis')));
  }

  /**
   * Outputs the catalog dictionary to the PDF document.
   *
   * The catalog defines the root object of the document, specifying the document's page tree and viewer preferences,
   * such as zoom mode and layout mode.
   *
   * @return void
   */
  public function _putcatalog()
  {
    $this->_out('/Type /Catalog');
    $this->_out('/Pages 1 0 R');
    if ($this->ZoomMode == 'fullpage')
      $this->_out('/OpenAction [3 0 R /Fit]');
    elseif ($this->ZoomMode == 'fullwidth')
      $this->_out('/OpenAction [3 0 R /FitH null]');
    elseif ($this->ZoomMode == 'real')
      $this->_out('/OpenAction [3 0 R /XYZ null null 1]');
    elseif (!is_string($this->ZoomMode))
      $this->_out('/OpenAction [3 0 R /XYZ null null ' . ($this->ZoomMode / 100) . ']');
    if ($this->LayoutMode == 'single')
      $this->_out('/PageLayout /SinglePage');
    elseif ($this->LayoutMode == 'continuous')
      $this->_out('/PageLayout /OneColumn');
    elseif ($this->LayoutMode == 'two')
      $this->_out('/PageLayout /TwoColumnLeft');
  }

  /**
   * Outputs the PDF header with the version information.
   *
   * @return void
   */
  public function _putheader()
  {
    $this->_out('%PDF-' . $this->PDFVersion);
  }

  /**
   * Adds the trailer section to the PDF document structure.
   *
   * @return void
   */
  public function _puttrailer()
  {
    $this->_out('/Size ' . ($this->n + 1));
    $this->_out('/Root ' . $this->n . ' 0 R');
    $this->_out('/Info ' . ($this->n - 1) . ' 0 R');
  }

  /**
   * Finalizes the document structure and outputs the final content.
   *
   * The method performs the following steps:
   * - Writes the document header.
   * - Writes all pages and resources.
   * - Creates and outputs metadata (Info dictionary).
   * - Creates and outputs the document catalog.
   * - Generates and writes the cross-reference table.
   * - Outputs the trailer and finalizes the document stream.
   *
   * @return void
   */
  public function _enddoc()
  {
    $this->_putheader();
    $this->_putpages();
    $this->_putresources();
    //Info
    $this->_newobj();
    $this->_out('<<');
    $this->_putinfo();
    $this->_out('>>');
    $this->_out('endobj');
    //Catalog
    $this->_newobj();
    $this->_out('<<');
    $this->_putcatalog();
    $this->_out('>>');
    $this->_out('endobj');
    //Cross-ref
    $o = strlen($this->buffer);
    $this->_out('xref');
    $this->_out('0 ' . ($this->n + 1));
    $this->_out('0000000000 65535 f ');
    for ($i = 1; $i <= $this->n; $i++)
      $this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));
    //Trailer
    $this->_out('trailer');
    $this->_out('<<');
    $this->_puttrailer();
    $this->_out('>>');
    $this->_out('startxref');
    $this->_out($o);
    $this->_out('%%EOF');
    $this->state = 3;
  }
//End of class
}

//Handle special IE contype request
if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
  header('Content-Type: application/pdf');
  exit;
}
