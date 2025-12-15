<?php

declare(strict_types=1);

namespace App\Traits;

trait AsActionTrait
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Run the action.
     *
     * @param mixed ...$params
     * @return mixed
     */
    public static function run(...$params): mixed
    {
        $action = static::make();

        $method = '__invoke';

        return $action->{$method}(...$params);
    }
}
