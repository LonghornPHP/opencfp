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

namespace OpenCFP\Test\Unit\Infrastructure\Auth;

use Ergebnis\Test\Util\Helper;
use Mockery;
use OpenCFP\Infrastructure\Auth\SymfonySentinelSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SymfonySentinelSessionTest extends \PHPUnit\Framework\TestCase
{
    use Helper;
    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /**
     * @test
     */
    public function implementsSessionInterface()
    {
        $this->assertClassImplementsInterface(\Cartalyst\Sentinel\Sessions\SessionInterface::class, SymfonySentinelSession::class);
    }

    /**
     * @test
     */
    public function putSetsValue()
    {
        $key   = 'foo';
        $value = 'bar';

        [$session, $requestStack] = $this->getSessionMock();

        $session
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->identicalTo($key),
                $this->identicalTo($value)
            );

        $sentinelSession = new SymfonySentinelSession(
            $requestStack,
            $key
        );

        $sentinelSession->put($value);
    }

    /**
     * @test
     */
    public function getReturnsValue()
    {
        $key   = 'foo';
        $value = 'bar';

        [$session, $requestStack] = $this->getSessionMock();

        $session
            ->expects($this->once())
            ->method('get')
            ->with($this->identicalTo($key))
            ->willReturn($value);

        $sentinelSession = new SymfonySentinelSession(
            $requestStack,
            $key
        );

        $this->assertSame($value, $sentinelSession->get());
    }

    /**
     * @test
     */
    public function forgetRemovesKey()
    {
        $key = 'foo';

        [$session, $requestStack] = $this->getSessionMock();

        $session
            ->expects($this->once())
            ->method('remove')
            ->with($this->identicalTo($key));

        $sentinelSession = new SymfonySentinelSession(
            $requestStack,
            $key
        );

        $sentinelSession->forget();
    }

    /**
     * @return array{0: SessionInterface, 1: RequestStack}
     */
    private function getSessionMock(): array
    {
        $session = $this->getMockBuilder(SessionInterface::class)->getMock();

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return [$session, $requestStack];
    }
}
