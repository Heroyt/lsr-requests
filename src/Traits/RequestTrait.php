<?php

namespace Lsr\Core\Requests\Traits;

use InvalidArgumentException;
use Lsr\Core\Requests\Stream;
use Lsr\Enums\RequestMethod;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

trait RequestTrait
{

	public StreamInterface $streamBody;

	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Get one specific header by name
	 *
	 * @param string $name
	 *
	 * @return string|null
	 */
	public function header(string $name) : ?string {
		return $this->headers[$name] ?? null;
	}

	/**
	 * Retrieves the HTTP protocol version as a string.
	 *
	 * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
	 *
	 * @return string HTTP protocol version.
	 */
	public function getProtocolVersion() : string {
		return $this->httpVersion;
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
	public function withProtocolVersion($version) : static {
		$request = clone $this;
		$request->httpVersion = $version;
		return $request;
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
	public function hasHeader($name) : bool {
		return !empty($this->headers[$name]);
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
	public function getHeaderLine($name) : string {
		return $this->headers[$name] ?? '';
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
	public function withAddedHeader($name, $value) : static {
		$request = clone $this;
		if (!is_array($value)) {
			$value = [$value];
		}
		$request->headers[$name] = array_merge($request->getHeader($name), $value);
		return $request;
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
	public function getHeader($name) : array {
		$header = $this->headers[$name] ?? null;
		if ($header === null) {
			return [];
		}
		if (!is_array($header)) {
			return [$header];
		}
		return $header;
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
	public function withoutHeader($name) : static {
		$request = clone $this;
		if (isset($request->headers[$name])) {
			unset($request->headers[$name]);
		}
		return $request;
	}

	/**
	 * Gets the body of the message.
	 *
	 * @return StreamInterface Returns the body as a stream.
	 */
	public function getBody() : StreamInterface {
		if (!isset($this->streamBody)) {
			$this->streamBody = Stream::create($this->body);
		}
		return $this->streamBody;
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
	public function withBody(StreamInterface $body) : static {
		$request = clone $this;
		$request->streamBody = $body;
		$request->body = $body->getContents();
		$body->rewind();
		return $request;
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
	public function getRequestTarget() : string {
		return $this->getHeader('Origin')[0] ?? '';
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
	public function withRequestTarget($requestTarget) : static {
		return $this->withHeader('Origin', $requestTarget);
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
	public function withHeader($name, $value) : static {
		$request = clone $this;
		if (!is_array($value)) {
			$value = [$value];
		}
		$request->headers[$name] = $value;
		return $request;
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
	public function withMethod($method) : static {
		$enumMethod = RequestMethod::tryFrom(strtoupper($method));
		$request = clone $this;
		$request->type = $enumMethod ?? $this->type;
		return $request;
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
	public function getUri() : UriInterface {
		return $this->uri;
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
	public function withUri(UriInterface $uri, $preserveHost = false) : static {
		$request = clone $this;
		if ($preserveHost) {
			$uri = $uri->withHost($this->uri->getHost());
		}
		$request->uri = $uri;
		return $request;
	}
}