<?php

declare(strict_types=1);

namespace App\Services;

interface UserService
{
    public function find(int $id): ?array;
    public function store(array $data): array;
}
