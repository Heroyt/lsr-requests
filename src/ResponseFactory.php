<?php
declare(strict_types=1);

namespace Lsr\Core\Requests;

use Lsr\Interfaces\ResponseFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Serializer\Serializer;

final readonly class ResponseFactory implements ResponseFactoryInterface
{

	public function __construct(
		private Serializer $serializer,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function createJsonResponse(
		mixed   $data,
		int     $code = 200,
		array   $headers = [],
		string  $version = '1.1',
		?string $reason = null
	): ResponseInterface {
		return $this->createResponse(
			$code,
			$headers,
			$this->createStream($this->serializer->serialize($data, 'json')),
			$version,
			$reason
		)->withHeader('Content-Type', 'application/json');
	}

	/**
	 * @inheritDoc
	 */
	public function createResponse(
		int                         $code = 200,
		array                       $headers = [],
		StreamInterface|string|null $body = null,
		string                      $version = '1.1',
		?string                     $reason = null,
	): ResponseInterface {
		return Response::create($code, $headers, $body, $version, $reason);
	}

	/**
	 * @inheritDoc
	 */
	public function createStream(string $content): StreamInterface {
		return new Psr17Factory()->createStream($content);
	}

	/**
	 * @inheritDoc
	 */
	public function createResourceStream($resource): StreamInterface {
		return new Psr17Factory()->createStreamFromResource($resource);
	}
}