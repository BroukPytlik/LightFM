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
 * 
 * @serializationVersion 1
 */
 class ArchiveFile extends File {
     
     
     // overwriting parent's value
    protected $iconName = 'file-archive';
     
    // overwrite from parent
    protected static $priority = 0;
    
    
    public static function knownFileType($file) {
	return \LightFM\Filetypes::isArchive($file);
    }

    
    
    
}