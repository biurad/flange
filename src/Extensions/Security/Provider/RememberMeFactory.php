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

namespace Flange\Extensions\Security\Provider;

use Biurad\Security\Authenticator\RememberMeAuthenticator;
use Biurad\Security\Handler\RememberMeHandler;
use Flange\Extensions\Symfony\CacheExtension;
use Flange\Extensions\Symfony\FrameworkExtension;
use Flange\Extensions\Symfony\PropertyAccessExtension;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\RememberMe\CacheTokenVerifier;
use Symfony\Component\Security\Core\Signature\ExpiredSignatureStorage;
use Symfony\Component\Security\Core\Signature\SignatureHasher;

class RememberMeFactory extends AbstractFactory
{
    protected array $options = [
        'name' => 'REMEMBERME',
        'lifetime' => 31536000,
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'samesite' => null,
    ];

    public function getKey(): string
    {
        return 'remember-me';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $builder = $node
            ->fixXmlConfig('user_provider')
            ->beforeNormalization()->ifString()->then(fn ($v) => ['secret' => $v])->end()
            ->children()
        ;

        $builder
            ->scalarNode('secret')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('parameter')->defaultValue('_remember_me')->end()
            ->scalarNode('user_parameter')->defaultValue('_remember_user_id')->end()
            ->booleanNode('allow_multiple_tokens')->defaultFalse()->end()
            ->integerNode('max_uses')->end()
            ->integerNode('signature_lifetime')->defaultValue(600)->end()
            ->arrayNode('signature_properties')
                ->prototype('scalar')->end()
                ->requiresAtLeastOneElement()
                ->info('An array of properties on your User that are used to sign the remember-me cookie. If any of these change, all existing cookies will become invalid.')
                ->example(['email', 'password'])
                ->defaultValue(['password'])
            ->end()
            ->scalarNode('token_provider')->end()
            ->scalarNode('token_verifier')
                ->info('The service ID of a custom rememberme token verifier.')
            ->end();

        foreach ($this->options as $name => $value) {
            if ('secure' === $name) {
                $builder->enumNode($name)->values([true, false, 'auto'])->defaultValue('auto' === $value ? null : $value);
            } elseif ('samesite' === $name) {
                $builder->enumNode($name)->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultValue($value);
            } elseif (\is_bool($value)) {
                $builder->booleanNode($name)->defaultValue($value);
            } elseif (\is_int($value)) {
                $builder->integerNode($name)->defaultValue($value);
            } else {
                $builder->scalarNode($name)->defaultValue($value);
            }
        }
    }

    public function create(Container $container, string $id, array $config): void
    {
        $hasCache = !empty($container->getExtensionConfig(CacheExtension::class, $container->hasExtension(FrameworkExtension::class) ? 'symfony' : null));

        if (isset($config['token_provider'])) {
            $tokenVerifier = isset($config['token_verifier']) ? new Reference($config['token_verifier']) : null;
            $tokenStorage = $container->has($config['token_provider']) ? new Reference($config['token_provider']) : new Statement($config['token_provider']);

            if (null === $tokenVerifier && $hasCache) {
                $tokenVerifier = new Statement(CacheTokenVerifier::class, [new Reference('cache.app')]);
            }
        } elseif ($container->hasExtension(PropertyAccessExtension::class)) {
            if ($hasCache) {
                $expireStorage = new Statement(ExpiredSignatureStorage::class, [new Reference('cache.app'), $config['signature_lifetime']]);
            }
            $container->set('security.authenticator.remember_me_signature_hasher', new Definition(SignatureHasher::class, [new Reference('property_accessor'), $config['signature_properties'], $config['secret'], $expireStorage ?? null, $config['max_uses'] ?? null]));
        }

        $container->autowire('security.authenticator.remember_me_handler', new Definition(RememberMeHandler::class, [
            $config['secret'],
            $tokenStorage ?? null,
            $tokenVerifier ?? null,
            new Reference('?security.authenticator.remember_me_signature_hasher'),
            $config['parameter'],
            \array_intersect_key($config, $this->options),
        ]));
        $container->autowire('security.authenticator.remember_me', new Definition(RememberMeAuthenticator::class, [new Reference('security.authenticator.remember_me_handler'), 2 => $config['allow_multiple_tokens']]));
    }
}
