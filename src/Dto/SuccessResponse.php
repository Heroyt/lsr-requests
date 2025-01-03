<?php

namespace Lsr\Core\Requests\Dto;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'SuccessResponse', type: 'object')]
readonly class SuccessResponse implements \JsonSerializable
{

	/**
	 * @param string      $message
	 * @param string|null $detail
	 * @param array<string,mixed>|null  $values
	 */
	public function __construct(
		#[OA\Property(example: 'Message')]
		public string     $message = 'Success',
		#[OA\Property(example: 'Description')]
		public ?string    $detail = null,
		#[OA\Property(type: 'object', example: ['key1' => 'value1', 'key2' => 'value2'])]
		public ?array     $values = null,
	){}

    /**
     * @inheritDoc
     * @return array{message:string,detail?:string|null,values?:null|array<string,mixed>}
     */
    public function jsonSerialize() : array {
	    $data = [
		    'message' => $this->message,
	    ];

		if (!empty($this->detail)) {
		    $data['detail'] = $this->detail;
	    }

	    if (!empty($this->values)) {
		    $data['values'] = $this->values;
	    }

		return $data;
    }
}