<?php

namespace Lsr\Core\Requests\Enums;

use OpenApi\Attributes as OA;

#[OA\Schema(type: 'string')]
enum ErrorType: string
{
	case VALIDATION = 'validation_error';
	case DATABASE   = 'database_error';
	case INTERNAL   = 'internal_error';
	case NOT_FOUND  = 'resource_not_found_error';
	case ACCESS     = 'resource_access_error';
}
