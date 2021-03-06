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
 * Interface for each class that wants to represent an image file.
 * 
 * @author Jan Ťulák<jan@tulak.me>
 * 
 */
interface IImage {

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @param type $bigSide	Size of the bigger side of image
     * @param bool $crop   If the image should be cropped to fit into a rectangle
     * @return string Relative URL for the thumbnail
     */
    public function getThumbnail($bigSide, $crop = TRUE);

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param type $bigSide	Size of the bigger side of image
     * @param bool $crop   If the image should be cropped to fit into a rectangle
     * @return string key for the thumbnail in cache space "thumbnails"
     */
    public function createThumbnail($bigSide, $crop = TRUE);

    /**
     * Return resolution of the image as a string in format AAAxBBB
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @return string 
     */
    public function getResolution();

    /**
     * Try to return an Exif data from the image.
     * If succeed, then return array with data.
     * If fails, return false.
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @return mixed
     */
    public function getExif();
}