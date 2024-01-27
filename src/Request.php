<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Requests;


use JsonException;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Requests\Traits\RequestTrait;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface, \Psr\Http\Message\RequestInterface
{

	use RequestTrait;

	public RequestMethod $type = RequestMethod::GET;
	/** @var string[] */
	public array $path = [];
	/** @var array<string, mixed> */
	public array $query = [];
	/** @var array<string, string|numeric-string> */
	public array  $params = [];
	public string $body   = '';
	/** @var array<string, string|numeric|array<string,string|numeric>> */
	public array $post = [];
	/** @var array<string, string|numeric|array<string,string|numeric>> */
	public array $get = [];
	/** @var array<string, string|numeric|array<string,string|numeric>> */
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

	/**
	 * @param UriInterface $uri
	 * @param string       $httpVersion
	 *
	 * @throws JsonException
	 */
	public function __construct(public UriInterface $uri, public string $httpVersion = '1.1') {
		$query = $uri->getPath();

		// If the request is made to a PHP file, get the query data from GET params
		if (str_ends_with($query, '.php')) {
			$query = ($_GET['p'] ?? []);
		}

		// Get headers
		/**
		 * @var array<string, string>|false $headers
		 * @noinspection PhpUndefinedFunctionInspection
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
		$this->query = array_filter($_GET, static function ($key) {
			return $key !== 'p';
		},                          ARRAY_FILTER_USE_KEY);

		// Find route
		$this->route = Router::getRoute($this->type, $this->path, $this->params);

		if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
			$body = file_get_contents("php://input");
			$this->body = $body === false ? '' : $body;
			if ($this->type === RequestMethod::POST) {
				if (!empty($this->body)) {
					$_POST = array_merge($_POST, (array)json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				}
				$_REQUEST = array_merge($_REQUEST, $_POST);
			}
			else if ($this->type === RequestMethod::UPDATE || $this->type === RequestMethod::PUT) {
				if (!empty($this->body)) {
					/** @phpstan-ignore-next-line */
					$this->post = array_merge(
						$this->post,
						(array)json_decode($this->body, true, 512, JSON_THROW_ON_ERROR)
					);
				}
				$_REQUEST = array_merge($_REQUEST, $this->post);
			}
			else if ($this->type === RequestMethod::GET) {
				if (!empty($this->body)) {
					$_GET = array_merge($_GET, (array)json_decode($this->body, true, 512, JSON_THROW_ON_ERROR));
				}
				$_REQUEST = array_merge($_REQUEST, $_GET);
			}
		}
		$this->post = $_POST;
		$this->get = $_GET;
		$this->request = $_REQUEST;
	}

	public function getPath(): array {
		return $this->path;
	}

	/**
	 * Parse split URL path
	 *
	 * @param string[] $query
	 *
	 * @return void
	 */
	protected function parseArrayQuery(array $query): void {
		$this->path = array_map('strtolower', $query);
	}

	/**
	 * Parse URL path
	 *
	 * @param string $query
	 *
	 * @return void
	 */
	protected function parseStringQuery(string $query): void {
		$url = parse_url($query);
		// @phpstan-ignore-next-line
		$filePath = urldecode(ROOT . substr($url['path'], 1));
		if (file_exists($filePath) && is_file($filePath)) {
			$extension = pathinfo($filePath, PATHINFO_EXTENSION);
			if ($extension !== 'php') {
				/** @noinspection PhpComposerExtensionStubsInspection */
				$mime = match ($extension) {
					'css'   => 'text/css',
					'scss'  => 'text/x-scss',
					'sass'  => 'text/x-sass',
					'csv'   => 'text/csv',
					'css.map', 'js.map', 'map', 'json' => 'application/json',
					'js'    => 'text/javascript',
					default => mime_content_type($filePath),
				};
				header('Content-Type: ' . $mime);
				exit(file_get_contents($filePath));
			}
		}
		$this->parseArrayQuery(array_filter(explode('/', $url['path'] ?? ''), static function ($a) {
			return !empty($a);
		}));
	}

	/**
	 * @return RouteInterface|null
	 */
	public function getRoute(): ?RouteInterface {
		return $this->route;
	}

	/**
	 * @return void
	 * @throws RouteNotFoundException
	 */
	public function handle(): void {
		if (isset($this->route)) {
			$this->route->handle($this);
			return;
		}
		throw new RouteNotFoundException($this);
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __get($name): mixed {
		return $this->params[$name] ?? null;
	}

	/**
	 * @param string $name
	 * @param string $value
	 *
	 * @return void
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __set($name, $value): void {
		$this->params[$name] = $value;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __isset($name): bool {
		return isset($this->params[$name]);
	}

	/**
	 * Check if current page is requested using AJAX call
	 *
	 * @return bool
	 */
	public function isAjax(): bool {
		return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
					$_SERVER['HTTP_X_REQUESTED_WITH']
				) === 'xmlhttprequest') || (!empty($_SERVER['X_REQUESTED_WITH']) && strtolower(
					$_SERVER['X_REQUESTED_WITH']
				) === 'xmlhttprequest');
	}

	/**
	 * @inheritDoc
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
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
	public function getIp(): string {
		return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
	}

	/**
	 * @return RequestMethod
	 * @phpstan-ignore-next-line
	 */
	public function getMethod(): RequestMethod {
		return $this->type;
	}

	/**
	 * Get a POST parameter from the request with a specified default fallback value
	 *
	 * @param string     $name
	 * @param string|numeric|array<string,string|numeric>|null $default
	 *
	 * @return string|numeric|array<string,string|numeric>|null
	 */
	public function getPost(string $name, string|array|int|float|null $default = null): string|array|int|float|null {
		return $this->post[$name] ?? $default;
	}

	/**
	 * Get a GET parameter from the request with a specified default fallback value
	 *
	 * @param string     $name
	 * @param string|numeric|array<string,string|numeric>|null $default
	 *
	 * @return string|numeric|array<string,string|numeric>|null
	 */
	public function getGet(string $name, string|array|int|float|null $default = null): string|array|int|float|null {
		return $this->request[$name] ?? $default;
	}

	/**
	 * @param string              $name
	 * @param string|numeric|null $default
	 *
	 * @return string|numeric|null
	 */
	public function getParam(string $name, mixed $default = null): string|int|float|null {
		return $this->params[$name] ?? $default;
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return $this
	 */
	public function setPreviousRequest(RequestInterface $request): static {
		$this->previousRequest = $request;
		$this->errors = array_merge($this->previousRequest->getPassErrors(), $this->errors);
		$this->notices = array_merge($this->previousRequest->getPassNotices(), $this->notices);
		return $this;
	}

	public function getPassErrors(): array {
		return $this->passErrors;
	}

	public function getPassNotices(): array {
		return $this->passNotices;
	}

	public function addError(string $error): static {
		$this->errors[] = $error;
		return $this;
	}

	public function addPassError(string $error): static {
		$this->passErrors[] = $error;
		return $this;
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function addNotice(string $notice): static {
		$this->notices[] = $notice;
		return $this;
	}

	public function addPassNotice(string $notice): static {
		$this->passNotices[] = $notice;
		return $this;
	}

	public function getNotices(): array {
		return $this->notices;
	}

}
