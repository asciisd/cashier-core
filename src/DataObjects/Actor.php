<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\DataObjects;

/**
 * Who performed an admin money action — id and guard as scalars, because the
 * package cannot depend on any host admin model.
 */
readonly class Actor
{
    public function __construct(
        public int|string $id,
        public ?string $guard = null,
        public ?string $ip = null,
    ) {}

    /**
     * @return array{id: int|string, guard: ?string, ip: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'guard' => $this->guard,
            'ip' => $this->ip,
        ];
    }
}
