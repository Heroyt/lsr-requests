<?php

namespace Lsr\Core\Requests;

use JsonException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use function explode;
use function strtolower;
use function trim;

class RequestFactory
{

	/**
	 * Create a new stream from string content
	 *
	 * @param string $content
	 *
	 * @return StreamInterface
	 */
	public static function createStream(string $content): StreamInterface {
		return (new Psr17Factory())->createStream($content);
	}

	/**
	 * @return Request
	 * @throws JsonException
	 */
	public static function getHttpRequest(): Request {
		$psr17Factory = new Psr17Factory();

		$creator = new ServerRequestCreator(
			$psr17Factory, // ServerRequestFactory
			$psr17Factory, // UriFactory
			$psr17Factory, // UploadedFileFactory
			$psr17Factory  // StreamFactory
		);

		return self::fromPsrRequest($creator->fromGlobals());
	}

	/**
	 * Create a new instance of the Request decorator from any PSR-7 ServerRequestInterface.
	 *
	 * @param ServerRequestInterface $request
	 *
	 * @return Request
	 * @throws JsonException
	 */
	public static function fromPsrRequest(ServerRequestInterface $request): Request {
		// Maybe parse JSON body
		foreach ($request->getHeader('content-type') as $headerValue) {
			if (strtolower(trim(explode(';', $headerValue, 2)[0])) === 'application/json') {
				$body = $request->getBody();
				$request = $request->withParsedBody(
					json_decode($body->getContents(), true, 512, JSON_THROW_ON_ERROR)
				);
				$body->rewind();
				break;
			}
		}

		return new Request($request);
	}

}