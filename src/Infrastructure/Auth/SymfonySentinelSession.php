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

namespace OpenCFP\Infrastructure\Auth;

use Cartalyst\Sentinel\Sessions\SessionInterface as SentinelSessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface as SymfonySessionInterface;

final class SymfonySentinelSession implements SentinelSessionInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $key;

    public function __construct(RequestStack $requestStack, $key = null)
    {
        $this->requestStack = $requestStack;
        $this->key          = $key ?: 'cartalyst_sentinel';
    }

    /**
     * Put a value in the Sentinel session.
     *
     * @param mixed $value
     */
    public function put($value): void
    {
        $this->session()->set($this->key, $value);
    }

    /**
     * Returns the Sentinel session value.
     *
     * @return mixed
     */
    public function get()
    {
        return $this->session()->get($this->key);
    }

    /**
     * Removes the Sentinel session.
     */
    public function forget(): void
    {
        $this->session()->remove($this->key);
    }

    private function session(): SymfonySessionInterface
    {
        return $this->requestStack->getSession();
    }
}
