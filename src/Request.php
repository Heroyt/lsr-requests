<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Requests;


use InvalidArgumentException;
use Lsr\Core\Requests\Exceptions\RouteNotFoundException;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\RouteInterface;
use Psr\Http\Message\ServerRequestInterface as Psr7RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 request decorator class.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 */
class Request implements RequestInterface
{

	/** @var array<string, string|numeric-string> */
	public array $params = [];
	/** @var array<string|int, string> */
	public array $errors = [];
	/** @var array<string|array{title?:string,content:string,type?:string}> */
	public array $notices = [];
	/** @var array<string|int, string> */
	public array $passErrors = [];
	/** @var array<string|array{title?:string,content:string,type?:string}> */
	public array          $passNotices = [];
	public ?RequestInterface  $previousRequest = null;
	protected ?RouteInterface $route           = null;
	private RequestMethod $type;
	/** @var string[] */
	private array $path;

	/**
	 * @param Psr7RequestInterface $psrRequest
	 */
	public function __construct(private readonly Psr7RequestInterface $psrRequest) {
	}

	public function getStaticFileMime(): string {
		$path = $this->getUri()->getPath();
		$filePath = urldecode(ROOT . substr($path, 1));
		$extension = pathinfo($filePath, PATHINFO_EXTENSION);

		/** @noinspection PhpComposerExtensionStubsInspection */
		return match ($extension) {
			'css'                              => 'text/css',
			'scss'                             => 'text/x-scss',
			'sass'                             => 'text/x-sass',
			'csv'                              => 'text/csv',
			'css.map', 'js.map', 'map', 'json' => 'application/json',
			'js'                               => 'text/javascript',
			default                            => mime_content_type($filePath),
		};
	}

	/**
	 * Get parsed request path as an array
	 *
	 * @return string[]
	 */
	public function getPath(): array {
		if (!isset($this->path)) {
			$uri = $this->psrRequest->getUri();
			$query = $uri->getPath();

			// If the request is made to a PHP file, get the query data from GET params
			if (str_ends_with($query, '.php')) {
				$query = $this->psrRequest->getQueryParams()['p'] ?? [];
			}

			// Parse path
			$this->path = is_array($query) ? $this->parseArrayQuery($query) : $this->parseStringQuery($query);
		}
		return $this->path;
	}

	/**
	 * Retrieves the URI instance.
	 *
	 * This method MUST return a UriInterface instance.
	 *
	 * @link http://tools.ietf.org/html/rfc3986#section-4.3
	 * @return UriInterface Returns a UriInterface instance
	 *     representing the URI of the request.
	 */
	public function getUri(): UriInterface {
		return $this->psrRequest->getUri();
	}

	/**
	 * Retrieve query string arguments.
	 *
	 * Retrieves the deserialized query string arguments, if any.
	 *
	 * Note: the query params might not be in sync with the URI or server
	 * params. If you need to ensure you are only getting the original
	 * values, you may need to parse the query string from `getUri()->getQuery()`
	 * or from the `QUERY_STRING` server param.
	 *
	 * @return array
	 */
	public function getQueryParams(): array {
		return $this->psrRequest->getQueryParams();
	}

	/**
	 * Parse split URL path
	 *
	 * @param string[] $query
	 *
	 * @return string[]
	 */
	protected function parseArrayQuery(array $query): array {
		return array_values(array_map('strtolower', $query));
	}

	/**
	 * Parse URL path
	 *
	 * @param string $query
	 *
	 * @return string[]
	 */
	protected function parseStringQuery(string $query): array {
		$url = parse_url($query);
		return $this->parseArrayQuery(
			array_filter(
				explode('/', $url['path'] ?? ''), static fn($a) => !empty($a)
			)
		);
	}

