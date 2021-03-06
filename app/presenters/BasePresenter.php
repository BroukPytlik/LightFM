<?php

/**
 * Base presenter for all application presenters.
 * 
 * @author Jan Ťulák<jan@tulak.me>
 * 
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter {
    // TODO file manipulation
    // TODO replace jqueryui
    // TODO resizing and scrolling
    // TODO maybe Images - addd a possibility to make a comment/note to them - use nette cache
    

    /**
     * List of all know interfaces this presenter can show.
     * Eg. IText for only text files, IDirectory for directories, IFile for 
     * all files or INode for everything.
     * 
     * @var array 
     */
    protected $knownInterfaces = array();

    /**
     * @persistent
     */
    public $path = "/";

    /**
     * 	Root directory
     * @var LightFM\Directory 
     */
    protected $root;

    /**
     * 	item that user wants to view
     * @var LightFM\Node
     */
    protected $viewed;

    /**
     * If show hidden files/foldes
     * @var bool
     */
    protected $showHidden = false;

    /**
     * Verify the given path and test user related things (hidden files,
     * sidebar...)
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     */
    public function startup() {
	parent::startup();
	\Stopwatch::start('BasePresenter');

	$this->template->isAjax = $this->isAjax();
	$this->template->noAjax = false;
	$this->template->showFileOpsForm = false;

	// if path is empty, it means it is a root
	if (strlen($this->path) == 0)
	    $this->path = '/';
	
	
	if (!($this instanceof ErrorPresenter)){
	    // test for forbidden "../" and similar
	    $this->path = $this->verifyPath($this->path);
	    // fill viewed
	    $this->loadFiles();
	}

	$this->template->user = $this->getUser();

	$this->showHidden = $this->getHttpRequest()->getCookie('hiddenFiles');
	// set sidebar showing
	$this->template->showSidebar = false;
	//dump($this->getUser());
	
	
    }

    public function afterRender() {
	parent::afterRender();
	
	//  take care about the archive cache
	\LightFM\ArchiveCache::trasher();
    }
    
    /**
     * Only for ajax 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     */
    public function beforeRender() {
	parent::beforeRender();
	if ($this->isAjax()) {
	    $this->invalidateControl('title');
	    $this->invalidateControl('sidebar');
	}

	\Stopwatch::stop('BasePresenter');
    }

    /**
     * Sign out the user and delete hiddenFiles cookie
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     */
    public function handleSignOut() {
	$this->getUser()->logout();
	$this->getHttpResponse()->deleteCookie('hiddenFiles');
	$this->flashMessage('You have been signed out.');

	if ($this->isAjax()) {
	    $this->invalidateControl('header');
	    $this->invalidateControl('title');
	    $this->invalidateControl('flashes');
	    $this->invalidateControl('sidebar');
	    $this->invalidateControl('content');
	} else {
	    $this->redirect('List:');
	}
    }

    /**
     * Will select presenter for the file.
     * If we are already in it, will do nothing,
     * else it will do a redirect.
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * 
     * @param \LightFM\Node $file
     */
    protected function selectCorrectPresenter(\LightFM\Node $file) {
	// test if this presenter knows the file
	// and if knows, do nothing
	foreach ($this->knownInterfaces as $interface) {
	    if ($file instanceof $interface) {
		return;
	    }
	    $interface = "\\LightFM\\" . $interface;
	    if ($file instanceof $interface) {
		return;
	    }
	}
	// else redirect to the default one
	$this->redirect($this->viewed->Presenter . ':default');
    }

    /**
     * Fill $this->path, viewed and root with data
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @throws Nette\Application\BadRequestException
     */
    protected function loadFiles() {

	// get path
	$this->root = LightFM\IO::findPath($this->path);

	// get the item
	$this->viewed = $this->template->viewed = $this->getLastNode($this->root);

	$this->template->isOwner = $this->viewed->isOwner($this->getUser()->id) && $this->getUser()->isLoggedIn();
	
	if ($this->root->Dummy) {
	    throw new Nette\Application\BadRequestException($this->path);
	}
    }
    
    /**
     * Test if this is a presenter that can display the viewed item
     */
    protected function testPresenter(){

	if (!($this instanceof SettingsPresenter)) {
	    // if we are in settings, we do not need to change presenter or check perms

	    try {
		$this->testAccess($this->viewed);
		$this->selectCorrectPresenter($this->viewed);
	    } catch (Nette\Application\ForbiddenRequestException $e) {
		//$this->redirect('Settings:password', array('view' => $this->name, 'req' => (string) $this->getHttpRequest()->getUrl()));
		$this->redirect('Settings:password');
	    }
	}
    }

    /**
     * Test for access rights for current user/guest.
     * If the user can't has valid cookie, an exception will be thrown
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param \LightFM\Node $node
     * @throws Nette\Application\ForbiddenRequestException
     */
    protected function testAccess($node) {
	//\Nette\Diagnostics\Debugger::barDump($node);
	$tested = $node;

	if ($this->viewed->isOwner($this->getUser()->getId()) && $this->getUser()->isLoggedIn()) {
	    // if owner, do nothing, owner has access
	    return;
	}

	while ($tested !== NULL && empty($tested->Password)) {
	    // try to find the clossest password
	    $tested = $tested->Parent;
	}
	if ($tested === NULL) {
	    // if null, there is no password needed, so stop
	    return;
	}

	// Here it gets only if some password is needed, but still the user can
	// be owner, or can know the password.
	if (!Authenticator::hasAccess($tested->Password, $tested->Path)) {
	    throw new Nette\Application\ForbiddenRequestException;
	}
    }

    /**
     * parse path, find the root and so..
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     */
    public function actionDefault() {
	// if we are in error presenter, do nothing
	if ($this instanceof ErrorPresenter)
	    return;


	try {
	    $this->testPresenter();
	} catch (Nette\Application\ForbiddenRequestException $e) {
	    //$this->forward('Error:default', array('exception' => $e));
	} catch (Nette\Application\BadRequestException $e) {
	    dump($this->root);
	    $this->forward('Error:default', array('exception' => $e));
	    //throw $e;
	}
    }

    /**
     * Return the last child from the given node
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param LightFM\Node $node
     * @return LightFM\Node 
     */
    protected function getLastNode(LightFM\Node $node) {
	$last = $node;
	while ($last instanceof \LightFM\Directory) {
	    if ($last->UsedChild == NULL)
		break;
	    $last = $last->UsedChild;
	}
	return $last;
    }

    /**
     * Return verified and corrected patch (removed "//" and so)
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param string $path
     * @throw Nette\Application\ForbiddenRequestException
     * @return string
     */
    protected function verifyPath($path) {
	if (preg_match('/(^\.\.\/)|(\/\.\.$)|(\/\.\.\/)/', $path) != FALSE)
	    throw new Nette\Application\ForbiddenRequestException;
	$path = preg_replace('/\/\/+/', '/', $path); // remove double slashes
	$path = preg_replace('/(?!^)\/$/', '', $path); // remove trailing slash
	return $path;
    }

    /**
     * Will remove hidden items from the array
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param type $arr
     */
    protected function removeHidden(&$arr) {
	foreach ($arr as $key => $item) {
	    if ($item->Hidden)
		unset($arr[$key]);
	}
    }

    /**
     * Return path in given node in array where URI is as a key and dir name
     * is as a value. The root is on first place.
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     * @param LightFM\Node $node
     * @return array
     */
    protected function getPath(LightFM\Node $node) {
	$path = array();
	$last = $node;
	while ($last instanceof \LightFM\Directory) {
	    $path[$last->Path] = $last->name . '/';
	    if ($last->usedChild == NULL)
		break;
	    $last = $last->usedChild;
	}
	return $path;
    }

}
