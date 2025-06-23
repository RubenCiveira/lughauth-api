<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Domain;

use Psr\Http\Message\StreamInterface;

class Attach
{
    public function __construct(
        public readonly string $name,
        public readonly StreamInterface $stream
    ) {
    }
}
