<?php

declare(strict_types=1);

namespace App\Controllers;

class UserController
{
    public function index(): array
    {
        return [];
    }

    public function show(int $id): string
    {
        return "User {$id}";
    }
}
