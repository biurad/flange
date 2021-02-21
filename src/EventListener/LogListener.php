<?php

declare(strict_types=1);

/*
 * This file is part of DivineNii opensource projects.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 DivineNii (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rade\EventListener;

use Biurad\Http\Response\RedirectResponse;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Rade\Event\ExceptionEvent;
use Rade\Event\RequestEvent;
use Rade\Event\ResponseEvent;
use Rade\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs request, response, and exceptions.
 */
class LogListener implements EventSubscriberInterface
{
    protected LoggerInterface $logger;

    /** @var null|callable */
    protected $exceptionLogFilter;

    public function __construct(LoggerInterface $logger, $exceptionLogFilter = null)
    {
        $this->logger = $logger;

        if (null === $exceptionLogFilter) {
            $exceptionLogFilter = function (\Throwable $e) {
                if ($e instanceof BadResponseException && $e->getResponse()->getStatusCode() < 500) {
                    return LogLevel::ERROR;
                }

                return LogLevel::CRITICAL;
            };
        }

        $this->exceptionLogFilter = $exceptionLogFilter;
    }

    /**
     * Logs master requests on event Events::REQUEST
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->logRequest($event->getRequest());
    }

    /**
     * Logs master response on event Events::RESPONSE.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $this->logResponse($event->getResponse());
    }

    /**
     * Logs uncaught exceptions on event Events::EXCEPTION.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $this->logException($event->getThrowable());
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::REQUEST  => ['onKernelRequest', 0],
            Events::RESPONSE => ['onKernelResponse', 0],
            /*
             * Priority -4 is used to come after those from SecurityServiceProvider (0)
             * but before the error handlers added with Rade\Application::error (defaults to -8)
             */
            Events::EXCEPTION => ['onKernelException', -4],
        ];
    }

    /**
     * Logs a request.
     */
    protected function logRequest(ServerRequestInterface $request): void
    {
        $this->logger->log(LogLevel::DEBUG, '> REQUEST: ' . $request->getMethod() . ' ' . (string) $request->getUri());
    }

    /**
     * Logs a response.
     *
     * @param Response $response
     */
    protected function logResponse(ResponseInterface $response): void
    {
        $message = '< RESPONSE: ' . $response->getStatusCode();

        if ($response instanceof RedirectResponse) {
            $message .= ' ' . $response->getHeaderLine('Location');
        }

        $this->logger->log(LogLevel::DEBUG, $message);
    }

    /**
     * Logs an exception.
     */
    protected function logException(\Throwable $e): void
    {
        $this->logger->log(
            ($this->exceptionLogFilter)($e),
            \sprintf('%s: %s (uncaught exception) at %s line %s', \get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
            ['code' => $e->getCode()]
        );
    }
}
