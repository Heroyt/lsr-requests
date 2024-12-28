<?php
declare(strict_types=1);

namespace TestCases;

use Generator;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use Mocks\MockRequestObj;
use Mocks\MockRequestObjQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class RequestValidationMapperTest extends TestCase
{

	public static function getValidRequests(): Generator {
		$body = [
			'name' => 'John Doe',
			'age'  => 32,
			'tags' => [
				'tag1',
				'tag2',
			],
		];
		$query = [
			'filter' => 'test',
			'page'   => 1,
			'tags'   => [
				'tag1',
			],
		];
		$request = Request::create(
			'POST',
			'/?' . http_build_query($query),
			['Content-Type' => 'application/json'],
			json_encode($body)
		);
		yield [$request, $body, $query];

		$body = [
			'name' => 'Jane Doe',
			'age'  => 28,
			'tags' => [
				'tag3',
			],
		];
		$query = [
			'tags' => [
				'tag1',
			],
		];
		$request = Request::create(
			'POST',
			'/?' . http_build_query($query),
			['Content-Type' => 'application/json'],
			json_encode($body)
		);
		yield [$request, $body, $query];

		$body = [
			'name'    => 'Test Testovič',
			'age'     => 99,
			'tags'    => [
				'test1',
				'test2',
				'test3',
				'test5',
			],
			'address' => [
				'street' => 'Testovací',
				'number' => 123,
				'city'   => 'Testov',
			],
		];
		$query = [
			'page' => 10,
		];
		$request = Request::create(
			'POST',
			'/?' . http_build_query($query),
			['Content-Type' => 'application/json'],
			json_encode($body)
		);
		yield [$request, $body, $query];
	}

	public static function getInvalidQueryRequests(): Generator {
		$query = [
			'page' => -1,
		];
		$request = Request::create(
			'GET',
			'/?'.http_build_query($query)
		);
		yield [$request];
	}

	public static function getInvalidBodyRequests(): Generator {
		$body = [
			'name' => '',
			'age'  => 32,
			'tags' => [
				'tag1',
				'tag2',
			],
		];
		$request = Request::create(
			'POST',
			'/',
			['Content-Type' => 'application/json'],
			json_encode($body)
		);
		yield [$request];

		$body = [
			'age'  => 32,
			'tags' => [
				'tag1',
				'tag2',
			],
		];
		$request = Request::create(
			'POST',
			'/',
			['Content-Type' => 'application/json'],
			json_encode($body)
		);
		yield [$request];
	}

	/**
	 * @param array{name:string,age:int,tags:string[]}              $body
	 * @param array{filter?:string,page?:int,tags?:string[]|string} $query
	 */
	#[DataProvider('getValidRequests')]
	public function testMapToObject(Request $request, array $body, array $query): void {
		$mapper = $this->getMapper();
		$mapper->setRequest($request);
		$object = $mapper->mapBodyToObject(MockRequestObj::class);
		self::assertInstanceOf(MockRequestObj::class, $object);

		self::assertSame($body['name'], $object->name);
		self::assertSame($body['age'], $object->age);
		self::assertEquals($body['tags'], $object->tags);

		$object = $mapper->mapQueryToObject(MockRequestObjQuery::class);
		self::assertInstanceOf(MockRequestObjQuery::class, $object);
		self::assertSame($query['filter'] ?? null, $object->filter);
		self::assertSame($query['page'] ?? 0, $object->page);
		self::assertSame($query['tags'] ?? [], $object->tags);
	}

	#[DataProvider('getInvalidBodyRequests')]
	public function testInvalidMapBodyToObject(Request $request): void {
		$mapper = $this->getMapper();
		$mapper->setRequest($request);

		$this->expectException(ValidationException::class);
		$obj = $mapper->mapBodyToObject(MockRequestObj::class);
		var_dump($obj);
	}

	#[DataProvider('getInvalidQueryRequests')]
	public function testInvalidMapQueryToObject(Request $request): void {
		$mapper = $this->getMapper();
		$mapper->setRequest($request);

		$this->expectException(ValidationException::class);
		$mapper->mapQueryToObject(MockRequestObjQuery::class);
	}

	public function testUninitializedMapBody() : void {
		$mapper = $this->getMapper();
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Request is not set - call setRequest() before mapping');
		$mapper->mapBodyToObject(MockRequestObj::class);
	}

	public function testUninitializedMapQuery() : void {
		$mapper = $this->getMapper();
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Request is not set - call setRequest() before mapping');
		$mapper->mapQueryToObject(MockRequestObj::class);
	}

	private function getMapper(): RequestValidationMapper {
		return new RequestValidationMapper(
			new Mapper(
				new Serializer(
					[
						new ArrayDenormalizer(),
						new DateTimeNormalizer(),
						new DibiRowNormalizer(),
						new BackedEnumNormalizer(),
						new JsonSerializableNormalizer(),
						new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor(),),
					]
				)
			)
		);
	}
}