	public function isStaticFile(): bool {
		$path = $this->getUri()->getPath();
		// @phpstan-ignore-next-line
		$filePath = urldecode(ROOT . substr($path, 1));
		if (file_exists($filePath) && is_file($filePath)) {
			$extension = pathinfo($filePath, PATHINFO_EXTENSION);
			if ($extension !== 'php') {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return void
	 * @throws RouteNotFoundException
	 * @deprecated
	 */
	public function handle(): void {
		$route = $this->getRoute();
		if (!isset($route)) {
			throw new RouteNotFoundException($this);
		}
		$route->handle($this);
	}

	/**
	 * @return RouteInterface|null
	 * @interal
	 */
	public function getRoute(): ?RouteInterface {
		if (!isset($this->route)) {
			$this->route = Router::getRoute($this->getType(), $this->getPath(), $this->params);
		}
		return $this->route;
	}

	/**
	 * Get request method as an Enum
	 *
	 * @return RequestMethod
	 */
	public function getType(): RequestMethod {
		$this->type ??= RequestMethod::from(strtoupper($this->psrRequest->getMethod()));
		return $this->type;
	}

	/**
	 * Retrieves the HTTP method of the request.
	 *
	 * @return string Returns the request method.
	 */
	public function getMethod(): string {
		return $this->psrRequest->getMethod();
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
		return $this->hasHeader('x-requested-with') && in_array(
				'xmlhttprequest',
				array_map(static fn(string $val) => strtolower(trim($val)), $this->getHeader('x-requested-with')),
				true
			);
	}

	/**
	 * Checks if a header exists by the given case-insensitive name.
	 *
	 * @param string $name Case-insensitive header field name.
	 *
	 * @return bool Returns true if any header names match the given header
	 *     name using a case-insensitive string comparison. Returns false if
	 *     no matching header name is found in the message.
	 */
	public function hasHeader(string $name): bool {
		return $this->psrRequest->hasHeader($name);
	}

	/**
	 * Retrieves a message header value by the given case-insensitive name.
	 *
	 * This method returns an array of all the header values of the given
	 * case-insensitive header name.
	 *
	 * If the header does not appear in the message, this method MUST return an
	 * empty array.
	 *
	 * @param string $name Case-insensitive header field name.
	 *
	 * @return string[] An array of string values as provided for the given
	 *    header. If the header does not appear in the message, this method MUST
	 *    return an empty array.
	 */
	public function getHeader(string $name): array {
		return $this->psrRequest->getHeader($name);
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
		$params = $this->psrRequest->getServerParams();
		return $params['HTTP_CLIENT_IP'] ?? $params['HTTP_X_FORWARDED_FOR'] ?? $params['REMOTE_ADDR'] ?? '';
	}

	/**
	 * Retrieve server parameters.
	 *
	 * Retrieves data related to the incoming request environment,
	 * typically derived from PHP's $_SERVER superglobal. The data IS NOT
	 * REQUIRED to originate from $_SERVER.
	 *
	 * @return array
	 */
	public function getServerParams(): array {
		return $this->psrRequest->getServerParams();
	}

	/**
	 * Get a POST parameter from the request with a specified default fallback value
	 *
	 * @param string $name
	 * @param string|numeric|array<string,string|numeric>|bool|null $default
	 *
	 * @return string|numeric|array<string,string|numeric>|bool|null
	 */
	public function getPost(string $name, string|array|int|float|bool|null $default = null): string|array|int|float|bool|null {
		return $this->psrRequest->getParsedBody()[$name] ?? $default;
	}

	/**
	 * Retrieve any parameters provided in the request body.
	 *
	 * If the request Content-Type is either application/x-www-form-urlencoded
	 * or multipart/form-data, and the request method is POST, this method MUST
	 * return the contents of $_POST.
	 *
	 * Otherwise, this method may return any results of deserializing
	 * the request body content; as parsing returns structured content, the
	 * potential types MUST be arrays or objects only. A null value indicates
	 * the absence of body content.
	 *
	 * @return null|array|object The deserialized body parameters, if any.
	 *     These will typically be an array or object.
	 */
	public function getParsedBody(): object|array|null {
		return $this->psrRequest->getParsedBody();
	}

	/**
	 * Get a GET parameter from the request with a specified default fallback value
	 *
	 * @param string $name
	 * @param string|numeric|array<string,string|numeric>|bool|null $default
	 *
	 * @return string|numeric|array<string,string|numeric>|bool|null
	 */
	public function getGet(string $name, string|array|int|float|bool|null $default = null): string|array|int|float|bool|null {
		return $this->psrRequest->getQueryParams()[$name] ?? $default;
	}

	/**
	 * @param array<string,string|numeric|null> $params
	 *
	 * @return $this
	 */
	public function setParams(array $params): static {
		$this->params = $params;
		return $this;
	}

	/**
	 * Get a URL path parameter
	 *
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

	/**
	 * @param string|array{title?:string,content:string,type?:string} $notice
	 *
	 * @return $this
	 */
	public function addNotice(string|array $notice): static {
		$this->notices[] = $notice;
		return $this;
	}

	/**
	 * @param string|array{title?:string,content:string,type?:string} $notice
	 *
	 * @return $this
	 */
	public function addPassNotice(string|array $notice): static {
		$this->passNotices[] = $notice;
		return $this;
	}

	/**
	 * @return array<string|array{title?:string,content:string,type?:string}>
	 */
	public function getNotices(): array {
		return $this->notices;
	}

	/**
	 * Retrieves the HTTP protocol version as a string.
	 *
	 * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
	 *
	 * @return string HTTP protocol version.
	 */
	public function getProtocolVersion(): string {
		return $this->psrRequest->getProtocolVersion();
	}

	/**
	 * Return an instance with the specified HTTP protocol version.
	 *
	 * The version string MUST contain only the HTTP version number (e.g.,
	 * "1.1", "1.0").
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new protocol version.
	 *
	 * @param string $version HTTP protocol version
	 *
	 * @return static
	 */
	public function withProtocolVersion(string $version): static {
		return new self($this->psrRequest->withProtocolVersion($version));
	}

	/**
	 * Retrieves all message header values.
	 *
	 * The keys represent the header name as it will be sent over the wire, and
	 * each value is an array of strings associated with the header.
	 *
	 *     // Represent the headers as a string
	 *     foreach ($message->getHeaders() as $name => $values) {
	 *         echo $name . ": " . implode(", ", $values);
	 *     }
	 *
	 *     // Emit headers iteratively:
	 *     foreach ($message->getHeaders() as $name => $values) {
	 *         foreach ($values as $value) {
	 *             header(sprintf('%s: %s', $name, $value), false);
	 *         }
	 *     }
	 *
	 * While header names are not case-sensitive, getHeaders() will preserve the
	 * exact case in which headers were originally specified.
	 *
	 * @return string[][] Returns an associative array of the message's headers. Each
	 *     key MUST be a header name, and each value MUST be an array of strings
	 *     for that header.
	 */
	public function getHeaders(): array {
		return $this->psrRequest->getHeaders();
	}

	/**
	 * Retrieves a comma-separated string of the values for a single header.
	 *
	 * This method returns all of the header values of the given
	 * case-insensitive header name as a string concatenated together using
	 * a comma.
	 *
	 * NOTE: Not all header values may be appropriately represented using
	 * comma concatenation. For such headers, use getHeader() instead
	 * and supply your own delimiter when concatenating.
	 *
	 * If the header does not appear in the message, this method MUST return
	 * an empty string.
	 *
	 * @param string $name Case-insensitive header field name.
	 *
	 * @return string A string of values as provided for the given header
	 *    concatenated together using a comma. If the header does not appear in
	 *    the message, this method MUST return an empty string.
	 */
	public function getHeaderLine(string $name): string {
		return $this->psrRequest->getHeaderLine($name);
	}

	/**
	 * Return an instance with the provided value replacing the specified header.
	 *
	 * While header names are case-insensitive, the casing of the header will
	 * be preserved by this function, and returned from getHeaders().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new and/or updated header and value.
	 *
	 * @param string          $name  Case-insensitive header field name.
	 * @param string|string[] $value Header value(s).
	 *
	 * @return static
	 * @throws InvalidArgumentException for invalid header names or values.
	 */
	public function withHeader(string $name, $value): static {
		return new self($this->psrRequest->withHeader($name, $value));
	}

	/**
	 * Return an instance with the specified header appended with the given value.
	 *
	 * Existing values for the specified header will be maintained. The new
	 * value(s) will be appended to the existing list. If the header did not
	 * exist previously, it will be added.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new header and/or value.
	 *
	 * @param string          $name  Case-insensitive header field name to add.
	 * @param string|string[] $value Header value(s).
	 *
	 * @return static
	 * @throws InvalidArgumentException for invalid header names or values.
	 */
	public function withAddedHeader(string $name, $value): static {
		return new self($this->psrRequest->withAddedHeader($name, $value));
	}

	/**
	 * Return an instance without the specified header.
	 *
	 * Header resolution MUST be done without case-sensitivity.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that removes
	 * the named header.
	 *
	 * @param string $name Case-insensitive header field name to remove.
	 *
	 * @return static
	 */
	public function withoutHeader(string $name): static {
		return new self($this->psrRequest->withoutHeader($name));
	}

	/**
	 * Gets the body of the message.
	 *
	 * @return StreamInterface Returns the body as a stream.
	 */
	public function getBody(): StreamInterface {
		return $this->psrRequest->getBody();
	}

	/**
	 * Return an instance with the specified message body.
	 *
	 * The body MUST be a StreamInterface object.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return a new instance that has the
	 * new body stream.
	 *
	 * @param StreamInterface $body Body.
	 *
	 * @return static
	 * @throws InvalidArgumentException When the body is not valid.
	 */
	public function withBody(StreamInterface $body): static {
		return new self($this->psrRequest->withBody($body));
	}

	/**
	 * Retrieves the message's request target.
	 *
	 * Retrieves the message's request-target either as it will appear (for
	 * clients), as it appeared at request (for servers), or as it was
	 * specified for the instance (see withRequestTarget()).
	 *
	 * In most cases, this will be the origin-form of the composed URI,
	 * unless a value was provided to the concrete implementation (see
	 * withRequestTarget() below).
	 *
	 * If no URI is available, and no request-target has been specifically
	 * provided, this method MUST return the string "/".
	 *
	 * @return string
	 */
	public function getRequestTarget(): string {
		return $this->psrRequest->getRequestTarget();
	}

	/**
	 * Return an instance with the specific request-target.
	 *
	 * If the request needs a non-origin-form request-target — e.g., for
	 * specifying an absolute-form, authority-form, or asterisk-form —
	 * this method may be used to create an instance with the specified
	 * request-target, verbatim.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request target.
	 *
	 * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
	 *     request-target forms allowed in request messages)
	 *
	 * @param mixed $requestTarget
	 *
	 * @return static
	 */
	public function withRequestTarget($requestTarget): static {
		return new self($this->psrRequest->withRequestTarget($requestTarget));
	}

	/**
	 * Return an instance with the provided HTTP method.
	 *
	 * While HTTP method names are typically all uppercase characters, HTTP
	 * method names are case-sensitive and thus implementations SHOULD NOT
	 * modify the given string.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request method.
	 *
	 * @param string $method Case-sensitive method.
	 *
	 * @return static
	 * @throws InvalidArgumentException for invalid HTTP methods.
	 */
	public function withMethod(string $method): static {
		return new self($this->psrRequest->withMethod($method));
	}

	/**
	 * Returns an instance with the provided URI.
	 *
	 * This method MUST update the Host header of the returned request by
	 * default if the URI contains a host component. If the URI does not
	 * contain a host component, any pre-existing Host header MUST be carried
	 * over to the returned request.
	 *
	 * You can opt-in to preserving the original state of the Host header by
	 * setting `$preserveHost` to `true`. When `$preserveHost` is set to
	 * `true`, this method interacts with the Host header in the following ways:
	 *
	 * - If the Host header is missing or empty, and the new URI contains
	 *   a host component, this method MUST update the Host header in the returned
	 *   request.
	 * - If the Host header is missing or empty, and the new URI does not contain a
	 *   host component, this method MUST NOT update the Host header in the returned
	 *   request.
	 * - If a Host header is present and non-empty, this method MUST NOT update
	 *   the Host header in the returned request.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new UriInterface instance.
	 *
	 * @link http://tools.ietf.org/html/rfc3986#section-4.3
	 *
	 * @param UriInterface $uri          New request URI to use.
	 * @param bool         $preserveHost Preserve the original state of the Host header.
	 *
	 * @return static
	 */
	public function withUri(UriInterface $uri, bool $preserveHost = false): static {
		return new self($this->psrRequest->withUri($uri, $preserveHost));
	}

	/**
	 * Retrieve cookies.
	 *
	 * Retrieves cookies sent by the client to the server.
	 *
	 * The data MUST be compatible with the structure of the $_COOKIE
	 * superglobal.
	 *
	 * @return array
	 */
	public function getCookieParams(): array {
		return $this->psrRequest->getCookieParams();
	}

	/**
	 * Return an instance with the specified cookies.
	 *
	 * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
	 * be compatible with the structure of $_COOKIE. Typically, this data will
	 * be injected at instantiation.
	 *
	 * This method MUST NOT update the related Cookie header of the request
	 * instance, nor related values in the server params.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated cookie values.
	 *
	 * @param array $cookies Array of key/value pairs representing cookies.
	 *
	 * @return static
	 */
	public function withCookieParams(array $cookies): static {
		return new self($this->psrRequest->withCookieParams($cookies));
	}

	/**
	 * Return an instance with the specified query string arguments.
	 *
	 * These values SHOULD remain immutable over the course of the incoming
	 * request. They MAY be injected during instantiation, such as from PHP's
	 * $_GET superglobal, or MAY be derived from some other value such as the
	 * URI. In cases where the arguments are parsed from the URI, the data
	 * MUST be compatible with what PHP's parse_str() would return for
	 * purposes of how duplicate query parameters are handled, and how nested
	 * sets are handled.
	 *
	 * Setting query string arguments MUST NOT change the URI stored by the
	 * request, nor the values in the server params.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated query string arguments.
	 *
	 * @param array $query Array of query string arguments, typically from
	 *                     $_GET.
	 *
	 * @return static
	 */
	public function withQueryParams(array $query): static {
		return new self($this->psrRequest->withQueryParams($query));
	}

	/**
	 * Retrieve normalized file upload data.
	 *
	 * This method returns upload metadata in a normalized tree, with each leaf
	 * an instance of Psr\Http\Message\UploadedFileInterface.
	 *
	 * These values MAY be prepared from $_FILES or the message body during
	 * instantiation, or MAY be injected via withUploadedFiles().
	 *
	 * @return array An array tree of UploadedFileInterface instances; an empty
	 *     array MUST be returned if no data is present.
	 */
	public function getUploadedFiles(): array {
		return $this->psrRequest->getUploadedFiles();
	}

	/**
	 * Create a new instance with the specified uploaded files.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated body parameters.
	 *
	 * @param array $uploadedFiles An array tree of UploadedFileInterface instances.
	 *
	 * @return static
	 * @throws InvalidArgumentException if an invalid structure is provided.
	 */
	public function withUploadedFiles(array $uploadedFiles): static {
		return new self($this->psrRequest->withUploadedFiles($uploadedFiles));
	}

	/**
	 * Return an instance with the specified body parameters.
	 *
	 * These MAY be injected during instantiation.
	 *
	 * If the request Content-Type is either application/x-www-form-urlencoded
	 * or multipart/form-data, and the request method is POST, use this method
	 * ONLY to inject the contents of $_POST.
	 *
	 * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
	 * deserializing the request body content. Deserialization/parsing returns
	 * structured data, and, as such, this method ONLY accepts arrays or objects,
	 * or a null value if nothing was available to parse.
	 *
	 * As an example, if content negotiation determines that the request data
	 * is a JSON payload, this method could be used to create a request
	 * instance with the deserialized parameters.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated body parameters.
	 *
	 * @param null|array|object $data The deserialized body data. This will
	 *                                typically be in an array or object.
	 *
	 * @return static
	 * @throws InvalidArgumentException if an unsupported argument type is
	 *                                provided.
	 */
	public function withParsedBody($data): static {
		return new self($this->psrRequest->withParsedBody($data));
	}

	/**
	 * Retrieve attributes derived from the request.
	 *
	 * The request "attributes" may be used to allow injection of any
	 * parameters derived from the request: e.g., the results of path
	 * match operations; the results of decrypting cookies; the results of
	 * deserializing non-form-encoded message bodies; etc. Attributes
	 * will be application and request specific, and CAN be mutable.
	 *
	 * @return array Attributes derived from the request.
	 */
	public function getAttributes(): array {
		return $this->psrRequest->getAttributes();
	}

	/**
	 * Retrieve a single derived request attribute.
	 *
	 * Retrieves a single derived request attribute as described in
	 * getAttributes(). If the attribute has not been previously set, returns
	 * the default value as provided.
	 *
	 * This method obviates the need for a hasAttribute() method, as it allows
	 * specifying a default value to return if the attribute is not found.
	 *
	 * @param string $name    The attribute name.
	 * @param mixed  $default Default value to return if the attribute does not exist.
	 *
	 * @return mixed
	 * @see getAttributes()
	 */
	public function getAttribute(string $name, $default = null): mixed {
		return $this->psrRequest->getAttribute($name, $default);
	}

	/**
	 * Return an instance with the specified derived request attribute.
	 *
	 * This method allows setting a single derived request attribute as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated attribute.
	 *
	 * @param string $name  The attribute name.
	 * @param mixed  $value The value of the attribute.
	 *
	 * @return static
	 * @see getAttributes()
	 */
	public function withAttribute(string $name, $value): static {
		return new self($this->psrRequest->withAttribute($name, $value));
	}

	/**
	 * Return an instance that removes the specified derived request attribute.
	 *
	 * This method allows removing a single derived request attribute as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that removes
	 * the attribute.
	 *
	 * @param string $name The attribute name.
	 *
	 * @return static
	 * @see getAttributes()
	 */
	public function withoutAttribute(string $name): static {
		return new self($this->psrRequest->withoutAttribute($name));
	}
}
