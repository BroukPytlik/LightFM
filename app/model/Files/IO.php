<?php

/**
 * This file is part of LightFM web file manager.
 * 
 * @Copyright (c) 2013 Jan Tulak (http://tulak.me)
 * 
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace LightFM;

/**
 * 
 * 
 * @author Jan Ťulák<jan@tulak.me>
 * 
 */
abstract class IO extends \Nette\Object {

    /**
     * 	Smarter replacement of php is_file - go through symlinks
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $path
     * @return boolean
     */
    public static function is_file($path) {
	// at first skim through links
	if (is_link($path))
	    while (is_link($path = readlink($path)));

	if (is_file($path))
	    return true;

	return false;
    }

    /**
     * 	Smarter replacement of php is_dir - go through symlinks
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $path
     * @return boolean
     */
    public static function is_dir($path) {
	// at first skim through links
	if (is_link($path))
	    while (is_link($path = readlink($path)));

	if (is_dir($path))
	    return true;

	return false;
    }

    /**
     * Create hierarchical path from root to the given path 
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @param string $path
     * @return \LightFM\Node
     */
    public static function findPath($path) {
	return self::createPath($path, $path);
    }

    /**
     * Return an array of classes which implements an interface
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $interfaceName
     * @return array
     */
    public static function getImplementingClasses($interfaceName) {
	$cache = new \Nette\Caching\Cache($GLOBALS['container']->cacheStorage, 'interfaces');
	$implements = $cache->load($interfaceName);
	if ($implements === NULL) {
	    $implements = $cache->save($interfaceName, function() use ($interfaceName) {
			return \LightFM\IO::getImplementingClassesCompute($interfaceName);
		    });
	}
	return $implements;
    }

    /**
     * This is public only because in getImplementingClasses() it is called by callback.
     * Do not use directly.
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @param type $interfaceName
     * @return type
     */
    public static function getImplementingClassesCompute($interfaceName) {
	$classes = NULL;
	// At first find instance of robotLoader and get classes.
	foreach (\Nette\Loaders\RobotLoader::getLoaders() as $i => $loader) {
	    if ($loader instanceof \Nette\Loaders\RobotLoader) {
		$classes = $loader->getIndexedClasses();
		// robot loader is in nette only once, no need to search longer
		break;
	    }
	}

	// And then get all classes that implements the interface.
	// http://stackoverflow.com/questions/3993759/php-how-to-get-a-list-of-classes-that-implement-certain-interface
	$array = array_filter(
		array_keys($classes), function( $className ) use ( $interfaceName ) {
		    return in_array($interfaceName, class_implements($className));
		}
	);
	return $array;
    }

    /**
     * Return an array of classes which provides an file view
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @return type
     */
    public static function getFileModules() {
	return self::getImplementingClasses('LightFM\IFile');
    }

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $restOfPath
     * @return type
     */
    private static function createPath_explodePath($restOfPath) {
	// remove slash at the end
	if (substr($restOfPath, -1, 1) == '/')
	    $restOfPath = substr($restOfPath, 0, -1);

	// split the path to the actual node and to the rest
	if (strpos($restOfPath, '/') === FALSE) {
	    // there is no slash - so simply set
	    $dir = $restOfPath;
	    $restOfPath = "";
	} else if (substr($restOfPath, 0, 1) == '/') {
	    // there is a slash at the begining - so simply set 
	    $dir = "/";
	    $restOfPath = substr($restOfPath, 1);
	} else {
	    // there is a slash in the middle - split it
	    list($dir, $restOfPath) = explode('/', $restOfPath, 2);
	    $restOfPath = trim($restOfPath, '/');
	}
	return array($dir, $restOfPath);
    }

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $fullPath
     * @return \LightFM\Directory
     */
    private static function createPath_tryCreate_final($fullPath) {
	return new \LightFM\Directory($fullPath);
    }

    /**
     * Get correct class which represents the given path - already create an object.
     * Usage: 
     * $node = IO::getNodeType($file); // path from this system's root
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @param string $fullPath
     * @return \LightFM\classes
     * @throws \Nette\FatalErrorException
     */
    public static function createFileType($fullPath) {
	$cache = new \Nette\Caching\Cache($GLOBALS['container']->cacheStorage, 'files');
	$created = $cache->load($fullPath);
	if ($created === NULL) {
	    $created = $cache->save($fullPath, function() use ($fullPath) {
			return \LightFM\IO::createFileTypeCompute($fullPath);
		    }, array(
		\Nette\Caching\Cache::FILES => DATA_ROOT . '/' . $fullPath
	    ));
	}
	return $created;
    }

