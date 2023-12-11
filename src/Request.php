<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Requests;


use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\Traits\RequestTrait;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;
use Lsr\Logging\Logger;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface, \Psr\Http\Message\RequestInterface
{

	use RequestTrait;

	public RequestMethod $type = RequestMethod::GET;
	/** @var string[] */
	public array $path = [];
	/** @var array<string, mixed> */
	public array $query = [];
	/** @var array<string, mixed> */
	public array  $params = [];
	public string $body   = '';
	/** @var array<string, string|numeric|array> */
	public array $post = [];
	/** @var array<string, string|numeric|array> */
	public array $get = [];
	/** @var array<string, string|numeric|array> */
	public array $request = [];
	/** @var array<string|int, string> */
	public array $errors = [];
	/** @var string[] */
	public array $notices = [];
	/** @var array<string|int, string> */
	public array $passErrors = [];
	/** @var string[] */
	public array $passNotices = [];
	/** @var string[]|string[][] */
	public array              $headers         = [];
	public ?RequestInterface  $previousRequest = null;
	protected ?RouteInterface $route           = null;

	public function __construct(
		public UriInterface $uri,
		public string       $httpVersion = '1.1'
	) {
		$logger = new Logger(LOG_DIR, 'request');
		$query = $uri->getPath();

		// If the request is made to a PHP file, get the query data from GET params
		if (str_ends_with($query, '.php')) {
			$query = ($_GET['p'] ?? []);
		}

		// Get headers
		/**
		 * @var array<string, string>|false $headers
		 */
		$headers = function_exists('apache_request_headers') ? apache_request_headers() : false;
		if (is_array($headers)) {
			$this->headers = $headers;
		}
		else {
			// Fallback to $_SERVER super global
			$this->headers = [
				'Accept'          => $_SERVER['HTTP_ACCEPT'] ?? '*/*',
				'Accept-Encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'utf-8',
				'Accept-Language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
				'Host'            => $_SERVER['HTTP_HOST'] ?? 'localhost',
				'User-Agent'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'Referrer'        => $_SERVER['HTTP_REFERER'] ?? '',
				'Connection'      => $_SERVER['HTTP_CONNECTION'] ?? '',
			];
		}

		// Request method
		$this->type = RequestMethod::tryFrom($_SERVER['REQUEST_METHOD'] ?? '') ?? RequestMethod::GET;

		// Parse path
		if (is_array($query)) {
			$this->parseArrayQuery($query);
		}
		else {
			$this->parseStringQuery($query);
		}
		$this->query = array_filter($_GET, static function($key) {
			return $key !== 'p';
		},                          ARRAY_FILTER_USE_KEY);

		// Find route
		$this->route = Router::getRoute($this->type, $this->path, $this->params);

		if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
			$this->body = file_get_contents("php://input");
			if ($this->type === RequestMethod::POST) {
				if (!empty($this->body)) {
				$_POST = array_merge($_POST, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				}
				$_REQUEST = array_merge($_REQUEST, $_POST);
			}
			elseif ($this->type === RequestMethod::UPDATE || $this->type === RequestMethod::PUT) {
				if (!empty($this->body)) {
				$this->post = array_merge($this->post, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				}
				$_REQUEST = array_merge($_REQUEST, $this->post);
			}
			elseif ($this->type === RequestMethod::GET) {
				if (!empty($this->body)) {
				$_GET = array_merge($_GET, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				}
				$_REQUEST = array_merge($_REQUEST, $_GET);
			}
		}
		$this->post = $_POST;
		$this->get = $_GET;
		$this->request = $_REQUEST;
	}

	public function getPath() : array {
		return $this->path;
	}

	/**
	 * Parse split URL path
	 *
	 * @param string[] $query
	 *
	 * @return void
	 */
	protected function parseArrayQuery(array $query) : void {
		$this->path = array_map('strtolower', $query);
	}

	/**
	 * Parse URL path
	 *
	 * @param string $query
	 *
	 * @return void
	 */
	protected function parseStringQuery(string $query) : void {
		$url = parse_url($query);
		$filePath = urldecode(ROOT.substr($url['path'], 1));
		if (file_exists($filePath) && is_file($filePath)) {
			$extension = pathinfo($filePath, PATHINFO_EXTENSION);
			if ($extension !== 'php') {
				/** @noinspection PhpComposerExtensionStubsInspection */
				$mime = match ($extension) {
					'css' => 'text/css',
					'scss' => 'text/x-scss',
					'sass' => 'text/x-sass',
					'csv' => 'text/csv',
					'css.map', 'js.map', 'map', 'json' => 'application/json',
					'js' => 'text/javascript',
					default => mime_content_type($filePath),
				};
				header('Content-Type: '.$mime);
				exit(file_get_contents($filePath));
			}
		}
		$this->parseArrayQuery(array_filter(explode('/', $url['path'] ?? ''), static function($a) {
			return !empty($a);
		}));
	}

	/**
	 * @return RouteInterface|null
	 */
	public function getRoute() : ?RouteInterface {
		return $this->route;
	}

	/**
	 * @return void
	 * @throws RouteNotFoundException
	 */
	public function handle() : void {
		if (isset($this->route)) {
			$this->route->handle($this);
			return;
		}
		throw new RouteNotFoundException($this);
	}

	public function __get($name) {
		return $this->params[$name] ?? null;
	}

	public function __set($name, $value) {
		$this->params[$name] = $value;
	}

	public function __isset($name) {
		return isset($this->params[$name]);
	}

	/**
	 * Check if current page is requested using AJAX call
	 *
	 * @return bool
	 */
	public function isAjax() : bool {
		return
			(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
			(!empty($_SERVER['X_REQUESTED_WITH']) && strtolower($_SERVER['X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize() : array {
		$vars = get_object_vars($this);
		if (isset($this->route) && !empty($this->route->getName())) {
			$vars['routeName'] = $this->route->getName();
		}
		return $vars;
	}

	/**
	 * Get IP of the client requesting
	 *
	 * @return string
	 */
	public function getIp() : string {
		return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
	}

	public function getMethod() : RequestMethod {
		return $this->type;
	}

	/**
	 * Get a POST parameter from the request with a specified default fallback value
	 *
	 * @param string     $name
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	public function getPost(string $name, mixed $default = null) : mixed {
		return $this->post[$name] ?? $default;
	}

	/**
	 * Get a GET parameter from the request with a specified default fallback value
	 *
	 * @param string     $name
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	public function getGet(string $name, mixed $default = null) : mixed {
		return $this->request[$name] ?? $default;
	}

	public function getParam(string $name, mixed $default = null) : mixed {
		return $this->params[$name] ?? $default;
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return Request
	 */
	public function setPreviousRequest(RequestInterface $request) : static {
		$this->previousRequest = $request;
		$this->errors = array_merge($this->previousRequest->getPassErrors(), $this->errors);
		$this->notices = array_merge($this->previousRequest->getPassNotices(), $this->notices);
		return $this;
	}

	public function addError(string $error) : static {
		$this->errors[] = $error;
		return $this;
	}

	public function addPassError(string $error) : static {
		$this->passErrors[] = $error;
		return $this;
	}

	public function getErrors() : array {
		return $this->errors;
	}

	public function getPassErrors() : array {
		return $this->passErrors;
	}

	public function addNotice(string $notice) : static {
		$this->notices[] = $notice;
		return $this;
	}

	public function addPassNotice(string $notice) : static {
		$this->passNotices[] = $notice;
		return $this;
	}

	public function getNotices() : array {
		return $this->notices;
	}

	public function getPassNotices() : array {
		return $this->passNotices;
	}

}
