<?php
declare(strict_types=1);

namespace Mocks;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\ObjectValidation\Attributes\Required;
use Lsr\ObjectValidation\Attributes\StringLength;

class MockRequestObjInner
{

	#[StringLength(min: 5, max: 100), Required]
	public string $street;

	#[StringLength(min: 5, max: 100), Required]
	public string $city;

	#[IntRange(min: 1), Required]
	public int $number;

}