<?php
declare(strict_types=1);

namespace Lsr\Core\Requests\Validation;

use Lsr\Core\Requests\Request;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\ObjectValidation\Validator;
use Lsr\Serializer\Mapper;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

readonly class RequestValidationMapper
{

	public private(set) Request $request;

	public function __construct(
		private Mapper $denormalizer,
	) {
	}

	public function setRequest(Request $request): RequestValidationMapper {
		$this->request = $request;
		return $this;
	}


	/**
	 * @template T of object
	 *
	 * @param class-string<T> $class
	 *
	 * @return T
	 * @throws ValidationException On validation
	 * @throws ExceptionInterface On denormalization
	 */
	public function mapBodyToObject(string $class) : object {
		if (!isset($this->request)) {
			throw new \RuntimeException('Request is not set - call setRequest() before mapping');
		}
		/** @var T $object */
		$object = $this->denormalizer->map(
			$this->request->getParsedBody(),
			$class,
			[
				'disable_type_enforcement' => true
			]
		);

		new Validator()->validateAll($object);

		return $object;
	}

	/**
	 * @template T of object
	 *
	 * @param class-string<T> $class
	 *
	 * @return T
	 * @throws ValidationException On validation
	 * @throws ExceptionInterface On denormalization
	 */
	public function mapQueryToObject(string $class) : object {
		if (!isset($this->request)) {
			throw new \RuntimeException('Request is not set - call setRequest() before mapping');
		}
		/** @var T $object */
		$object = $this->denormalizer->map(
			$this->request->getQueryParams(),
			$class,
			[
				'disable_type_enforcement' => true
			]
		);

		new Validator()->validateAll($object);

		return $object;
	}

}