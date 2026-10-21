<?php

namespace App\Portal\ReadOnly;

interface SelectReader
{
    /** @return array<int, array<string, mixed>> */
    public function select(string $sql, array $bindings): array;
}
