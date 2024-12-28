<?php

namespace Lsr\Core\Requests\Dto;

use JsonSerializable;
use Lsr\Core\Requests\Enums\ErrorType;
use OpenApi\Attributes as OA;
use Dibi\Exception;
use Throwable;

#[OA\Schema(schema: 'ErrorResponse', type: 'object')]
readonly class ErrorResponse implements JsonSerializable
{
	/**
	 * @param string         $title
	 * @param ErrorType      $type
	 * @param string|null    $detail
	 * @param Throwable|null $exception
	 * @param array<string,mixed>|null     $values
	 */
	public function __construct(
		#[OA\Property(example: 'Error title')]
		public string     $title,
		#[OA\Property]
		public ErrorType  $type = ErrorType::INTERNAL,
		#[OA\Property(example: 'Error description')]
		public ?string    $detail = null,
		#[OA\Property(
			properties: [
				new OA\Property('message', type: 'string', example: 'Some exception description'),
				new OA\Property('code', type: 'int', example: 123),
				new OA\Property(
					         'trace',
					type   : 'array',
					items  : new OA\Items(type: 'object'),
					example: [['file' => 'index.php', 'line' => 1, 'function' => 'abc', 'args' => ['Argument value']]],
				),
			],
			type      : 'object',
		)]
		public ?Throwable $exception = null,
		#[OA\Property(type: 'object', example: ['key1' => 'value1', 'key2' => 'value2'])]
		public ?array     $values = null,
	) {
	}

	/**
	 * @return array{
	 *     title:string,
	 *     type:string,
	 *     detail?:string,
	 *     values?:array<string,mixed>,
	 *     exception?:array{
	 *        message:string,
	 *        code:int|string,
	 *        trace:list<array{function: string, line?: int, file?: string, class?: class-string, type?: string, args?: array<mixed>, object?: object}>},
	 *        sql?:string
	 *     }
	 * }
	 */
	public function jsonSerialize(): array {
		$error = [
			'type'  => $this->type->value,
			'title' => $this->title,
		];

		if (!empty($this->detail)) {
			$error['detail'] = $this->detail;
		}

		if (!empty($this->values)) {
			$error['values'] = $this->values;
		}

		if (isset($this->exception)) {
			$error['exception'] = [
				'message' => $this->exception->getMessage(),
				'code'    => $this->exception->getCode(),
				'trace'   => $this->exception->getTrace(),
			];
			if (method_exists($this->exception, 'getSql')) {
				$error['sql'] = $this->exception->getSql();
				assert(is_string($error['sql']));
			}
		}

		return $error;
	}
}