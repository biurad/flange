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
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransportFactory;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Bridge\Expo\ExpoTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Bridge\FortySixElks\FortySixElksTransportFactory;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Bridge\Gitter\GitterTransportFactory;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Bridge\Iqsms\IqsmsTransportFactory;
use Symfony\Component\Notifier\Bridge\KazInfoTeh\KazInfoTehTransportFactory;
use Symfony\Component\Notifier\Bridge\LightSms\LightSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Bridge\Mailjet\MailjetTransportFactory as MailjetNotifierTransportFactory;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdTransport;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaTransportFactory;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalTransportFactory;
use Symfony\Component\Notifier\Bridge\OrangeSms\OrangeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory as SendinblueNotifierTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Sms77\Sms77TransportFactory;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransportFactory;
use Symfony\Component\Notifier\Bridge\Smsc\SmscTransportFactory;
use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Telnyx\TelnyxTransportFactory;
use Symfony\Component\Notifier\Bridge\TurboSms\TurboSmsTransport;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Bridge\Vonage\VonageTransportFactory;
use Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransportFactory;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransportFactory;
use Symfony\Component\Notifier\Channel\BrowserChannel;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Channel\ChatChannel;
use Symfony\Component\Notifier\Channel\EmailChannel;
use Symfony\Component\Notifier\Channel\PushChannel;
use Symfony\Component\Notifier\Channel\SmsChannel;
use Symfony\Component\Notifier\Chatter;
use Symfony\Component\Notifier\EventListener\NotificationLoggerListener;
use Symfony\Component\Notifier\EventListener\SendFailedMessageToNotifierListener;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Messenger\MessageHandler;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Texter;
use Symfony\Component\Notifier\Transport;
use Symfony\Component\Notifier\Transport\NullTransportFactory;

