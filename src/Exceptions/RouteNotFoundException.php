<?php

namespace Lsr\Core\Requests\Exceptions;

use Exception;
use Lsr\Interfaces\RequestInterface;

class RouteNotFoundException extends Exception
{

	public function __construct(public RequestInterface $request) {
		parent::__construct('Route "'.$this->request->getMethod()->value.' '.implode('/', $this->request->getPath()).'" was not found');
	}

}