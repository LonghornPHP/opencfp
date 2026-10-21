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

namespace OpenCFP\Http\Action\Talk;

use HTMLPurifier;
use OpenCFP\Domain\CallForPapers;
use OpenCFP\Domain\Model;
use OpenCFP\Domain\Services;
use OpenCFP\Http\Form;
use OpenCFP\Http\View;
use Symfony\Component\HttpFoundation;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing;
use Twig\Environment;

final class CreateProcessAction
{
    /**
     * @var Services\Authentication
     */
    private $authentication;

    /**
     * @var View\TalkHelper
     */
    private $talkHelper;

    /**
     * @var CallForPapers
     */
    private $callForPapers;

    /**
     * @var HTMLPurifier
     */
    private $purifier;

    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @var string
     */
    private $applicationEmail;

    /**
     * @var string
     */
    private $applicationTitle;

    /**
     * @var string
     */
    private $applicationEndDate;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var Routing\Generator\UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(
        Services\Authentication $authentication,
        View\TalkHelper $talkHelper,
        CallForPapers $callForPapers,
        HTMLPurifier $purifier,
        MailerInterface $mailer,
        string $applicationEmail,
        string $applicationTitle,
        string $applicationEndDate,
        Environment $twig,
        Routing\Generator\UrlGeneratorInterface $urlGenerator
    ) {
        $this->authentication     = $authentication;
        $this->talkHelper         = $talkHelper;
        $this->callForPapers      = $callForPapers;
        $this->purifier           = $purifier;
        $this->mailer             = $mailer;
        $this->applicationEmail   = $applicationEmail;
        $this->applicationTitle   = $applicationTitle;
        $this->applicationEndDate = $applicationEndDate;
        $this->twig               = $twig;
        $this->urlGenerator       = $urlGenerator;
    }

    public function __invoke(HttpFoundation\Request $request): HttpFoundation\Response
    {
        if (!$this->callForPapers->isOpen()) {
            $request->getSession()->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'You cannot create talks once the call for papers has ended',
            ]);

            $url = $this->urlGenerator->generate('dashboard');

            return new HttpFoundation\RedirectResponse($url);
        }

        $user = $this->authentication->user();

        $form = $this->createTalkForm([
            'title'       => $request->request->get('title'),
            'description' => $request->request->get('description'),
            'type'        => $request->request->get('type'),
            'level'       => $request->request->get('level'),
            'category'    => $request->request->get('category'),
            'desired'     => $request->request->get('desired'),
            'slides'      => $request->request->get('slides'),
            'other'       => $request->request->get('other'),
            'sponsor'     => $request->request->get('sponsor'),
            'user_id'     => $request->request->get('user_id'),
        ]);

        $form->sanitize();

        if (!$form->validateAll()) {
            $request->getSession()->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => \implode('<br>', $form->getErrorMessages()),
            ]);

            $content = $this->twig->render('talk/create.twig', [
                'formAction'     => $this->urlGenerator->generate('talk_create'),
                'talkCategories' => $this->talkHelper->getTalkCategories(),
                'talkTypes'      => $this->talkHelper->getTalkTypes(),
                'talkLevels'     => $this->talkHelper->getTalkLevels(),
                'title'          => $request->request->get('title'),
                'description'    => $request->request->get('description'),
                'type'           => $request->request->get('type'),
                'level'          => $request->request->get('level'),
                'category'       => $request->request->get('category'),
                'desired'        => $request->request->get('desired'),
                'slides'         => $request->request->get('slides'),
                'other'          => $request->request->get('other'),
                'sponsor'        => $request->request->get('sponsor'),
                'buttonInfo'     => 'Submit my talk!',
                'flash'          => $request->getSession()->get('flash'),
            ]);

            return new HttpFoundation\Response($content);
        }

        $talk = Model\Talk::create(\array_merge($form->getCleanData(), [
            'user_id' => $user->getId(),
        ]));

        try {
            $this->sendSubmitEmail(
                $user->getLogin(),
                (int) $talk->id
            );
            $request->getSession()->set('flash', [
                'type'  => 'success',
                'short' => 'Success',
                'ext'   => 'Successfully saved talk.',
            ]);
        } catch (TransportExceptionInterface $e) {
            $request->getSession()->set('flash', [
                'type'  => 'error',
                'short' => 'Error',
                'ext'   => 'Your talk was saved but we could not send a confirmation email',
            ]);
        }

        $url = $this->urlGenerator->generate('dashboard');

        return new HttpFoundation\RedirectResponse($url);
    }

    private function createTalkForm(array $data): Form\TalkForm
    {
        return new Form\TalkForm(
            $data,
            $this->purifier,
            [
                'categories' => $this->talkHelper->getTalkCategories(),
                'levels'     => $this->talkHelper->getTalkLevels(),
                'types'      => $this->talkHelper->getTalkTypes(),
            ]
        );
    }

    private function sendSubmitEmail(string $email, int $talkId): void
    {
        $talk = Model\Talk::find($talkId, ['title']);

        $parameters = [
            'email'   => $this->applicationEmail,
            'title'   => $this->applicationTitle,
            'talk'    => $talk->title,
            'enddate' => $this->applicationEndDate,
        ];

        $template = $this->twig->load('emails/talk_submit.twig')->unwrap();

        $render = function (string $block) use ($template, $parameters): string {
            return $template->renderBlock($block, $this->twig->mergeGlobals($parameters));
        };

        $message = (new Email())
            ->to($email)
            ->from(new Address($render('from'), $render('from_name')))
            ->subject($render('subject'))
            ->text($render('body_text'))
            ->html($render('body_html'));

        $this->mailer->send($message);
    }
}
