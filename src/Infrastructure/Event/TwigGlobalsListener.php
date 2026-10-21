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

namespace OpenCFP\Infrastructure\Event;

use OpenCFP\Domain\CallForPapers;
use OpenCFP\Domain\Services\Authentication;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TwigGlobalsListener implements EventSubscriberInterface
{
    /**
     * @var Authentication
     */
    private $authentication;

    /**
     * @var CallForPapers
     */
    private $callForPapers;

    /**
     * @var Twig\Environment
     */
    private $twig;

    public function __construct(
        Authentication $authentication,
        CallForPapers $callForPapers,
        Environment $twig
    ) {
        $this->authentication = $authentication;
        $this->callForPapers  = $callForPapers;
        $this->twig           = $twig;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 120],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $this->twig->addGlobal('current_page', $request->getRequestUri());
        $this->twig->addGlobal('cfp_open', $this->callForPapers->isOpen());

        // Authentication
        if ($this->authentication->isAuthenticated()) {
            $this->twig->addGlobal('user', $this->authentication->user());
            $this->twig->addGlobal('user_is_admin', $this->authentication->user()->hasAccess('admin'));
            $this->twig->addGlobal('user_is_reviewer', $this->authentication->user()->hasAccess('reviewer'));
        }

        // Flash
        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session !== null && $session->has('flash')) {
            $this->twig->addGlobal('flash', $session->get('flash'));
            $session->set('flash', null);
        }
    }
}
