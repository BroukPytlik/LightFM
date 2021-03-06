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
 * @property-read  string $IconName Name for icon file
 * @property-read  string $Suffix   File suffix
 * @property string $MimeType
 * @property-read string $Hash Hash of the file
 * 
 * @serializationVersion 4
 */
class File extends Node implements IFile,  IMovable, IRenameable, IDeletable {

    /**
     * 	The DEFAULT presenter called for this file
     * 	Note: If the given presenter will not know any interface which this
     * class is implementing, it will lead to a infinite redirecting!
     * @var string
     */
    protected $presenter = 'File';

    /**
     * 	css class for the node
     * @var string 
     */
    protected $iconName = null;

    /**
     * Array of some mimetypes and corresponding classes for icons
     * @var array
     */
    private $knownIcons = array(
	'application/pdf'=>'file-pdf',
	'application/msword'=>'file-doc'
    );
    
    /**
     * 	Contain mimetype of this file
     * @var string
     */
    protected $mimetype = '';

    /**
     * Priority of this class
     * @var int 
     */
    protected static $priority = -1000;

    /**
     * 	Suffix of this file
     * @var string
     */
    protected $suffix;

    /**
     * 	Hash of the file
     * @var string 
     */
    protected $hash;

    public function __construct($path) {
	parent::__construct($path);
	$fileparts = pathinfo($path);
	// split to suffix and name
	$this->suffix = !empty($fileparts['extension']) ? $fileparts['extension'] : '';
	$this->name = $fileparts['filename'];
	if (strlen($this->name) == 0) {
	    // files like .htaccess
	    $this->name = '.' . $this->suffix;
	    $this->suffix = "";
	}
	return $this;
    }

    public function getMimeType() {
	if ($this->mimetype == NULL) {
	    $this->mimetype = \LightFM\Filetypes::getMimeType($this->FullPath);
	}
	return $this->mimetype;
    }

    public function setMimeType($mimetype) {
	$this->mimetype = $mimetype;
	return $this;
    }

    public function getIconName() {
	if($this->iconName==NULL){
	    if(isset($this->knownIcons[$this->getMimeType()])){
		$this->iconName = $this->knownIcons[$this->getMimeType()];
	    }else{
		$this->iconName = "";
	    }
	}
	return $this->iconName;
    }

    public function getSuffix() {
	return $this->suffix;
    }

    public function getTemplateName() {
	return "";
    }

    public static function getPriority() {
	return static::$priority;
    }

    public static function knownFileType($file) {
	// generic file - know everything
	return TRUE;
    }

    public function getHash() {
	if ($this->hash == NULL) {
	    $this->hash = sha1_file($this->getFullPath());
	}
	return $this->hash;
    }
    
    /**
     * 
     * @param type $full
     * @return string
     */
    public function getName($full = FALSE) {
	if($full && $this->getSuffix()){
	    return parent::getName($full).'.'.$this->getSuffix();
	}else{
	    return parent::getName($full);
	}
    }




    /* ********************************************************************** */
    /*                         IMovable                                       */
    /* ********************************************************************** */
    
    public function move($targetDir) {
	
	$newPath="$targetDir/".$this->getName(TRUE);
	if(file_exists($newPath)){
		throw new \Exception('DIR_ALREADY_EXISTS',self::NAME_ALREADY_EXISTS);
	}
	
	if(!rename($this->getFullPath(),$newPath)){
	    @chmod($targetDir, 0777);
	    @chmod($this->getFullPath(), 0777);
	    if(!rename($this->getFullPath(),$newPath)){
		throw new \Nette\Application\ForbiddenRequestException;
	    }
	}
    }
    
    /* ********************************************************************** */
    /*                         IDeletable                                     */
    /* ********************************************************************** */
    
    public function delete() {
	//throw new \Nette\NotImplementedException;
	@chmod($this->getFullPath(), 0777);
	if(!@unlink($this->getFullPath())){
	    throw new \Nette\Application\ForbiddenRequestException;
	}
    }
    
    /* ********************************************************************** */
    /*                         IRenameable                                    */
    /* ********************************************************************** */
    
    public function rename($newName) {
	if(file_exists($this->getParent()->getFullPath()."/$newName")){
		throw new \Exception('FILE_ALREADY_EXISTS',self::NAME_ALREADY_EXISTS);
	}
	
	if(!rename($this->getFullPath(),$this->getParent()->getFullPath()."/$newName")){
	    @chmod($this->getParent()->getFullPath(), 0777);
	    @chmod($this->getFullPath(), 0777);
	    if(!rename($this->getFullPath(),$this->getParent()->getFullPath()."/$newName")){
		throw new \Nette\Application\ForbiddenRequestException;
	    }
	}
    }
    
    

}