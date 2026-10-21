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

namespace OpenCFP\Test\Integration;

use Illuminate\Database\Capsule;
use Illuminate\Database\Connection;
use Ergebnis\Test\Util\Helper;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpenCFP\Domain\CallForPapers;
use OpenCFP\Domain\Model\User;
use OpenCFP\Domain\Services\Authentication;
use OpenCFP\Domain\Services\IdentityProvider;
use OpenCFP\Environment;
use OpenCFP\Infrastructure\Auth\UserInterface;
use OpenCFP\Kernel;
use OpenCFP\Test\Helper\MockAuthentication;
use OpenCFP\Test\Helper\MockIdentityProvider;
use OpenCFP\Test\Helper\ResponseHelper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

abstract class WebTestCase extends KernelTestCase
{
    use Helper;
    use MockeryPHPUnitIntegration;
    use ResponseHelper;

    /**
     * Additional headers for a request.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Additional server variables to be sent with a request.
     *
     * @var array
     */
    protected $server = [];

    /**
     * @var null|KernelBrowser
     */
    private $client;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        $this->refreshContainer();

        // Mirrors HtmlPurifierWarmer, which only runs on an explicit cache:warmup.
        (new Filesystem())->mkdir(self::$kernel->getCacheDir() . '/htmlpurifier', 0755);

        if ($this instanceof TransactionalTestCase) {
            $this->databaseConnection()->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this instanceof TransactionalTestCase) {
            $this->databaseConnection()->rollBack();
        }

        parent::tearDown();
    }

    final protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function refreshContainer(): void
    {
        self::bootKernel(['environment' => Environment::TYPE_TESTING, 'debug' => true]);
    }

    private function databaseConnection(): Connection
    {
        /** @var Capsule\Manager $capsule */
        $capsule = self::getContainer()->get(Capsule\Manager::class);

        return $capsule->getConnection();
    }

    final protected function createClient(): KernelBrowser
    {
        if ($this->client === null) {
            $this->client = new KernelBrowser(self::$kernel);
        }

        return $this->client;
    }

    final protected function call(string $method, string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], string $content = null): Response
    {
        $client = $this->createClient();

        foreach ($cookies as $key => $value) {
            $client->getCookieJar()->set(new Cookie($key, $value));
        }

        $client->request($method, $uri, $parameters, $files, $server, $content);

        return $client->getResponse();
    }

    final protected function get(string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], string $content = null): Response
    {
        return $this->call('GET', $uri, $parameters, $cookies, $files, $server, $content);
    }

    final protected function post(string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], string $content = null): Response
    {
        return $this->call('POST', $uri, $parameters, $cookies, $files, $server, $content);
    }

    final protected function patch(string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], string $content = null): Response
    {
        return $this->call('PATCH', $uri, $parameters, $cookies, $files, $server, $content);
    }

    final protected function delete(string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], string $content = null): Response
    {
        return $this->call('DELETE', $uri, $parameters, $cookies, $files, $server, $content);
    }

    final protected function callForPapersIsOpen(): self
    {
        $cfp    = self::getContainer()->get(CallForPapers::class);
        $method = new \ReflectionMethod(CallForPapers::class, 'setEndDate');
        $method->setAccessible(true);
        $method->invoke($cfp, new \DateTimeImmutable('+1 week'));

        self::getContainer()->get('twig')->addGlobal('cfp_open', $cfp->isOpen());

        return $this;
    }

    final protected function callForPapersIsClosed(): self
    {
        $cfp    = self::getContainer()->get(CallForPapers::class);
        $method = new \ReflectionMethod(CallForPapers::class, 'setEndDate');
        $method->setAccessible(true);
        $method->invoke($cfp, new \DateTimeImmutable('-1 week'));

        self::getContainer()->get('twig')->addGlobal('cfp_open', $cfp->isOpen());

        return $this;
    }

    final protected function isOnlineConference(): self
    {
        $config                      = self::getContainer()->getParameter('config.application');
        $config['online_conference'] = true;
        self::getContainer()->get('twig')->addGlobal('site', $config);

        return $this;
    }

    final protected function asLoggedInSpeaker(int $id): self
    {
        $user = Mockery::mock(UserInterface::class);
        $user->shouldReceive('id')->andReturn($id);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('hasAccess')->with('admin')->andReturn(false);
        $user->shouldReceive('hasAccess')->with('reviewer')->andReturn(false);
        $user->shouldReceive('getLogin')->andReturn('my@email.com');

        /** @var MockAuthentication $authentication */
        $authentication = self::getContainer()->get(Authentication::class);
        $authentication->overrideUser($user);

        /** @var MockIdentityProvider $identityProvider */
        $identityProvider = self::getContainer()->get(IdentityProvider::class);
        $identityProvider->overrideCurrentUser(new User(['id' => $id]));

        return $this;
    }

    final protected function asAdmin(int $id): self
    {
        $user = Mockery::mock(UserInterface::class);
        $user->shouldReceive('id')->andReturn($id);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('hasAccess')->with('admin')->andReturn(true);
        $user->shouldReceive('hasAccess')->with('reviewer')->andReturn(false);

        /** @var MockAuthentication $authentication */
        $authentication = self::getContainer()->get(Authentication::class);
        $authentication->overrideUser($user);

        /** @var MockIdentityProvider $identityProvider */
        $identityProvider = self::getContainer()->get(IdentityProvider::class);
        $identityProvider->overrideCurrentUser(new User(['id' => $id]));

        return $this;
    }

    final protected function asReviewer(int $id): self
    {
        $user = Mockery::mock(UserInterface::class);
        $user->shouldReceive('id')->andReturn($id);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('hasAccess')->with('admin')->andReturn(false);
        $user->shouldReceive('hasAccess')->with('reviewer')->andReturn(true);

        /** @var MockAuthentication $authentication */
        $authentication = self::getContainer()->get(Authentication::class);
        $authentication->overrideUser($user);

        /** @var MockIdentityProvider $identityProvider */
        $identityProvider = self::getContainer()->get(IdentityProvider::class);
        $identityProvider->overrideCurrentUser(new User(['id' => $id]));

        return $this;
    }

    final protected function session(): Session\SessionInterface
    {
        return $this->createClient()->getRequest()->getSession();
    }

    /**
     * Generates a CSRF token bound to the session used by the next request.
     *
     * Symfony 6 reads the token storage from the current request's session, so
     * a request with a started session has to be available while the token is
     * generated, and the client has to reuse that same session.
     */
    final protected function csrfToken(string $tokenId): string
    {
        $client = $this->createClient();

        /** @var Session\SessionInterface $session */
        $session = self::getContainer()->get('session.factory')->createSession();
        $session->start();

        $request = new Request();
        $request->setSession($session);

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            $token = self::getContainer()->get('security.csrf.token_manager')
                ->getToken($tokenId)
                ->getValue();
        } finally {
            $requestStack->pop();
        }

        $session->save();

        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        return $token;
    }

    final protected function withFakeSwiftMailer(): self
    {
        $fakeMailer = Mockery::mock(MailerInterface::class);
        $fakeMailer->shouldReceive('send')->andThrow(new TransportException('Test transport failure'));

        self::getContainer()->set('mailer.mailer', $fakeMailer);

        return $this;
    }
}