    /**
     * This is public only because in getImplementingClasses() it is called by callback.
     * Do not use directly.
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @param type $fullPath
     * @return \LightFM\top
     * @throws \Nette\FatalErrorException
     */
    public static function createFileTypeCompute($fullPath) {
	$classes = array();

	$modules = self::getFileModules();
	foreach ($modules as $class) {
	    if ($class::knownFileType(DATA_ROOT . $fullPath)) {
		// if the class know this filetype
		$classes[$class::getPriority()] = $class;
	    }
	}
	krsort($classes);

	if (count($classes) == 0)
	    throw new \Nette\FatalErrorException("No possible node typefound! Probably missing the default class LightFM\File.");

	$top = array_shift($classes);
	return new $top($fullPath);
    }

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $fullPath
     * @param string $fullDir
     * @param string $restOfPath
     * @param \LightFM\DirConfig $config
     * @return \LightFM\Directory
     */
    private static function createPath_tryCreate_folder($fullPath, $fullDir, $restOfPath, \LightFM\DirConfig $config) {
	$cache = new \Nette\Caching\Cache($GLOBALS['container']->cacheStorage, 'dirs');
	$created = $cache->load($fullDir);
	if ($created === NULL) {
	    $created = $cache->save($fullDir, function() use ($fullDir) {
			return new \LightFM\Directory($fullDir);
		    }, array(
		\Nette\Caching\Cache::FILES => scandir(DATA_ROOT . '/' . $fullDir)
	    ));
	}
	// recursively create rest of the path
	$created->usedChild = self::createPath($fullPath, $restOfPath, $config);
	$created->usedChild->Parent = $created;

	// save a child config for case of emptying of $created
	if ($created->usedChild->dummy) {
	    // if the subdir is blacklisted, replace by empty node
	    $created = new \LightFM\Directory(NULL);
	}


	return $created;
    }

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $fullPath
     * @param string $fullDir
     * @param string $restOfPath
     * @param \LightFM\DirConfig $config
     * @return \LightFM\Directory
     */
    private static function createPath_tryCreate($fullPath, $fullDir, $restOfPath, \LightFM\DirConfig $config) {


	if ($restOfPath == "") {
	    // We are at the end of the path

	    if (self::is_file(DATA_ROOT . '/' . $fullPath)) {
		// the end of the path is a !file! in this dir
		$created = self::createFileType($fullPath);
	    } else {

		$created = self::createPath_tryCreate_final($fullPath);
	    }
	} else {
	    // this dir is not the final node, there is a subdir
	    // create node for this dir
	    $created = self::createPath_tryCreate_folder($fullPath, $fullDir, $restOfPath, $config);
	}
	return $created;
    }

    /**
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $fullPath	    full path relatively to the data root
     * @param string $restOfPath    rest of patch from this node
     * @param \LightFM\DirConfig $parentsConfig	    config of parent node, or null if none parent
     * @return \LightFM\Directory
     */
    public static function createPath($fullPath, $restOfPath, \LightFM\DirConfig $parentsConfig = NULL) {
	// Remove slashes from begining and end (if any)
	// and get top dir from the path

	list($dir, $restOfPath) = self::createPath_explodePath($restOfPath);

	// create path to this dir - remove the rest from the full path to get 
	// path to this dir

	$restLen = strlen($restOfPath);
	if ($restLen) {
	    $fullDir = substr($fullPath, 0, -($restLen ));
	} else {
	    $fullDir = $fullPath;
	}
	$config = new \LightFM\DirConfig($fullDir);

	try {
	    // load config
	    // and inherite
	    $config->inherite($parentsConfig);
	    $created = self::createPath_tryCreate($fullPath, $fullDir, $restOfPath, $config);
	} catch (\Nette\FileNotFoundException $e) {
	    // file not found - create empty node
	    $created = new \LightFM\Directory(NULL);
	}

	if (empty($created->Password)) {
	    // if subdirs do not needs a password, check this one and set if needed
	    $created->Password = $config->getAccessPassword();
	}

	if ($config->isBlacklisted($dir) || $config->isBlacklisted(DATA_ROOT . "/$dir") ||
		$config->isBlacklisted(DATA_ROOT . $fullDir)) {
	    // if the file/dir is blacklisted, replace by empty node
	    $created = new \LightFM\Directory(NULL);
	}


	// assign the config for this directory
	$created->Config = $config;

	return $created;
    }
    
    
   

}