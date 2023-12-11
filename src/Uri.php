<?php
/** @noinspection RegExpRedundantEscape */

namespace Lsr\Core\Requests;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use function is_string;
use function ltrim;
use function parse_url;
use function preg_replace_callback;
use function rawurlencode;
use function sprintf;
use function strtr;

class Uri implements UriInterface
{
	private const SCHEMES = ['http' => 80, 'https' => 443];

	private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

	private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

	/** @var string Uri scheme. */
	private string $scheme = '';

	/** @var string Uri user info. */
	private mixed $userInfo = '';

	/** @var string Uri host. */
	private string $host = '';

	/** @var int|null Uri port. */
	private ?int $port;

	/** @var string Uri path. */
	private string $path = '';

	/** @var string Uri query string. */
	private string $query = '';

	/** @var string Uri fragment. */
	private string $fragment = '';

	public function __construct(string $uri = '') {
		if ('' !== $uri) {
			if (false === $parts = parse_url($uri)) {
				throw new InvalidArgumentException(sprintf('Unable to parse URI: "%s"', $uri));
			}

			// Apply parse_url parts to a URI.
			$this->scheme = isset($parts['scheme']) ? strtr($parts['scheme'], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') : '';
			$this->userInfo = $parts['user'] ?? '';
			$this->host = isset($parts['host']) ? strtr($parts['host'], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') : '';
			$this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
			$this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
			$this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
			$this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';
			if (isset($parts['pass'])) {
				$this->userInfo .= ':'.$parts['pass'];
			}
		}
	}

	private function filterPort(?int $port) : ?int {
		if (null === $port) {
			return null;
		}

		if (0 > $port || 0xffff < $port) {
			throw new InvalidArgumentException(sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
		}

		return self::isNonStandardPort($this->scheme, $port) ? $port : null;
	}

	/**
	 * Is a given port non-standard for the current scheme?
	 */
	private static function isNonStandardPort(string $scheme, int $port) : bool {
		return !isset(self::SCHEMES[$scheme]) || $port !== self::SCHEMES[$scheme];
	}

	/** @noinspection RegExpUnnecessaryNonCapturingGroup */
	private function filterPath(string $path) : string {
		// @phpstan-ignore-next-line
		return str_replace(
			'//',
			'/',
			preg_replace_callback(
				'/(?:[^' . static::CHAR_UNRESERVED . static::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
				[__CLASS__, 'rawurlencodeMatchZero'],
				$path
			)
		);
	}

	/** @noinspection RegExpUnnecessaryNonCapturingGroup */
	private function filterQueryAndFragment(string $str) : string {
		// @phpstan-ignore-next-line
		return preg_replace_callback('/(?:[^'.static::CHAR_UNRESERVED.static::CHAR_SUB_DELIMS.'%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/', [__CLASS__, 'rawurlencodeMatchZero'], $str);
	}

	/**
	 * @param string[] $match
	 *
	 * @return string
	 */
	private static function rawurlencodeMatchZero(array $match) : string {
		return rawurlencode($match[0]);
	}

	public function __toString() : string {
		return self::createUriString($this->scheme, $this->getAuthority(), $this->path, $this->query, $this->fragment);
	}

	/**
	 * Create a URI string from its various parts.
	 */
	private static function createUriString(string $scheme, string $authority, string $path, string $query, string $fragment) : string {
		$uri = '';
		if ('' !== $scheme) {
			$uri .= $scheme.':';
		}

		if ('' !== $authority) {
			$uri .= '//'.$authority;
		}

		if ('' !== $path) {
			if ('/' !== $path[0]) {
				if ('' !== $authority) {
					// If the path is rootless and an authority is present, the path MUST be prefixed by "/"
					$path = '/'.$path;
				}
			}
			elseif (isset($path[1]) && '/' === $path[1]) {
				if ('' === $authority) {
					// If the path is starting with more than one "/" and no authority is present, the
					// starting slashes MUST be reduced to one.
					$path = '/'.ltrim($path, '/');
				}
			}

			$uri .= $path;
		}

		if ('' !== $query) {
			$uri .= '?'.$query;
		}

		if ('' !== $fragment) {
			$uri .= '#'.$fragment;
		}

		return $uri;
	}

	public function getAuthority() : string {
		if ('' === $this->host) {
			return '';
		}

		$authority = $this->host;
		if ('' !== $this->userInfo) {
			$authority = $this->userInfo.'@'.$authority;
		}

		if (null !== $this->port) {
			$authority .= ':'.$this->port;
		}

		return $authority;
	}

	public function getScheme() : string {
		return $this->scheme;
	}

	public function getUserInfo() : string {
		return $this->userInfo;
	}

	public function getHost() : string {
		return $this->host;
	}

	public function getPort() : ?int {
		return $this->port;
	}

	public function getPath() : string {
		return $this->path;
	}

	public function getQuery() : string {
		return $this->query;
	}

	public function getFragment() : string {
		return $this->fragment;
	}

	/**
	 * @param string $scheme
	 *
	 * @return static
	 */
	public function withScheme($scheme) : static {
		if (!is_string($scheme)) {
			throw new InvalidArgumentException('Scheme must be a string');
		}

		if ($this->scheme === $scheme = strtr($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')) {
			return $this;
		}

		$new = clone $this;
		$new->scheme = $scheme;
		$new->port = $new->filterPort($new->port);

		return $new;
	}

	/**
	 * @param string $user
	 * @param string $password
	 *
	 * @return $this
	 */
	public function withUserInfo($user, $password = null) : static {
		$info = $user;
		if (null !== $password && '' !== $password) {
			$info .= ':'.$password;
		}

		if ($this->userInfo === $info) {
			return $this;
		}

		$new = clone $this;
		$new->userInfo = $info;

		return $new;
	}

	/**
	 * @param string $host
	 *
	 * @return $this
	 */
	public function withHost($host) : static {
		if (!is_string($host)) {
			throw new InvalidArgumentException('Host must be a string');
		}

		if ($this->host === $host = strtr($host, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')) {
			return $this;
		}

		$new = clone $this;
		$new->host = $host;

		return $new;
	}

	/**
	 * @param null|int $port
	 *
	 * @return $this
	 */
	public function withPort($port) : static {
		if ($this->port === ($port = $this->filterPort($port))) {
			return $this;
		}

		$new = clone $this;
		$new->port = $port;

		return $new;
	}

	/**
	 * @param string $path
	 *
	 * @return $this
	 */
	public function withPath($path) : static {
		if ($this->path === $path = $this->filterPath($path)) {
			return $this;
		}

		$new = clone $this;
		$new->path = $path;

		return $new;
	}

	/**
	 * @param string $query
	 *
	 * @return $this
	 */
	public function withQuery($query) : static {
		if ($this->query === $query = $this->filterQueryAndFragment($query)) {
			return $this;
		}

		$new = clone $this;
		$new->query = $query;

		return $new;
	}

	/**
	 * @param string $fragment
	 *
	 * @return $this
	 */
	public function withFragment($fragment) : static {
		if ($this->fragment === $fragment = $this->filterQueryAndFragment($fragment)) {
			return $this;
		}

		$new = clone $this;
		$new->fragment = $fragment;

		return $new;
	}
}