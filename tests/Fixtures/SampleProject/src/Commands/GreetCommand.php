<?php

declare(strict_types=1);

namespace App\Commands;

class GreetCommand
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
