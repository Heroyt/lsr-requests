<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Requests;


use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Router;
use Lsr\Core\Routing\Route;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;

class Request implements RequestInterface
{

	public RequestMethod $type            = RequestMethod::GET;
	public array         $path            = [];
	public array         $query           = [];
	public array         $params          = [];
	public string        $body            = '';
	public array         $put             = [];
	public array         $post            = [];
	public array         $get             = [];
	public array         $request         = [];
	public array         $errors          = [];
	public array         $notices         = [];
	public array         $passErrors      = [];
	public array         $passNotices     = [];
	public ?Request      $previousRequest = null;
	protected ?Route     $route           = null;

	public function __construct(array|string $query) {
		$this->type = RequestMethod::tryFrom($_SERVER['REQUEST_METHOD']) ?? RequestMethod::GET;
		if (is_array($query)) {
			$this->parseArrayQuery($query);
		}
		else {
			$this->parseStringQuery($query);
		}
		$this->query = array_filter($_GET, static function($key) {
			return $key !== 'p';
		},                          ARRAY_FILTER_USE_KEY);
		$this->route = Router::getRoute($this->type, $this->path, $this->params);
		if (isset($_SESSION['fromRequest'])) {
			$this->previousRequest = unserialize($_SESSION['fromRequest'], [__CLASS__]);
			unset($_SESSION['fromRequest']);
			$this->errors = array_merge($this->previousRequest->passErrors, $this->errors);
			$this->notices = array_merge($this->previousRequest->passNotices, $this->notices);
		}
		if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
			$input = fopen("php://input", 'rb');
			$this->body = '';
			while ($data = fread($input, 1024)) {
				$this->body .= $data;
			}
			fclose($input);
			if ($this->type === RequestMethod::POST) {
				$_POST = array_merge($_POST, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				$_REQUEST = array_merge($_REQUEST, $_POST);
			}
			elseif ($this->type === RequestMethod::UPDATE) {
				$this->put = array_merge($this->put, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				$_REQUEST = array_merge($_REQUEST, $this->put);
			}
			elseif ($this->type === RequestMethod::GET) {
				$_GET = array_merge($_GET, json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				$_REQUEST = array_merge($_REQUEST, $_GET);
			}
		}
		$this->post = $_POST;
		$this->get = $_GET;
		$this->request = $_REQUEST;
	}

	protected function parseArrayQuery(array $query) : void {
		$this->path = array_map('strtolower', $query);
	}

	protected function parseStringQuery(string $query) : void {
		$url = parse_url($query);
		$filePath = urldecode(ROOT.substr($url['path'], 1));
		if (file_exists($filePath) && is_file($filePath)) {
			$extension = pathinfo($filePath, PATHINFO_EXTENSION);
			if ($extension !== 'php') {
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
		$this->parseArrayQuery(array_filter(explode('/', $url['path']), static function($a) {
			return !empty($a);
		}));
	}

	/**
	 * @return Route|null
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
		}
		else {
			throw new RouteNotFoundException($this);
		}
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
		if (isset($this->route) && !empty($this->route->getRouteName())) {
			$vars['routeName'] = $this->route->getRouteName();
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

	public function getPath() : array {
		return $this->path;
	}

	public function getMethod() : RequestMethod {
		return $this->type;
	}
}
