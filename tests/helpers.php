<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2020 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Factory;

if (!\function_exists('app')) {
    /**
     * Minimal service locator for laravel/legacy-factories, whose factory()
     * helper resolves the factory through Laravel's app() helper.
     *
     * @param null|string $abstract
     *
     * @return mixed
     */
    function app($abstract = null)
    {
        static $container;

        if ($container === null) {
            $container = new Container();

            $container->singleton(Factory::class, static function () {
                return Factory::construct(\Faker\Factory::create(), __DIR__ . '/../factories');
            });
        }

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract);
    }
}
