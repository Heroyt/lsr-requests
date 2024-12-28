<?php
declare(strict_types=1);

namespace Mocks;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\StringLength;

class MockRequestObjQuery
{

	#[StringLength(min: 0, max: 100)]
	public ?string $filter = null;

	#[IntRange(min: 0)]
	public int $page = 0;

	/** @var string[]  */
	public array|string $tags = [];

	public function addTag(string $tag) : void {
		if (is_string($this->tags)) {
			$this->tags = [$this->tags];
		}
		assert(is_array($this->tags));
		$this->tags[] = $tag;
	}

}