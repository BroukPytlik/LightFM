<?php

/**
 * This file is part of LightFM web file manager.
 * 
 * Copyright (c) 2013 Jan Tulak (http://tulak.me)
 * 
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

/**
 * Description of ADirectoryPresenter
 *
 */
abstract class ADirectoryPresenter extends BasePresenter  implements IDirectoryPresenter {

    
    protected $knownInterfaces = array('IDirectory');
    
    public function actionDefault() {
	parent::actionDefault();

	// If this is not a directory, then go to another presenter
	if (!($this->viewed instanceof LightFM\Directory)) {
	    
	    //$this->forward('File:default', array('path' => $this->path));
	}
    }
    
}

