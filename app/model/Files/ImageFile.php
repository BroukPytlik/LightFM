<?php

/**
 * This file is part of LightFM web file manager.
 * 
 * Copyright (c) 2013 Jan Tulak (http://tulak.me)
 * 
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace LightFM;

/**
 * 
 * @author Jan Ťulák<jan@tulak.me>
 * 
 * @property-read string $Resolution Image resolution
 * @property-read string $Thumbnail Path to the thumbnail
 * @property-read mixed  $Exif
 * 
 * @serializationVersion 1
 */
class ImageFile extends File implements IImage {

    /**
     * 	The presenter called for this file
     * @var string
     */
    protected $presenter = 'Image';
    // overwriting parent's value
    protected $iconName = 'file-image';

    /**
     * hash of name and path and some other informations
     * @var string
     */
    private $_hash;

    /**
     * 	path relatively from data root to a folder in which is the image thumb
     * @var string 
     */
    private $_thumbnailDirectory;

    /**
     * Path from data_root to the file
     * @var String
     */
    private $_thumbnailPath;

    /**
     * if the image is somethign what we can edit in php
     * @var bool
     */
    protected $isUnknown = true;
    // overwrite from parent
    protected static $priority = 0;

    /**
     * subdir in DATA_ROOT/DATA_TEMP
     */

    const imagesDir = '/thumbnails/';
    /**
     * Suffix for thumb files
     */
    const thumbSuffix = '.jpg';

    /**
     * 	Nette\Image object
     * @var Nette\Image
     */
    private $image;

    /**
     * Array with exif informations.
     * 
     * @var array
     */
    private $exif;

    public function __construct($path) {
	parent::__construct($path);
	$mime = \LightFM\Filetypes::getMimeType(DATA_ROOT . $path);
	if ($mime == 'image/png' || $mime == 'image/jpeg' || $mime == 'image/gif') {
	    $this->isUnknown = FALSE;
	}
    }

    public function getTemplateName() {
	return "default";
    }

    protected function getThumbnailPath() {
	if ($this->_thumbnailDirectory == NULL) {
	    $this->_thumbnailDirectory = DATA_TEMP . self::imagesDir . $this->Parent->Path . '/';
	}
	return $this->_thumbnailDirectory;
    }

    public function getThumbnail($bigSide, $crop = TRUE) {
	if ($this->isUnknown) {
	    return '';
	}
	if ($this->_thumbnailPath == NULL) {
	    $cropped = $crop ? '_crop' : '';
	    $this->_thumbnailPath = '/' . $this->getThumbnailPath() . $this->getHash() . '_' . $bigSide . $cropped . self::thumbSuffix;
	}
	return $this->_thumbnailPath;
    }

    /**
     * Return nette image, or null if file is an unknown image
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @return \Nette\Image
     */
    protected function getImage() {
	if ($this->isUnknown) {
	    return NULL;
	}
	if ($this->image == NULL) {
	    $this->image = \Nette\Image::fromFile($this->FullPath);
	}
	return $this->image;
    }

    public static function knownFileType($file) {
	return \LightFM\Filetypes::isImage($file);
    }

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param type $bigSide	Size of the bigger side of image
     * @param bool $crop   If the image should be cropped to fit into a rectangle
     * @return string string encoded data of the image
     */
    public function createThumbnail($bigSide, $crop = TRUE) {
	if ($this->isUnknown) {
	    return '';
	}

	$cropped = $crop ? '_crop' : '';

	$relativePath = '/' . $this->getThumbnailPath() . $this->getHash() . '_' . $bigSide . $cropped . self::thumbSuffix;
	$cache = new \Nette\Caching\Cache($GLOBALS['container']->cacheStorage, 'thumbnails');


	if ($cache->load($relativePath) == NULL) {
	    $t = $this;
	    $cache->save($relativePath, function() use ($t, $bigSide, $crop) {
			// it is as a callback, because this way it prevets concurent generating
			\Nette\Diagnostics\Debugger::log('Generating thumbnail for ' . $t->getPath());
			// create thumbnail
			$image = \Nette\Image::fromFile($t->FullPath);
			
			
			if ($crop) {
			    $image->resize($bigSide, $bigSide, $image::EXACT|$image::SHRINK_ONLY);
			} else {
			    $image->resize($bigSide, $bigSide,$image::SHRINK_ONLY);
			}
			    // make so big image
			    $blank = \Nette\Image::fromBlank($bigSide, $bigSide, \Nette\Image::rgb(0,0,0));
			    $blank->place($image, '50%', '50%');
			    $image = $blank;
			
			$image->sharpen();
			return (string) $image;
		    }, array(
		\Nette\Caching\Cache::EXPIRE => '+ 2 weeks',
		\Nette\Caching\Cache::SLIDING => TRUE,
	    ));

	}
	return $relativePath;
    }

    /**
     * Return resolution of the image as a string in format AAAxBBB
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @return string 
     */
    public function getResolution() {
	if ($this->isUnknown) {
	    return 'Unknown';
	}
	list($w, $h) = getimagesize($this->getFullPath());
	return $w . 'x' . $h;
    }

    public function getExif() {
	if (!function_exists('exif_read_data')) {
	    return FALSE;
	}
	if ($this->exif == NULL) {
	    $this->exif = @exif_read_data($this->FullPath);
	    if (isset($this->exif["GPSLongitude"])) {
		$this->exif['CUSTOM_GPS_LON'] = $this->getGps($this->exif["GPSLongitude"], $this->exif['GPSLongitudeRef']);
		$this->exif['CUSTOM_GPS_LAT'] = $this->getGps($this->exif["GPSLatitude"], $this->exif['GPSLatitudeRef']);
	    } else if (isset($this->exif["GPS"])) {
		$this->exif['CUSTOM_GPS_LON'] = $this->getGps($this->exif["GPS"]["GPSLongitude"], $this->exif["GPS"]['GPSLongitudeRef']);
		$this->exif['CUSTOM_GPS_LAT'] = $this->getGps($this->exif["GPS"]["GPSLatitude"], $this->exif["GPS"]['GPSLatitudeRef']);
	    }
	}
	return $this->exif;
    }

    /**
     * @author http://stackoverflow.com/questions/2526304/php-extract-gps-exif-data
     * 
     * @param type $exifCoord
     * @param type $hemi
     * @return type
     */
    private function getGps($exifCoord, $hemi) {

	$degrees = count($exifCoord) > 0 ? $this->gps2Num($exifCoord[0]) : 0;
	$minutes = count($exifCoord) > 1 ? $this->gps2Num($exifCoord[1]) : 0;
	$seconds = count($exifCoord) > 2 ? $this->gps2Num($exifCoord[2]) : 0;

	$flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

	return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }

    /**
     * @author http://stackoverflow.com/questions/2526304/php-extract-gps-exif-data
     * 
     * @param type $coordPart
     * @return int
     */
    private function gps2Num($coordPart) {

	$parts = explode('/', $coordPart);

	if (count($parts) <= 0)
	    return 0;

	if (count($parts) == 1)
	    return $parts[0];

	return floatval($parts[0]) / floatval($parts[1]);
    }

}