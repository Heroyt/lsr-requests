<?php
declare(strict_types=1);

namespace Mocks;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class MockRequestObj
{

	#[StringLength(min: 5, max: 100), Required]
	public string $name;

	#[IntRange(min: 1), Required]
	public int $age;

	/** @var string[]  */
	public array $tags = [];

	public ?MockRequestObjInner $address = null;

	public function addTag(string $tag) : void {
		$this->tags[] = $tag;
	}

}