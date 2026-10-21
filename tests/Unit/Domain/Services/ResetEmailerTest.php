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

namespace OpenCFP\Test\Unit\Domain\Services;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpenCFP\Domain\Services\ResetEmailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ResetEmailerTest extends \PHPUnit\Framework\TestCase
{
    use MockeryPHPUnitIntegration;

    /** @test */
    public function it_sends_the_expected_email()
    {
        $userEmail = 'user@example.com';

        $mailer = Mockery::mock(MailerInterface::class)
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (Email $message) use ($userEmail) {
                $to = $message->getTo();

                return $to !== []
                    && $to[0]->getAddress() === $userEmail
                    && $message->getSubject() === 'Password Reset Request';
            }))
            ->getMock();

        $twig = new Environment(new ArrayLoader([
            'emails/reset_password.twig' => <<<'TWIG'
{% block subject %}Password Reset Request{% endblock %}
{% block from %}admin@example.com{% endblock %}
{% block from_name %}Reset{% endblock %}
{% block body_text %}Plain text body{% endblock %}
{% block body_html %}<p>HTML body</p>{% endblock %}
TWIG
            ,
        ]));

        $resetEmailer = new ResetEmailer(
            $mailer,
            $twig,
            'admin@example.com',
            'Reset'
        );

        $response = $resetEmailer->send(
            123,
            $userEmail,
            '987abc'
        );

        $this->assertNotFalse($response);
    }
}
