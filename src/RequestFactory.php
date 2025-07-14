<?php

namespace Lsr\Core\Requests;

use Lsr\Exceptions\DispatchBreakException;
use Lsr\Interfaces\RequestFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Serializer;
use function explode;
use function strtolower;
use function trim;

final readonly class RequestFactory implements RequestFactoryInterface
{

	private ServerRequestCreator $requestCreator;

	public function __construct(
		private Serializer $serializer,
	) {
		$psr17Factory = new Psr17Factory();
		$this->requestCreator = new ServerRequestCreator(
			$psr17Factory, // ServerRequestFactory
			$psr17Factory, // UriFactory
			$psr17Factory, // UploadedFileFactory
			$psr17Factory // StreamFactory
		);
	}

	public function getHttpRequest(): Request {
		return $this->fromPsrRequest($this->requestCreator->fromGlobals());
	}

	public function fromPsrRequest(ServerRequestInterface $request): Request {
		// Maybe parse JSON body
		foreach ($request->getHeader('content-type') as $headerValue) {
			if (strtolower(trim(explode(';', $headerValue, 2)[0])) === 'application/json') {
				$body = $request->getBody();
				$body->rewind();
				try {
					$data = $this->serializer->decode($body->getContents(), 'json');
				} catch (ExceptionInterface $e) {
					throw DispatchBreakException::createBadRequest(
						'Invalid JSON body: ' . $e->getMessage(),
					);
				}
				assert(is_array($data));
				$request = $request->withParsedBody($data);
				$body->rewind();
				break;
			}
		}

		return new Request($request);
	}

}