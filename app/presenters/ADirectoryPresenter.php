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
 * @property-read string $DisplayName Displayed name of the presenter
 * @property-read array $AllDirectoryPresenters list of all presenters implementing IDirectoryPresenter
 */
abstract class ADirectoryPresenter extends BasePresenter implements IDirectoryPresenter {

    const ORDER_FILENAME = 'filename';
    const ORDER_SUFFIX = 'suffix';
    const ORDER_SIZE = 'size';
    const ORDER_DATE = 'date';
    const ORDER_ASC = FALSE;
    const ORDER_DESC = TRUE;

    /**
     * Column for sorting
     * @persistent
     * @var string
     */
    public $orderBy = self::ORDER_FILENAME;

    /**
     * Way of sorting - asc/desc
     * @persistent
     * @var boolean
     */
    public $orderReversed = self::ORDER_ASC;

    /**
     * 	List of known interfaces for displaying files
     * @var array
     */
    protected $knownInterfaces = array('IDirectory');
    protected $allDirectoryPresenters;

    /**
     * Displayed name of the presenter
     * @var String
     */

    const DISPLAY_NAME = 'N/a';

    /**
     * Order of the presenters in menu - 0 is the most left
     * @var int
     */
    const ORDER = 999;

    /**
     * 	Return list of all presenters that implements IDirectoryPresenter
     * @return array
     */
    public function getAllDirectoryPresenters() {
	if ($this->allDirectoryPresenters == NULL) {
	    $presenters = \LightFM\IO::getImplementingClasses('IDirectoryPresenter');
	    // remove this abstract class
	    if (($key = array_search('ADirectoryPresenter', $presenters)) !== false) {
		unset($presenters[$key]);
	    }
	    $this->allDirectoryPresenters = array();
	    foreach ($presenters as $presenter) {
		$this->allDirectoryPresenters[$presenter::ORDER] = array(
		    'name' => $presenter::DISPLAY_NAME,
		    'presenter' => preg_replace('/Presenter$/', '', $presenter)
		);
	    }
	    ksort($this->allDirectoryPresenters);
	}
	return $this->allDirectoryPresenters;
    }

    public function renderDefault() {
	// save the accessible presenters to a template variable
	$this->template->directoryPresenters = $this->getAllDirectoryPresenters();
	$this->template->actualPresenter = preg_replace('/Presenter$/', '', get_class($this));


	// send to template ---------
	$this->template->orderReversed = $this->orderReversed;
	$this->template->orderBy = $this->orderBy;

	// create backpath
	$path = $this->getPath($this->root);
	$this->template->path = $path;
	// backpath
	$this->template->backpath = '/';
	$i = count($path);
	foreach ($path as $item) {
	    // instead of the root we want only '/' and we want to ommit 
	    // the last item in the path
	    if ($i-- > 1 && $i < count($path) - 1) {
		$this->template->backpath .= $item;
	    }
	}
    }

    /**
     * sort the array acording of given parameters
     * @param array $list
     * @param string $orderBy
     * @param booolean $order
     * @return array 
     */
    protected function sortList(array &$list, $orderBy, $order) {
	$t = $this;
	usort($list, function(\LightFM\Node $a, \LightFM\Node $b) use($orderBy, $order, $t) {
		    $result = 0;
		    switch ($orderBy) {
			case $t::ORDER_FILENAME:
			    $result = strcmp($a->Name, $b->Name);
			    break;
			case $t::ORDER_SUFFIX:
			    if ($a instanceof \LightFM\IFile && $b instanceof \LightFM\IFile) {
				$result = strcmp($a->Suffix, $b->Suffix);
			    }
			    break;
			case $t::ORDER_SIZE:
			    if ($a->Size != $b->Size) {
				$result = ($a->Size < $b->Size) ? -1 : 1;
			    }
			    break;
			case $t::ORDER_DATE:
			    if ($a->Date != $b->Date) {
				$result = ($a->Date < $b->Date) ? -1 : 1;
			    }
			    break;
		    }

		    if ($order == $t::ORDER_DESC) {
			// if it is revered order, then reverse it
			$result *=-1;
		    }
		    return $result;
		});
    }

    /**
     * This function manages ZIP download.
     * It takes the actual dir from $this->viewed and list of filenames in
     * POST "list". It will check if these files exists in viewed dir and
     * then call zip creation.
     * It will return an JSON response with url of the zip file,
     * or an HTTP error.
     * 
     * @param array $files
     */
    public function actionDownloadZip() {
	parent::actionDefault();

	$response = array();

	$httpRequest = $GLOBALS['container']->httpRequest;
	$httpResponse = $GLOBALS['container']->httpResponse;
	$list = $httpRequest->getPost('list');
	// now we have list of items to package
	
	//$list = array('data1');
	$content = array_merge($this->viewed->SubdirsNames, $this->viewed->FilesNames);
	try {
	    // test for all wanted files and dirs if they are here
	    foreach ($list as $item) {
		if (!in_array($item, $content)) {
		    // item wasn't found - set error and break
		    $response['error'] = "File >> " . $item . " << in >> " . $this->viewed->Path . " << wasn't found!";
		    throw new Nette\FileNotFoundException;
		}
	    }
	    
	    $response['path'] = \LightFM\IO::getZip($this->viewed->FullPath,$list);
	    
	} catch (\Nette\FileNotFoundException $e) {
	    //  files not found
	    $httpResponse->setCode(\Nette\Http\Response::S404_NOT_FOUND);
	    \Nette\Diagnostics\Debugger::log($response['error'] );
	} catch (Exception $e){
	    // exceptions from creating the archive
	    if($e->getCode() !=  \Zip::ZIP_ERROR){
		throw $e;
	    }
	    $httpResponse->setCode(Nette\Http\Response::S500_INTERNAL_SERVER_ERROR);
	    $response['error']  = "An Error Occured In >> " . $this->viewed->Path . " << When Creating Archive.";
	    \Nette\Diagnostics\Debugger::log($response['error'] );
	}
	
	
	// TODO Change file name

	$this->sendResponse(new Nette\Application\Responses\JsonResponse($response));
    }

}

