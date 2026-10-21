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

namespace OpenCFP\Domain\Services;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class ResetEmailer
{
    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var string
     */
    private $configEmail;

    /**
     * @var string
     */
    private $configTitle;

    public function __construct(MailerInterface $mailer, Environment $twig, string $configEmail, string $configTitle)
    {
        $this->mailer      = $mailer;
        $this->twig        = $twig;
        $this->configEmail = $configEmail;
        $this->configTitle = $configTitle;
    }

    /**
     * @param string $userId
     * @param string $email
     * @param string $resetCode
     *
     * @return false|int
     */
    public function send($userId, $email, $resetCode)
    {
        $parameters = $this->parameters($userId, $resetCode);

        try {
            $this->mailer->send($this->preparedMessage($email, $parameters));

            return 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $userId
     * @param string $resetCode
     *
     * @return array
     */
    private function parameters($userId, $resetCode)
    {
        return [
            'reset_code' => $resetCode,
            'method'     => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
                ? 'https' : 'http',
            'host'    => !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost',
            'user_id' => $userId,
            'email'   => $this->configEmail,
            'title'   => $this->configTitle,
        ];
    }

    /**
     * @param string $email
     * @param array  $parameters
     */
    private function preparedMessage($email, $parameters): Email
    {
        $template = $this->twig->load('emails/reset_password.twig')->unwrap();

        $render = function (string $block) use ($template, $parameters): string {
            return $template->renderBlock($block, $this->twig->mergeGlobals($parameters));
        };

        return (new Email())
            ->to($email)
            ->from(new Address($render('from'), $render('from_name')))
            ->subject($render('subject'))
            ->text($render('body_text'))
            ->html($render('body_html'));
    }
}
