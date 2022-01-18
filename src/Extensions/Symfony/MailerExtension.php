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

namespace Rade\DI\Extensions\Symfony;

use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\AliasedInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueTransportFactory;
use Symfony\Component\Mailer\EventListener\EnvelopeListener;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Header\Headers;

/**
 * Symfony component mailer extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class MailerExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'mailer';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('Mailer configuration')
            ->canBeEnabled()
            ->validate()
                ->ifTrue(function ($v) {
                    return isset($v['dsn']) && \count($v['transports']);
                })
                ->thenInvalid('"dsn" and "transports" cannot be used together.')
            ->end()
            ->fixXmlConfig('transport')
            ->fixXmlConfig('header')
            ->children()
                ->scalarNode('message_bus')->defaultNull()->info('The message bus to use. Defaults to the default bus if the Messenger component is installed.')->end()
                ->scalarNode('dsn')->defaultNull()->end()
                ->arrayNode('transports')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('envelope')
                    ->info('Mailer Envelope configuration')
                    ->children()
                        ->scalarNode('sender')->end()
                        ->arrayNode('recipients')
                            ->performNoDeepMerging()
                            ->beforeNormalization()
                            ->ifArray()
                                ->then(function ($v) {
                                    return \array_filter(\array_values($v));
                                })
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->normalizeKeys(false)
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                return !\is_array($v) || \array_keys($v) !== ['value'];
                            })
                            ->then(function ($v) {
                                return ['value' => $v];
                            })
                        ->end()
                        ->children()
                            ->variableNode('value')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(Mailer::class)) {
            throw new \LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
        }

        if (!\count($configs['transports']) && null === $configs['dsn']) {
            $configs['dsn'] = 'smtp://null';
        }

        $transports = $configs['dsn'] ? ['main' => $configs['dsn']] : $configs['transports'];
        $mailer = $container->set('mailer.mailer', new Definition(Mailer::class, [new Reference('mailer.transports')]))->autowire([MailerInterface::class]);
        $container->set('mailer.transport_factory', new Definition(Transport::class));
        $container->set('mailer.transports', new Definition([new Reference('mailer.transport_factory'), 'fromStrings'], [$transports]));
        $container->set('mailer.default_transport', new Definition([new Reference('mailer.transport_factory'), 'fromString'], [\current($transports)]));
        $container->set('mailer.messenger.message_handler', new Definition(MessageHandler::class, [new Reference('mailer.transports')]))->tag('messenger.message_handler');
        $container->alias('mailer', 'mailer.mailer');

        if (false === $messageBus = $configs['message_bus']) {
            $mailer->arg(1, null);
        } elseif ($messageBus) {
            $mailer->arg(1, new Reference($messageBus));
        }

        $classToServices = [
            GmailTransportFactory::class,
            MailgunTransportFactory::class,
            MailjetTransportFactory::class,
            MandrillTransportFactory::class,
            PostmarkTransportFactory::class,
            SendgridTransportFactory::class,
            SendinblueTransportFactory::class,
            SesTransportFactory::class,
            OhMySmtpTransportFactory::class,
        ];

        foreach ($classToServices as $class) {
            if (\class_exists($class)) {
                $container->tag($class, 'mailer.transport_factory');
            }
        }

        if (\class_exists(EventDispatcher::class)) {
            $container->set('mailer.envelope_listener', new Definition(EnvelopeListener::class, [$configs['envelope']['sender'] ?? null, $configs['envelope']['recipients'] ?? null]))->tag('event_subscriber')->public(false);
            $container->set('mailer.message_logger_listener', new Definition(MessageLoggerListener::class))->tag('event_subscriber')->public(false);

            if ($configs['headers']) {
                $headers = new Definition(Headers::class);

                foreach ($configs['headers'] as $name => $data) {
                    $value = $data['value'];

                    if (\in_array(\strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to'])) {
                        $value = (array) $value;
                    }
                    $headers->bind('addHeader', [$name, $value]);
                }

                $container->set(Headers::class, $headers)->public(false);
                $container->set('mailer.message_listener', new Definition(MessageListener::class, [new Reference(Headers::class)]))->tag('event_subscriber')->public(false);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $container->definition('mailer.transport_factory')->arg(0, $container->findBy(
            'mailer.transport_factory',
            fn ($id) => $container->has($id) ? new Reference($id) : new Statement($id)
        ));
    }
}
