<?php

use Nette\Diagnostics\Debugger;

/**
 * Error presenter.
 * 
 * @author Jan Ťulák<jan@tulak.me>
 */
class ErrorPresenter extends BasePresenter {

    /**
     * Only for ajax
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * 
     */
    public function beforeRender() {
	parent::beforeRender();

	if ($this->isAjax()) {
	    $this->invalidateControl('header');
	    $this->invalidateControl('subtitle');
	    $this->invalidateControl('flashes');
	    $this->invalidateControl('content');
	}
    }

    /**
     * Test for known error codes and select correct template
     * 
     * @author Jan Ťulák<jan@tulak.me>
     * @author Nette sandbox
     * 
     * @param  Exception
     * @return void
     */
    public function renderDefault($exception) {
	if ($this->isAjax()) { // AJAX request? Just note this error in payload.
	    $this->payload->error = TRUE;
	    $this->terminate();
	} elseif ($exception instanceof Nette\Application\BadRequestException) {
	    $code = $exception->getCode();
	    // load template 403.latte or 404.latte or ... 4xx.latte
	    $this->setView(in_array($code, array(401, 403, 404, 405, 410, 500)) ? $code : '4xx');
	    $this->template->message = $exception->getMessage();
	    // log to access.log
	    Debugger::log("HTTP code $code: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}", 'access');
	} else {
	    $this->setView('500'); // load template 500.latte
	    Debugger::log($exception, Debugger::ERROR); // and log exception
	}
    }

}