/**
 * Symfony Notifier Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class NotifierExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'notifier';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode('notifier')
            ->info('Notifier configuration')
            ->canBeEnabled()
            ->fixXmlConfig('chatter_transport')
            ->children()
                ->arrayNode('chatter_transports')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->fixXmlConfig('texter_transport')
            ->children()
                ->arrayNode('texter_transports')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->children()
                ->booleanNode('notification_on_failed_messages')->defaultFalse()->end()
            ->end()
            ->children()
                ->arrayNode('channel_policy')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()->ifString()->then(fn (string $v) => [$v])->end()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end()
            ->fixXmlConfig('admin_recipient')
            ->children()
                ->arrayNode('admin_recipients')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('email')->cannotBeEmpty()->end()
                            ->scalarNode('phone')->defaultValue('')->end()
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

        if (!class_exists(Notifier::class)) {
            throw new \LogicException('Notifier support cannot be enabled as the component is not installed. Try running "composer require symfony/notifier".');
        }

        $container->set('chatter.transport_factory', new Definition(Transport::class, [new TaggedLocator('chatter.transport_factory')]));
        $container->set('chatter.transports', new Definition([new Reference('chatter.transport_factory'), 'fromStrings'], [$configs['chatter_transports'] ?? []]))->public(false);
        $container->set('chatter.messenger.chat_handler', new Definition(MessageHandler::class, [$cts = new Reference('chatter.transports')]))->tag('messenger.message_handler', ['handles' => ChatMessage::class])->public(false);
        $container->set('texter.transport_factory', new Definition(Transport::class, [new TaggedLocator('texter.transport_factory')]));
        $container->set('texter.transports', new Definition([new Reference('texter.transport_factory'), 'fromStrings'], [$configs['texter_transports'] ?? []]))->public(false);
        $container->set('texter.messenger.sms_handler', new Definition(MessageHandler::class, [$tt = new Reference('texter.transports')]))->tag('messenger.message_handler', ['handles' => SmsMessage::class])->public(false);
        $container->set('texter.messenger.push_handler', new Definition(MessageHandler::class, [$tt]))->tag('messenger.message_handler', ['handles' => PushMessage::class])->public(false);
        $container->set('notifier.channel.browser', new Definition(BrowserChannel::class))->public(false)->tag('notifier.channel', ['channel' => 'browser']);
        $container->set('notifier.channel.chat', new Definition(ChatChannel::class, [$cts, $mds = new Reference('?messenger.default_bus')]))->public(false)->tag('notifier.channel', ['channel' => 'chat']);
        $container->set('notifier.channel.sms', new Definition(SmsChannel::class, [$tt, $mds]))->public(false)->tag('notifier.channel', ['channel' => 'sms']);
        $container->set('notifier.channel.push', new Definition(PushChannel::class, [$tt, $mds]))->public(false)->tag('notifier.channel', ['channel' => 'push']);
        $notifier = $container->autowire('notifier', new Definition(Notifier::class, [new TaggedLocator('notifier.channel', 'channel'), new Statement(ChannelPolicy::class, [$configs['channel_policy']])]));

        if (isset($configs['admin_recipients'])) {
            foreach ($configs['admin_recipients'] as $recipient) {
                $notifier->bind('addAdminRecipient', [new Statement(Recipient::class, [$recipient['email'], $recipient['phone']])]);
            }
        }

        if ($configs['texter_transports']) {
            $container->set('texter', new Definition(Texter::class, [$cts, $mds]))->autowire();
        }

        if ($configs['chatter_transports']) {
            $container->set('chatter', new Definition(Chatter::class, [$cts, $mds]))->autowire();
        }

        if ($container->hasExtension(EventDispatcherExtension::class)) {
            if ($container->hasExtension(MailerExtension::class)) {
                $sender = $container->definition('mailer.envelope_listener')->getArguments()[0] ?? null;
                $container->set('notifier.channel.email', new Definition(EmailChannel::class, [new Reference('mailer.transports'), $mds, $sender]))->public(false)->tag('notifier.channel', ['channel' => 'email']);
            }

            if ($container->hasExtension(MessengerExtension::class)) {
                $container->set('notifier.failed_message_listener', new Definition(SendFailedMessageToNotifierListener::class, [new Reference('notifier')]))->public(false)->tag('event_subscriber');

                // as we have a bus, the channels don't need the transports
                $container->definition('notifier.channel.chat')->arg(0, null);

                if ($container->has('notifier.channel.email')) {
                    $container->definition('notifier.channel.email')->arg(0, null);
                }
                $container->definition('notifier.channel.sms')->arg(0, null);
                $container->definition('notifier.channel.push')->arg(0, null);
            }

            $container->set('notifier.logger_notification_listener', new Definition(NotificationLoggerListener::class))->public(false)->tag('event_subscriber');
        }

        $classToServices = [
            AllMySmsTransportFactory::class => 'texter.transport_factory',
            AmazonSnsTransportFactory::class => ['texter.transport_factory', 'chatter.transport_factory'],
            ClickatellTransportFactory::class => 'texter.transport_factory',
            DiscordTransportFactory::class => 'chatter.transport_factory',
            EsendexTransportFactory::class => 'texter.transport_factory',
            ExpoTransportFactory::class => 'texter.transport_factory',
            FakeChatTransportFactory::class => 'chatter.transport_factory',
            FakeSmsTransportFactory::class => 'texter.transport_factory',
            FirebaseTransportFactory::class => 'chatter.transport_factory',
            FortySixElksTransportFactory::class => 'texter.transport_factory',
            FreeMobileTransportFactory::class => 'texter.transport_factory',
            GatewayApiTransportFactory::class => 'texter.transport_factory',
            GitterTransportFactory::class => 'chatter.transport_factory',
            GoogleChatTransportFactory::class => 'chatter.transport_factory',
            InfobipTransportFactory::class => 'texter.transport_factory',
            IqsmsTransportFactory::class => 'texter.transport_factory',
            KazInfoTehTransportFactory::class => 'texter.transport_factory',
            LightSmsTransportFactory::class => 'texter.transport_factory',
            LinkedInTransportFactory::class => 'chatter.transport_factory',
            MailjetNotifierTransportFactory::class => 'texter.transport_factory',
            MattermostTransportFactory::class => 'chatter.transport_factory',
            MercureTransportFactory::class => 'chatter.transport_factory',
            MessageBirdTransport::class => 'texter.transport_factory',
            MessageMediaTransportFactory::class => 'texter.transport_factory',
            MicrosoftTeamsTransportFactory::class => 'chatter.transport_factory',
            MobytTransportFactory::class => 'texter.transport_factory',
            NullTransportFactory::class => ['texter.transport_factory', 'chatter.transport_factory'],
            OctopushTransportFactory::class => 'texter.transport_factory',
            OneSignalTransportFactory::class => 'texter.transport_factory',
            OrangeSmsTransportFactory::class => 'texter.transport_factory',
            OvhCloudTransportFactory::class => 'texter.transport_factory',
            RocketChatTransportFactory::class => 'chatter.transport_factory',
            SendinblueNotifierTransportFactory::class => 'texter.transport_factory',
            SinchTransportFactory::class => 'texter.transport_factory',
            SlackTransportFactory::class => 'chatter.transport_factory',
            Sms77TransportFactory::class => 'texter.transport_factory',
            SmsapiTransportFactory::class => 'texter.transport_factory',
            SmsBiurasTransportFactory::class => 'texter.transport_factory',
            SmscTransportFactory::class => 'texter.transport_factory',
            SpotHitTransportFactory::class => 'texter.transport_factory',
            TelegramTransportFactory::class => 'chatter.transport_factory',
            TelnyxTransportFactory::class => 'texter.transport_factory',
            TurboSmsTransport::class => 'texter.transport_factory',
            TwilioTransportFactory::class => 'texter.transport_factory',
            VonageTransportFactory::class => 'texter.transport_factory',
            YunpianTransportFactory::class => 'texter.transport_factory',
            ZulipTransportFactory::class => 'chatter.transport_factory',
        ];

        foreach ($classToServices as $class => $classTag) {
            if (!\class_exists($class)) {
                continue;
            }
            $classTags = \is_array($classTag) ? $classTag : [$classTag];

            foreach ($classTags as $tag) {
                $container->tag($class, $tag);
            }
        }
    }
}
