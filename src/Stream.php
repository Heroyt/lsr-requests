<?php

namespace Lsr\Core\Requests;

use Error;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;
use function clearstatcache;
use function error_get_last;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function fwrite;
use function is_resource;
use function is_string;
use function stream_get_contents;
use function stream_get_meta_data;
use function trigger_error;
use function var_export;
use const E_USER_ERROR;
use const PHP_VERSION_ID;
use const SEEK_CUR;
use const SEEK_SET;

class Stream implements StreamInterface
{
	private const READ_WRITE_HASH = [
		'read'  => [
			'r'   => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
			'rb'  => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
			'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
			'x+t' => true, 'c+t' => true, 'a+' => true,
		],
		'write' => [
			'w'   => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
			'c+'  => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
			'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
			'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true,
		],
	];
	/** @var resource|null A resource reference */
	private        $stream;
	private bool   $seekable;
	private bool   $readable;
	private bool   $writable;
	private string $uri;
	private ?int   $size = null;

	private function __construct() {
	}

	/**
	 * Creates a new PSR-7 stream.
	 *
	 * @param string|resource|StreamInterface $body
	 *
	 * @throws InvalidArgumentException
	 */
	public static function create($body = '') : StreamInterface {
		if ($body instanceof StreamInterface) {
			return $body;
		}

		if (is_string($body)) {
			/** @var resource|false $resource */
			$resource = fopen('php://temp', 'rwb+');
			if ($resource === false) {
				throw new RuntimeException('Cannot initiate Stream');
			}
			fwrite($resource, $body);
			/** @noinspection CallableParameterUseCaseInTypeContextInspection */
			$body = $resource;
		}

		if (is_resource($body)) {
			$new = new self();
			$new->stream = $body;
			$meta = stream_get_meta_data($new->stream);
			$new->seekable = $meta['seekable'] && 0 === fseek($new->stream, 0, SEEK_CUR);
			$new->readable = isset(self::READ_WRITE_HASH['read'][$meta['mode']]);
			$new->writable = isset(self::READ_WRITE_HASH['write'][$meta['mode']]);

			return $new;
		}

		throw new InvalidArgumentException('First argument to Stream::create() must be a string, resource or StreamInterface.');
	}

	/**
	 * Closes the stream when the destructed.
	 */
	public function __destruct() {
		$this->close();
	}

	public function close() : void {
		if (isset($this->stream)) {
			if (is_resource($this->stream)) {
				fclose($this->stream);
			}
			$this->detach();
		}
	}

	public function detach() {
		if (!isset($this->stream)) {
			return null;
		}

		$result = $this->stream;
		unset($this->stream);
		$this->size = $this->uri = null;
		$this->readable = $this->writable = $this->seekable = false;

		return $result;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		try {
			if ($this->isSeekable()) {
				$this->seek(0);
			}

			return $this->getContents();
		} catch (Throwable $e) {
			if (PHP_VERSION_ID >= 70400) {
				throw $e;
			}

			if ($e instanceof Error) {
				trigger_error((string) $e, E_USER_ERROR);
			}

			return '';
		}
	}

	public function isSeekable() : bool {
		return $this->seekable;
	}

	/**
	 * @param int $offset
	 * @param int $whence
	 *
	 * @return void
	 */
	public function seek($offset, $whence = SEEK_SET) : void {
		if (!isset($this->stream)) {
			throw new RuntimeException('Stream is detached');
		}

		if (!$this->seekable) {
			throw new RuntimeException('Stream is not seekable');
		}

		if (-1 === fseek($this->stream, $offset, $whence)) {
			throw new RuntimeException('Unable to seek to stream position "'.$offset.'" with whence '.var_export($whence, true));
		}
	}

	public function getContents() : string {
		if (!isset($this->stream)) {
			throw new RuntimeException('Stream is detached');
		}

		if (false === $contents = @stream_get_contents($this->stream)) {
			throw new RuntimeException('Unable to read stream contents: '.(error_get_last()['message'] ?? ''));
		}

		return $contents;
	}

	public function getSize() : ?int {
		if (null !== $this->size) {
			return $this->size;
		}

		if (!isset($this->stream)) {
			return null;
		}

		// Clear the stat cache if the stream has a URI
		if ($uri = $this->getUri()) {
			clearstatcache(true, (string) $uri);
		}

		$stats = fstat($this->stream);
		if (isset($stats['size'])) {
			$this->size = $stats['size'];

			return $this->size;
		}

		return null;
	}

	/**
	 * @return string
	 */
	private function getUri() : string {
		if (false !== $this->uri) {
			$this->uri = $this->getMetadata('uri') ?? false;
		}

		return $this->uri;
	}

	/**
	 * @param string|null $key
	 *
	 * @return mixed
	 */
	public function getMetadata($key = null) : mixed {
		if (!isset($this->stream)) {
			return $key ? null : [];
		}

		$meta = stream_get_meta_data($this->stream);

		if (null === $key) {
			return $meta;
		}

		return $meta[$key] ?? null;
	}

	public function tell() : int {
		if (!isset($this->stream)) {
			throw new RuntimeException('Stream is detached');
		}

		if (false === $result = @ftell($this->stream)) {
			throw new RuntimeException('Unable to determine stream position: '.(error_get_last()['message'] ?? ''));
		}

		return $result;
	}

	public function eof() : bool {
		return !isset($this->stream) || feof($this->stream);
	}

	public function rewind() : void {
		$this->seek(0);
	}

	public function isWritable() : bool {
		return $this->writable;
	}

	public function write($string) : int {
		if (!isset($this->stream)) {
			throw new RuntimeException('Stream is detached');
		}

		if (!$this->writable) {
			throw new RuntimeException('Cannot write to a non-writable stream');
		}

		// We can't know the size after writing anything
		$this->size = null;

		if (false === $result = @fwrite($this->stream, $string)) {
			throw new RuntimeException('Unable to write to stream: '.(error_get_last()['message'] ?? ''));
		}

		return $result;
	}

	public function isReadable() : bool {
		return $this->readable;
	}

	public function read($length) : string {
		if (!isset($this->stream)) {
			throw new RuntimeException('Stream is detached');
		}

		if (!$this->readable) {
			throw new RuntimeException('Cannot read from non-readable stream');
		}

		if (false === $result = @fread($this->stream, $length)) {
			throw new RuntimeException('Unable to read from stream: '.(error_get_last()['message'] ?? ''));
		}

		return $result;
	}
}