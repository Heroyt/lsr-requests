<?php

namespace Lsr\Core\Requests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * PSR-7 response decorator class.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 */
readonly class Response implements ResponseInterface
{

	private Serializer $serializer;

	public function __construct(public ResponseInterface $psrResponse) {
		$normalizerContext = [
			AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object, string $format, array $context) {
				if (property_exists($object, 'code') && isset($object->code)) {
					return $object->code;
				}
				if (property_exists($object, 'id') && isset($object->id)) {
					return $object->id;
				}
				if (property_exists($object, 'name') && isset($object->name)) {
					return $object->name;
				}
				return null;
			},
		];
		$this->serializer = new Serializer(
			[
				new DateTimeNormalizer(),
				new BackedEnumNormalizer(),
				new JsonSerializableNormalizer(defaultContext: $normalizerContext),
				new ObjectNormalizer(defaultContext: $normalizerContext),
			], [
				new JsonEncoder(
					defaultContext: [
						                JsonDecode::ASSOCIATIVE => true,
						                JsonEncode::OPTIONS     => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
					                ]
				),
				new XmlEncoder(),
			],
		);
	}

	/**
	 * @param int                                  $status  Status code
	 * @param array<string,string>                 $headers Response headers
	 * @param string|resource|StreamInterface|null $body    Response body
	 * @param string                               $version Protocol version
	 * @param string|null                          $reason  Reason phrase (when empty a default will be used based on the status code)
	 */
	public static function create(int $status = 200, array $headers = [], mixed $body = null, string $version = '1.1', ?string $reason = null): Response {
		return new self(new \Nyholm\Psr7\Response($status, $headers, $body, $version, $reason));
	}

	/**
	 * @inheritDoc
	 */
	public function getProtocolVersion(): string {
		return $this->psrResponse->getProtocolVersion();
	}

	/**
	 * @inheritDoc
	 * @return Response
	 */
	public function withProtocolVersion(string $version): Response {
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
	 * @return Response
	 */
	public function withAddedHeader(string $name, $value): Response {
		return new self($this->psrResponse->withAddedHeader($name, $value));
	}

	/**
	 * @inheritDoc
	 * @return Response
	 */
	public function withoutHeader(string $name): Response {
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
	 * @return Response
	 */
	public function withStatus(int $code, string $reasonPhrase = ''): Response {
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
	 * @return Response
	 *
	 * @see Response::withBody()
	 */
	public function withStringBody(string $body): Response {
		return $this->withBody(RequestFactory::createStream($body));
	}

	/**
	 * @inheritDoc
	 * @return Response
	 */
	public function withBody(StreamInterface $body): Response {
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
	 * @return Response
	 * @see  Response::withBody()
	 * @see  Response::withHeader()
	 */
	public function withJsonBody(mixed $data): Response {
		$body = RequestFactory::createStream(
			$this->serializer->serialize($data, 'json'),
		);
		return $this->withBody($body)->withHeader('Content-Type', 'application/json');
	}

	/**
	 * @inheritDoc
	 * @return Response
	 */
	public function withHeader(string $name, $value): Response {
		return new self($this->psrResponse->withHeader($name, $value));
	}

	/**
	 * Return an instance with the provided value encoded as a XML string in the response body.
	 *
	 * @post Sets the response body to XML-encoded string
	 * @post Sets the Content-Type header to application/xml.
	 *
	 * @param mixed $data
	 *
	 * @return Response
	 * @see  Response::withBody()
	 * @see  Response::withHeader()
	 *
	 */
	public function withXmlBody(mixed $data): Response {
		$body = RequestFactory::createStream(
			$this->serializer->serialize($data, 'xml'),
		);
		return $this->withBody($body)->withHeader('Content-Type', 'application/xml');
	}
}