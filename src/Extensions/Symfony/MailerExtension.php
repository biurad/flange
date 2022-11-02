<?php declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Extensions\Symfony;

use Flange\Extensions\EventDispatcherExtension;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
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
use Symfony\Component\Mailer\Transport\NativeTransportFactory;
use Symfony\Component\Mailer\Transport\NullTransportFactory;
use Symfony\Component\Mailer\Transport\SendmailTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mime\Header\Headers;

use function Rade\DI\Loader\service;

/**
 * Symfony component mailer extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class MailerExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
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
                ->ifTrue(fn ($v) => isset($v['dsn']) && \count($v['transports']))
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
                                ->then(fn ($v) => \array_filter(\array_values($v)))
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->beforeNormalization()
                            ->ifTrue(fn ($v) => !\is_array($v) || \array_keys($v) !== ['value'])
                            ->then(fn ($v) => ['value' => $v])
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
    public function register(Container $container, array $configs = []): void
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
        $definitions = [
            'mailer' => $mailer = service(Mailer::class, [new Reference('mailer.transports')])->typed(Mailer::class, MailerInterface::class),
            'mailer.transports' => service([new Reference('mailer.transport_factory'), 'fromStrings'], [$transports]),
            'mailer.transport_factory' => service(Transport::class, [new TaggedLocator('mailer.transport_factory')])->public(false),
            'mailer.default_transport' => service([new Reference('mailer.transport_factory'), 'fromString'], [\current($transports)]),
            'mailer.messenger.message_handler' => service(MessageHandler::class, [new Reference('mailer.transports')])->public(false)->tag('messenger.message_handler'),
        ];

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
            NullTransportFactory::class,
            NativeTransportFactory::class,
            SendmailTransportFactory::class,
            EsmtpTransportFactory::class,
        ];

        foreach ($classToServices as $class) {
            if (\class_exists($class)) {
                $container->tag($class, 'mailer.transport_factory');
            }
        }

        if ($container->hasExtension(EventDispatcherExtension::class)) {
            $definitions += [
                'mailer.envelope_listener' => service(EnvelopeListener::class, [$configs['envelope']['sender'] ?? null, $configs['envelope']['recipients'] ?? null])->public(false)->tag('event_subscriber'),
                'mailer.message_logger_listener' => service(MessageLoggerListener::class)->public(false)->tag('event_subscriber'),
            ];

            if ($configs['headers']) {
                $headers = new Definition(Headers::class);

                foreach ($configs['headers'] as $name => $data) {
                    $value = $data['value'];

                    if (\in_array(\strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to'], true)) {
                        $value = (array) $value;
                    }
                    $headers->bind('addHeader', [$name, $value]);
                }

                $definitions += [
                    'mailer.headers' => $headers,
                    'mailer.message_listener' => service(MessageListener::class, [new Reference('mailer.headers')])->public(false)->tag('event_subscriber'),
                ];
            }
        }

        $container->multiple($definitions);
    }
}
