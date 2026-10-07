<?php

declare(strict_types=1);

namespace Celema\Console\Tests\Fixtures;

enum Format: string
{
	case Csv = 'csv';
	case Json = 'json';
}
