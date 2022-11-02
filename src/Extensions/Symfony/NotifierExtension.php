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

use function Rade\DI\Loader\service;

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
    public function register(Container $container, array $configs = []): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(Notifier::class)) {
            throw new \LogicException('Notifier support cannot be enabled as the component is not installed. Try running "composer require symfony/notifier".');
        }

        $definitions = [
            'notifier.chatter.transport_factory' => service(Transport::class, [new TaggedLocator('chatter.transport_factory')])->public(false),
            'notifier.chatter.transports' => service([new Reference('notifier.chatter.transport_factory'), 'fromStrings'], [$configs['chatter_transports'] ?? []])->public(false),
            'notifier.chatter.messenger.chat_handler' => service(MessageHandler::class, [$ct = new Reference('notifier.chatter.transports')])->public(false)->tag('messenger.message_handler', ['handles' => ChatMessage::class]),
            'notifier.texter.transport_factory' => service(Transport::class, [new TaggedLocator('notifier.texter.transport_factory')])->public(false),
            'notifier.texter.transports' => service([new Reference('notifier.texter.transport_factory'), 'fromStrings'], [$configs['texter_transports'] ?? []])->public(false),
            'notifier.texter.messenger.sms_handler' => service(MessageHandler::class, [$tt = new Reference('notifier.texter.transports')])->public(false)->tag('messenger.message_handler', ['handles' => SmsMessage::class]),
            'notifier.texter.messenger.push_handler' => service(MessageHandler::class, [$tt])->public(false)->tag('messenger.message_handler', ['handles' => PushMessage::class]),
            'notifier.channel.browser' => service(BrowserChannel::class)->public(false)->tag('notifier.channel', ['channel' => 'browser']),
            'notifier.channel.chat' => $cc = service(ChatChannel::class, [$ct, $ms = new Reference('?messenger.default_bus')])->public(false)->tag('notifier.channel', ['channel' => 'chat']),
            'notifier.channel.sms' => $sc = service(SmsChannel::class, [$tt, $ms])->public(false)->tag('notifier.channel', ['channel' => 'sms']),
            'notifier.channel.push' => $pc = service(PushChannel::class, [$tt, $ms])->public(false)->tag('notifier.channel', ['channel' => 'push']),
            'notifier' => $notifier = service(Notifier::class, [new TaggedLocator('notifier.channel', 'channel'), new Statement(ChannelPolicy::class, [$configs['channel_policy']])])->typed(),
        ];

        if (isset($configs['admin_recipients'])) {
            foreach ($configs['admin_recipients'] as $recipient) {
                $notifier->bind('addAdminRecipient', [new Statement(Recipient::class, [$recipient['email'], $recipient['phone']])]);
            }
        }

        if ($configs['texter_transports']) {
            $definitions['notifier.texter'] = service(Texter::class, [$tt, $ms])->typed();
        }

        if ($configs['chatter_transports']) {
            $definitions['notifier.chatter'] = service(Chatter::class, [$ct, $ms])->typed();
        }

        if ($container->hasExtension(EventDispatcherExtension::class)) {
            if ($container->hasExtension(MailerExtension::class)) {
                $sender = $container->definition('mailer.envelope_listener')->getArgument(0);
                $definitions['notifier.channel.email'] = $ec = service(EmailChannel::class, [new Reference('mailer.transports'), $ms, $sender])->public(false)->tag('notifier.channel', ['channel' => 'email']);
            }

            $definitions['notifier.listener.logger_notification'] = service(NotificationLoggerListener::class)->public(false)->tag('event_subscriber');
        }

        if ($container->hasExtension(MessengerExtension::class)) {
            $definitions['notifier.listener.failed_message'] = service(SendFailedMessageToNotifierListener::class, [new Reference('notifier')])->public(false)->tag('event_subscriber');

            // as we have a bus, the channels don't need the transports
            $cc->args([null, $ms]);
            $sc->args([null, $ms]);
            $pc->args([null, $ms]);

            if (isset($ec)) {
                $ec->args([null, $ms]);
            }
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

            $container->tag($class, $classTag);
        }

        $container->multiple($definitions);
    }
}
