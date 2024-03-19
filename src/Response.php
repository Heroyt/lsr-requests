<?php

namespace Lsr\Core\Requests;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 response decorator class.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 */
readonly class Response implements ResponseInterface
{

	public function __construct(public ResponseInterface $psrResponse) {
	}

	/**
	 * @inheritDoc
	 */
	public function getProtocolVersion(): string {
		return $this->psrResponse->getProtocolVersion();
	}

	/**
	 * @inheritDoc
	 */
	public function withProtocolVersion(string $version): static {
		return new self($this->psrResponse->withProtocolVersion($version));
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaders(): array {
		return $this->psrResponse->getHeaders();
	}

	/**
	 * @inheritDoc
	 */
	public function hasHeader(string $name): bool {
		return $this->psrResponse->hasHeader($name);
	}

	/**
	 * @inheritDoc
	 */
	public function getHeader(string $name): array {
		return $this->psrResponse->getHeader($name);
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderLine(string $name): string {
		return $this->psrResponse->getHeaderLine($name);
	}

	/**
	 * @inheritDoc
	 */
	public function withAddedHeader(string $name, $value): static {
		return new self($this->psrResponse->withAddedHeader($name, $value));
	}

	/**
	 * @inheritDoc
	 */
	public function withoutHeader(string $name): static {
		return new self($this->psrResponse->withoutHeader($name));
	}

	/**
	 * @inheritDoc
	 */
	public function getBody(): StreamInterface {
		return $this->psrResponse->getBody();
	}

	/**
	 * @inheritDoc
	 */
	public function getStatusCode(): int {
		return $this->psrResponse->getStatusCode();
	}

	/**
	 * @inheritDoc
	 */
	public function withStatus(int $code, string $reasonPhrase = ''): static {
		return new self($this->psrResponse->withStatus($code, $reasonPhrase));
	}

	/**
	 * @inheritDoc
	 */
	public function getReasonPhrase(): string {
		return $this->psrResponse->getReasonPhrase();
	}

	/**
	 * Return an instance with the provided body set.
	 *
	 * This transforms the string into a Stream interface and calls Response::withBody() with it.
	 *
	 * @param string $body
	 *
	 * @return $this
	 * @see Response::withBody()
	 *
	 */
	public function withStringBody(string $body): static {
		return $this->withBody(RequestFactory::createStream($body));
	}

	/**
	 * @inheritDoc
	 */
	public function withBody(StreamInterface $body): static {
		return new self($this->psrResponse->withBody($body));
	}

	/**
	 * Return an instance with the provided value encoded as a JSON string in the response body.
	 *
	 * @post Sets the response body to JSON-encoded string
	 * @post Sets the Content-Type header to application/json.
	 *
	 * @param mixed $data
	 *
	 * @return $this
	 * @throws JsonException
	 * @see  Response::withBody()
	 * @see  Response::withHeader()
	 *
	 */
	public function withJsonBody(mixed $data): static {
		$body = RequestFactory::createStream(
			json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
		);
		return $this->withBody($body)->withHeader('Content-Type', 'application/json');
	}

	/**
	 * @inheritDoc
	 */
	public function withHeader(string $name, $value): static {
		return new self($this->psrResponse->withHeader($name, $value));
	}
}