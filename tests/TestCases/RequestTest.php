<?php

namespace TestCases;

use Generator;
use Lsr\Core\Requests\Request;
use Lsr\Enums\RequestMethod;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7\Uri;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RequestTest extends TestCase
{

	private static ServerRequestCreator $creator;

	public static function getDummyRequests(): Generator {
		$creator = self::getRequestCreator();

		$data = [
			'server'  => [
				'SERVER_PROTOCOL' => 'HTTP/1.1',
				'REQUEST_METHOD'  => 'GET',
				'HTTPS'           => 'off',
				'SERVER_PORT'     => 80,
				'HTTP_HOST'       => 'localhost',
				'REQUEST_URI'     => '/',
				'QUERY_STRING'    => 'key1=hello',
				'HTTP_CLIENT_IP'  => self::generateRandomIPv4(),
			],
			'headers' => [
				'Accept-Language' => 'cs-CZ,cs;q=0.9',
				'Accept-Encoding' => 'gzip, deflate, br',
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			],
			'cookie'  => [
				'cookie1' => md5(uniqid('', true)),
				'cookie2' => md5(uniqid('', true)),
			],
			'get'     => [
				'key1' => 'hello',
			],
		];
		yield [
			'psrRequest'   => $creator->fromArrays(...$data),
			'data'         => $data,
			'expectedPath' => [],
		];

		$data = [
			'server'  => [
				'SERVER_PROTOCOL'      => 'HTTP/1.1',
				'REQUEST_METHOD'       => 'POST',
				'HTTPS'                => 'off',
				'SERVER_PORT'          => 80,
				'HTTP_HOST'            => 'localhost',
				'REQUEST_URI'          => '/post',
				'QUERY_STRING'         => '',
				'HTTP_X_FORWARDED_FOR' => self::generateRandomIPv4(),
			],
			'headers' => [
				'Content-Type'     => 'application/x-www-form-urlencoded',
				'Accept-Language'  => 'cs-CZ,cs;q=0.9',
				'Accept-Encoding'  => 'gzip, deflate, br',
				'Accept'           => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'X-Requested-With' => 'XMLHttpRequest',
			],
			'post'    => [
				'key1' => 'value1',
				'key2' => 123,
			],
		];
		yield [
			'psrRequest'   => $creator->fromArrays(...$data),
			'data'         => $data,
			'expectedPath' => ['post'],
		];

		$data = [
			'server'  => [
				'SERVER_PROTOCOL' => 'HTTP/1.1',
				'REQUEST_METHOD'  => 'POST',
				'HTTPS'           => 'off',
				'SERVER_PORT'     => 80,
				'HTTP_HOST'       => 'localhost',
				'REQUEST_URI'     => '/post',
				'QUERY_STRING'    => '',
				'REMOTE_ADDR'     => self::generateRandomIPv4(),
			],
			'headers' => [
				'Content-Type'     => 'application/json',
				'Accept-Language'  => 'cs-CZ,cs;q=0.9',
				'Accept-Encoding'  => 'gzip, deflate, br',
				'Accept'           => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'X-Requested-With' => 'Postman',
			],
			'cookie'  => [
				'cookie1' => md5(uniqid('', true)),
				'cookie2' => md5(uniqid('', true)),
				'user'    => 'testing',
			],
			'body'    => '{"key1":"test","key2":[1,2,3]}',
			'files'   => [
				'file' => [
					'name'      => 'image.png',
					'type'      => 'image/png',
					'size'      => 1_600_000,
					'tmp_name'  => md5(uniqid('', true)),
					'error'     => UPLOAD_ERR_OK,
					'full_path' => 'image.png',
				],
			],
		];
		yield [
			'psrRequest'   => $creator->fromArrays(...$data),
			'data'         => $data,
			'expectedPath' => ['post'],
		];

		$data = [
			'server'  => [
				'SERVER_PROTOCOL' => 'HTTP/1.1',
				'REQUEST_METHOD'  => 'POST',
				'HTTPS'           => 'off',
				'SERVER_PORT'     => 80,
				'HTTP_HOST'       => 'localhost',
				'REQUEST_URI'     => '/index.php',
				'QUERY_STRING'    => 'p[]=test&p[]=post',
				'HTTP_CLIENT_IP'  => self::generateRandomIPv4(),
			],
			'headers' => [
				'Content-Type'    => 'application/json',
				'Accept-Language' => 'cs-CZ,cs;q=0.9',
				'Accept-Encoding' => 'gzip, deflate, br',
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			],
			'get'     => ['p' => ['test', 'post']],
			'body'    => '{"key1":"test","key2":[1,2,3]}',
			'files'   => [
				'file' => [
					'name'      => ['image.png', 'file.txt'],
					'type'      => ['image/png', 'text/plain'],
					'size'      => [1_600_000, 1000],
					'tmp_name'  => [md5(uniqid('', true)), md5(uniqid('', true))],
					'error'     => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
					'full_path' => ['image.png', 'file.txt'],
				],
			],
		];
		yield [
			'psrRequest'   => $creator->fromArrays(...$data),
			'data'         => $data,
			'expectedPath' => ['test', 'post'],
		];
	}

	private static function getRequestCreator(): ServerRequestCreator {
		if (!isset(self::$creator)) {
			$psr17Factory = new Psr17Factory();

			self::$creator = new ServerRequestCreator(
				$psr17Factory, // ServerRequestFactory
				$psr17Factory, // UriFactory
				$psr17Factory, // UploadedFileFactory
				$psr17Factory  // StreamFactory
			);
		}
		return self::$creator;
	}

	private static function generateRandomIPv4(): string {
		return rand(1, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
	}

	#[DataProvider('getDummyRequests')]
	public function testProtocolVersion(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals($psrRequest->getProtocolVersion(), $request->getProtocolVersion());

		// Modify protocol version
		$request1 = $request->withProtocolVersion('99');

		// The original request remains unchanged
		self::assertNotSame($request, $request1);
		self::assertNotEquals('99', $request->getProtocolVersion());

		// The new request changed
		self::assertEquals('99', $request1->getProtocolVersion());
	}

	#[DataProvider('getDummyRequests')]
	public function testHeaders(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		// Test non-existent header
		$invalidHeaderName = 'x-unknown-header';
		self::assertFalse($request->hasHeader($invalidHeaderName));
		self::assertEmpty($request->getHeader($invalidHeaderName));
		self::assertEmpty($request->getHeaderLine($invalidHeaderName));

		foreach ($psrRequest->getHeaders() as $name => $values) {
			self::assertTrue($request->hasHeader($name));
			self::assertEquals($values, $request->getHeader($name));
		}

		self::assertEquals($psrRequest->getHeaders(), $request->getHeaders());

		// Set the invalid header
		$headerValue = md5((string)rand());  // Random value
		$request1 = $request->withAddedHeader($invalidHeaderName, $headerValue);

		// Validate that the original request remains unchanged
		self::assertFalse($request->hasHeader($invalidHeaderName));
		self::assertEmpty($request->getHeader($invalidHeaderName));
		self::assertEmpty($request->getHeaderLine($invalidHeaderName));

		// Validate that the new request has the header
		self::assertTrue($request1->hasHeader($invalidHeaderName));
		self::assertEquals([$headerValue], $request1->getHeader($invalidHeaderName));
		self::assertNotEmpty($request1->getHeaderLine($invalidHeaderName));

		// WithAddedHeader should add another value to the header
		$headerValue2 = md5((string)rand()); // Random value
		$request2 = $request1->withAddedHeader($invalidHeaderName, $headerValue2);
		self::assertTrue($request2->hasHeader($invalidHeaderName));
		self::assertEquals([$headerValue, $headerValue2], $request2->getHeader($invalidHeaderName));

		// WithHeader should replace the header completely
		$headerValue3 = md5((string)rand()); // Random value
		$request3 = $request2->withHeader($invalidHeaderName, $headerValue3);
		self::assertTrue($request3->hasHeader($invalidHeaderName));
		self::assertEquals([$headerValue3], $request3->getHeader($invalidHeaderName));

		// Try to remove a header
		$request4 = $request3->withoutHeader($invalidHeaderName);
		self::assertTrue($request3->hasHeader($invalidHeaderName));
		self::assertFalse($request4->hasHeader($invalidHeaderName));
	}

	#[DataProvider('getDummyRequests')]
	public function testMethod(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals($psrRequest->getMethod(), $request->getMethod());
		self::assertEquals(RequestMethod::from(strtoupper($psrRequest->getMethod())), $request->getType());

		// Test change
		$request1 = $request->withMethod('UNKNOWN');

		// Original requests remains the same
		self::assertEquals($psrRequest->getMethod(), $request->getMethod());
		self::assertNotEquals('UNKNOWN', $request->getMethod());

		// New request changed
		self::assertEquals('UNKNOWN', $request1->getMethod());
	}

	#[DataProvider('getDummyRequests')]
	public function testBody(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		$body = $psrRequest->getBody()->getContents();
		$psrRequest->getBody()->rewind();

		self::assertEquals($body, $request->getBody()->getContents());

		// Try to change body
		$story = "In the neon-lit streets of Neo-Tokyo, Akira sat in front of his computer, his eyes reflecting the glow of the screen as lines of code scrolled past like digital cherry blossoms in the wind. As a seasoned programmer and avid anime enthusiast, Akira's passion for both worlds often intertwined, manifesting in subtle references woven into his creations. Today, as he worked on a new virtual reality game, he couldn't resist adding a touch of nostalgia for his favorite anime series. Hidden within the game's intricate architecture were nods to legendary anime characters – a glitch resembling the iconic Sharingan eye of a powerful ninja, a secret level inspired by the spirited adventures of a group of magical girls. These references were like whispers of a shared language, meant only for those who knew where to look. As Akira polished the final lines of code, he couldn't help but smile, knowing that he had infused his creation with the essence of his beloved anime, waiting for fellow fans to uncover these hidden gems and share in his excitement. And as the program compiled, he whispered to himself, 'Just a touch of my own anime magic.' - ChatGPT";
		$stream = (new Psr17Factory())->createStream($story);
		$request1 = $request->withBody($stream);
		self::assertNotSame($request, $request1);
		self::assertEquals($story, $request1->getBody()->getContents());
	}

	#[DataProvider('getDummyRequests')]
	public function testUri(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals((string)$psrRequest->getUri(), (string)$request->getUri());

		$uriString = self::generateRandomURI();
		$uri = new Uri($uriString);
		$request1 = $request->withUri($uri);

		self::assertNotSame($request, $request1);
		self::assertEquals($uriString, (string)$request1->getUri());
	}

	private static function generateRandomURI(): string {
		$schemes = ['http', 'https', 'ftp', 'mailto'];
		$tlds = ['com', 'org', 'net', 'gov', 'edu'];
		$characters = 'abcdefghijklmnopqrstuvwxyz0123456789';

		// Generate scheme
		$scheme = $schemes[array_rand($schemes)];

		// Generate hostname
		$hostname = self::generateRandomString(10, $characters) . '.' . $tlds[array_rand($tlds)];

		// Generate path
		$path = '/' . self::generateRandomString(rand(1, 5), $characters);

		// Generate query
		$query = '';
		$numQueryParams = rand(0, 3);
		if ($numQueryParams > 0) {
			$query .= '?';
			for ($i = 0; $i < $numQueryParams; $i++) {
				$query .= self::generateRandomString(8, $characters) . '=' . self::generateRandomString(8, $characters);
				if ($i < $numQueryParams - 1) {
					$query .= '&';
				}
			}
		}

		// Generate URI
		return "$scheme://$hostname$path$query";
	}

	private static function generateRandomString(int $length, string $characters): string {
		$str = '';
		$max = strlen($characters) - 1;
		for ($i = 0; $i < $length; $i++) {
			$str .= $characters[rand(0, $max)];
		}
		return $str;
	}

	#[DataProvider('getDummyRequests')]
	public function testParsedBody(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals($data['post'] ?? null, $request->getParsedBody());
		self::assertEquals($psrRequest->getParsedBody(), $request->getParsedBody());

		if (in_array($request->getType(), [RequestMethod::POST, RequestMethod::PUT, RequestMethod::UPDATE], true)) {
			foreach ($request->getParsedBody() as $key => $value) {
				self::assertEquals($value, $request->getPost($key));
			}
		}

		// Test change
		$expected = ['test' => uniqid('', true)];
		$request1 = $request->withParsedBody($expected);

		self::assertNotSame($request, $request1);
		self::assertEquals($expected, $request1->getParsedBody());

		foreach ($expected as $key => $value) {
			self::assertEquals($value, $request1->getPost($key));
		}

		self::assertNull($request1->getPost('unknown-key'));
	}

	#[DataProvider('getDummyRequests')]
	public function testQueryParams(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals($data['get'] ?? [], $request->getQueryParams());
		self::assertEquals($psrRequest->getQueryParams(), $request->getQueryParams());

		foreach ($data['get'] ?? [] as $key => $value) {
			self::assertEquals($value, $request->getGet($key));
		}
		self::assertNull($request->getGet('invalid-key'));
		self::assertEquals(123, $request->getGet('invalid-key', 123));

		// Test change
		$params = ['test' => uniqid('', true)];
		$request1 = $request->withQueryParams($params);
		self::assertNotSame($request, $request1);
		self::assertEquals($params, $request1->getQueryParams());
	}

	#[DataProvider('getDummyRequests')]
	public function testRequestTarget(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		$originalTarget = $psrRequest->getUri()->getPath();
		$query = $psrRequest->getUri()->getQuery();
		if (!empty($query)) {
			$originalTarget .= '?' . $query;
		}

		self::assertEquals($originalTarget, $request->getRequestTarget());

		$target = md5((string)rand());  // Random value
		$request1 = $request->withRequestTarget($target);

		self::assertEquals($target, $request1->getRequestTarget());
	}

	#[DataProvider('getDummyRequests')]
	public function testIsAjax(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertSame(
			$psrRequest->hasHeader('x-requested-with') && in_array(
				'xmlhttprequest',
				array_map(static fn(string $val) => strtolower(trim($val)),
					$psrRequest->getHeader('x-requested-with')),
				true
			),
			$request->isAjax()
		);
	}

	#[DataProvider('getDummyRequests')]
	public function testPath(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		$path = $request->getPath();
		self::assertEquals($expectedPath, $path);
	}

	#[DataProvider('getDummyRequests')]
	public function testServerParams(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals($data['server'], $request->getServerParams());
		self::assertEquals($psrRequest->getServerParams(), $request->getServerParams());
	}

	#[DataProvider('getDummyRequests')]
	public function testCookies(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEquals($data['cookie'] ?? [], $request->getCookieParams());
		self::assertEquals($psrRequest->getCookieParams(), $request->getCookieParams());

		// Test change
		$cookies = ['cookie1' => uniqid('', true), 'cookie2' => uniqid('', true)];
		$request1 = $request->withCookieParams($cookies);

		self::assertNotSame($request, $request1);
		self::assertNotEquals($cookies, $request->getCookieParams());
		self::assertEquals($cookies, $request1->getCookieParams());
	}

	#[DataProvider('getDummyRequests')]
	public function testFiles(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		//self::assertEquals($data['files'] ?? [], $request->getUploadedFiles());
		self::assertEquals($psrRequest->getUploadedFiles(), $request->getUploadedFiles());
		foreach ($request->getUploadedFiles() as $key => $file) {
			if (is_array($file)) {
				foreach ($file as $key2 => $fileInner) {
					if ($fileInner instanceof UploadedFile) {
						self::assertEquals($data['files'][$key]['name'][$key2], $fileInner->getClientFilename());
						self::assertEquals($data['files'][$key]['error'][$key2], $fileInner->getError());
						self::assertEquals($data['files'][$key]['size'][$key2], $fileInner->getSize());
					}
				}
			}

			if ($file instanceof UploadedFile) {
				self::assertEquals($data['files'][$key]['name'], $file->getClientFilename());
				self::assertEquals($data['files'][$key]['error'], $file->getError());
				self::assertEquals($data['files'][$key]['size'], $file->getSize());
			}
		}

		// Test change
		$files = [
			'newFile' => [
				'name'      => 'image.jpg',
				'type'      => 'image/jpeg',
				'size'      => 1_600_123,
				'tmp_name'  => md5(uniqid('', true)),
				'error'     => UPLOAD_ERR_CANT_WRITE,
				'full_path' => 'image.jpg',
			],
		];
		$request1 = $request->withUploadedFiles($files);

		self::assertNotSame($request, $request1);
		self::assertNotEquals($files, $request->getUploadedFiles());
		self::assertEquals($files, $request1->getUploadedFiles());
	}

	#[DataProvider('getDummyRequests')]
	public function testAttributes(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);

		self::assertEmpty($request->getAttributes());

		$attributes = [
			'a'    => uniqid('', true),
			'b'    => md5(uniqid('', true)),
			'test' => 'test',
		];
		$request1 = $request->withAttribute('a', $attributes['a']);
		$request2 = $request1->withAttribute('b', $attributes['b']);
		$request3 = $request2->withAttribute('test', $attributes['test']);
		$request4 = $request3->withoutAttribute('a');
		$request5 = $request3->withoutAttribute('c');

		self::assertNotSame($request, $request1);
		self::assertNotSame($request, $request2);
		self::assertNotSame($request, $request3);
		self::assertNotSame($request, $request4);
		self::assertNotSame($request, $request5);
		self::assertNotSame($request1, $request2);
		self::assertNotSame($request2, $request3);
		self::assertNotSame($request3, $request4);
		self::assertNotSame($request3, $request5);

		self::assertEquals(['a' => $attributes['a']], $request1->getAttributes());
		self::assertEquals(['a' => $attributes['a'], 'b' => $attributes['b']], $request2->getAttributes());
		self::assertEquals($attributes, $request3->getAttributes());
		self::assertEquals(['b' => $attributes['b'], 'test' => $attributes['test']], $request4->getAttributes());
		self::assertEquals($attributes, $request5->getAttributes());

		self::assertEquals($attributes['a'], $request1->getAttribute('a'));
		self::assertNull($request1->getAttribute('b'));
		self::assertEquals(123, $request1->getAttribute('test', 123));

		self::assertEquals($attributes['a'], $request2->getAttribute('a'));
		self::assertEquals($attributes['b'], $request2->getAttribute('b'));
		self::assertEquals('hello', $request2->getAttribute('test', 'hello'));

		self::assertEquals($attributes['a'], $request3->getAttribute('a'));
		self::assertEquals($attributes['b'], $request3->getAttribute('b'));
		self::assertEquals($attributes['test'], $request3->getAttribute('test'));

		self::assertNull($request4->getAttribute('a'));
		self::assertEquals($attributes['b'], $request4->getAttribute('b'));
		self::assertEquals($attributes['test'], $request4->getAttribute('test'));

		self::assertEquals($attributes['a'], $request5->getAttribute('a'));
		self::assertEquals($attributes['b'], $request5->getAttribute('b'));
		self::assertEquals($attributes['test'], $request5->getAttribute('test'));
		self::assertNull($request5->getAttribute('c'));
	}

	#[DataProvider('getDummyRequests')]
	public function testIp(ServerRequestInterface $psrRequest, array $data, array $expectedPath): void {
		$request = new Request($psrRequest);
		$ip = '';
		if (isset($data['server']['HTTP_CLIENT_IP'])) {
			$ip = $data['server']['HTTP_CLIENT_IP'];
		}
		else if (isset($data['server']['HTTP_X_FORWARDED_FOR'])) {
			$ip = $data['server']['HTTP_X_FORWARDED_FOR'];
		}
		else if (isset($data['server']['REMOTE_ADDR'])) {
			$ip = $data['server']['REMOTE_ADDR'];
		}

		self::assertEquals($ip, $request->getIp());
	}
}
