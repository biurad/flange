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

namespace Flange\Extensions\Security;

use Biurad\Security\AccessMap;
use Biurad\Security\Authenticator;
use Biurad\Security\Commands\UserStatusCommand;
use Biurad\Security\Handler\FirewallAccessHandler;
use Biurad\Security\Handler\LogoutHandler;
use Biurad\Security\RateLimiter\AbstractRequestRateLimiter;
use Biurad\Security\RateLimiter\DefaultLoginRateLimiter;
use Biurad\Security\Token\CacheableTokenStorage;
use Flange\Extensions\Symfony;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\PasswordHasher\Command\UserPasswordHashCommand;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\Pbkdf2PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\PriorityStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\ChainUserProvider;
use Symfony\Component\Security\Core\User\MissingUserProvider;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;

/**
 * Biurad Security Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SecurityExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /** @var array<int,Provider\ProviderFactoryInterface> */
    private array $factoryProviders = [], $userProviders = [];

    /**
     * @param array<int,Provider\ProviderFactoryInterface> $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->addProviderFactory($provider);
        }
    }

    /**
     * Add a provider factory.
     */
    public function addProviderFactory(Provider\ProviderFactoryInterface $factory): void
    {
        if ($factory instanceof Provider\AbstractFactory) {
            $this->factoryProviders[] = $factory;
        } else {
            $this->userProviders[] = $factory;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'security';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->fixXmlConfig('role', 'role_hierarchy')
            ->fixXmlConfig('password_hasher')
            ->fixXmlConfig('rule', 'access_control')
            ->children()
                ->booleanNode('hide_user_not_found')->defaultTrue()->end()
                ->booleanNode('include_authenticators')->defaultTrue()->end()
                ->booleanNode('erase_credentials')->defaultTrue()->end()
                ->scalarNode('firewall_name')->defaultValue('main')->end()
                ->enumNode('token_storage')->values(['session', 'cache', null])->defaultValue('session')->end()
                ->ScalarNode('token_storage_expiry')->defaultValue('+3 hours')->end()
                ->scalarNode('user_checker')
                    ->defaultValue('security.user_checker')
                    ->treatNullLike('security.user_checker')
                    ->info('The UserChecker to use when authenticating users in this firewall.')
                ->end()
                ->arrayNode('access_decision_manager')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('strategy')
                            ->values(['affirmative', 'consensus', 'unanimous', 'priority'])
                        ->end()
                        ->scalarNode('service')->end()
                        ->scalarNode('strategy_service')->end()
                        ->booleanNode('allow_if_all_abstain')->defaultFalse()->end()
                        ->booleanNode('allow_if_equal_granted_denied')->defaultTrue()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['strategy'], $v['service']))
                        ->thenInvalid('"strategy" and "service" cannot be used together.')
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['strategy'], $v['strategy_service']))
                        ->thenInvalid('"strategy" and "strategy_service" cannot be used together.')
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => isset($v['service'], $v['strategy_service']))
                        ->thenInvalid('"service" and "strategy_service" cannot be used together.')
                    ->end()
                ->end()
                ->arrayNode('role_hierarchy')
                    ->useAttributeAsKey('id')
                    ->arrayPrototype()
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(fn ($v) => ['value' => $v])->end()
                        ->beforeNormalization()
                            ->ifTrue(fn ($v) => \is_array($v) && isset($v['value']))
                            ->then(fn ($v) => \preg_split('/\s*,\s*/', $v['value']))
                        ->end()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->arrayNode('password_hashers')
                    ->example([
                        'App\Entity\User1' => 'auto',
                        'App\Entity\User2' => [
                            'algorithm' => 'auto',
                            'time_cost' => 8,
                            'cost' => 13,
                        ],
                    ])
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->canBeUnset()
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(fn ($v) => ['algorithm' => $v])->end()
                        ->children()
                            ->scalarNode('algorithm')
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(fn ($v) => !\is_string($v))
                                    ->thenInvalid('You must provide a string value.')
                                ->end()
                            ->end()
                            ->arrayNode('migrate_from')
                                ->prototype('scalar')->end()
                                ->beforeNormalization()->castToArray()->end()
                            ->end()
                            ->scalarNode('hash_algorithm')->info('Name of hashing algorithm for PBKDF2 (i.e. sha256, sha512, etc..) See hash_algos() for a list of supported algorithms.')->defaultValue('sha512')->end()
                            ->scalarNode('key_length')->defaultValue(40)->end()
                            ->booleanNode('ignore_case')->defaultFalse()->end()
                            ->booleanNode('encode_as_base64')->defaultTrue()->end()
                            ->scalarNode('iterations')->defaultValue(5000)->end()
                            ->integerNode('cost')
                                ->min(4)
                                ->max(31)
                                ->defaultNull()
                            ->end()
                            ->scalarNode('memory_cost')->defaultNull()->end()
                            ->scalarNode('time_cost')->defaultNull()->end()
                            ->scalarNode('id')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('access_control')
                    ->cannotBeOverwritten()
                    ->arrayPrototype()
                        ->fixXmlConfig('ip')
                        ->fixXmlConfig('method')
                        ->children()
                            ->scalarNode('request_matcher')->defaultNull()->end()
                            ->scalarNode('requires_channel')->defaultNull()->end()
                            ->scalarNode('path')
                                ->defaultNull()
                                ->info('use the urldecoded format')
                                ->example('^/path to resource/')
                            ->end()
                            ->scalarNode('host')->defaultNull()->end()
                            ->integerNode('port')->defaultNull()->end()
                            ->arrayNode('ips')
                                ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('methods')
                                ->beforeNormalization()->ifString()->then(fn ($v) => \preg_split('/\s*,\s*/', $v))->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->fixXmlConfig('role')
                        ->children()
                            ->arrayNode('roles')
                            ->beforeNormalization()->ifString()->then(fn ($v) => \preg_split('/\s*,\s*/', $v))->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('throttling')
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => true === $v)->then(fn () => ['limiter' => null, 'enabled' => true])
                        ->ifString()->then(fn ($v) => ['limiter' => $v, 'enabled' => true])
                    ->end()
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('limiter')->info(\sprintf('A service id implementing "%s".', AbstractRequestRateLimiter::class))->defaultNull()->end()
                        ->integerNode('max_attempts')->defaultValue(5)->end()
                        ->scalarNode('interval')->defaultValue('1 minute')->end()
                        ->scalarNode('lock_factory')->info('The service ID of the lock factory used by the login rate limiter (or null to disable locking)')->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
        ;

        $this->addProvidersSection($rootNode);
        $this->addAuthenticatorsSection($rootNode);

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
        if (!\class_exists(Authenticator::class)) {
            throw new \LogicException('Security support cannot be enabled as the Security library is not installed. Try running "composer require biurad/security".');
        }

        if (isset($configs['access_decision_manager']['service'])) {
            $container->alias('security.access.decision_manager', $configs['access_decision_manager']['service']);
        } else {
            if (isset($configs['access_decision_manager']['strategy_service'])) {
                $strategyService = new Reference($configs['access_decision_manager']['strategy_service']);
            }

            $container->autowire('security.access.decision_manager', new Definition(AccessDecisionManager::class, [
                new TaggedLocator('security.voter'),
                $strategyService ?? $this->createStrategyStatement(
                    $configs['access_decision_manager']['strategy'] ?? 'affirmative',
                    $configs['access_decision_manager']['allow_if_all_abstain'],
                    $configs['access_decision_manager']['allow_if_equal_granted_denied']
                ),
            ]));
        }

        if (isset($configs['role_hierarchy']) && \count($configs['role_hierarchy']) > 0) {
            $container->set('security.access.role_hierarchy_voter', new Definition(RoleHierarchyVoter::class, [new Statement(RoleHierarchy::class, [$configs['role_hierarchy']])]))->tag('security.voter', ['priority' => 245]);
        } else {
            $container->set('security.role_hierarchy', new Definition(RoleVoter::class))->public(false)->tag('security.voter', ['priority' => 245]);
        }

        if (!empty($configs['access_control'])) {
            $accessMap = $container->autowire('security.access_map', new Definition(AccessMap::class));
            $container->autowire('security.access_map_handler', new Definition(FirewallAccessHandler::class, [new Reference('security.access_map'), new Reference('security.token_storage'), new Reference('security.access.decision_manager')]));

            foreach ($configs['access_control'] as $access) {
                if (isset($access['request_matcher'])) {
                    if ($access['path'] || $access['host'] || $access['port'] || $access['ips'] || $access['methods']) {
                        throw new InvalidConfigurationException('The "request_matcher" option should not be specified alongside other options. Consider integrating your constraints inside your RequestMatcher directly.');
                    }
                    $matcher = new Reference($access['request_matcher']);
                } else {
                    $matcher = $this->createRequestMatcher(
                        $container,
                        $access['path'],
                        $access['host'],
                        $access['port'],
                        $access['methods'],
                        $access['ips']
                    );
                }
                $attributes = $access['roles'];

                if (0 === \count(\array_filter($access))) {
                    throw new InvalidConfigurationException('One or more access control items are empty. Did you accidentally add lines only containing a "-" under "security.access_control"?');
                }

                $accessMap->bind('add', [$matcher, $attributes, $access['requires_channel'] ?? null]);
            }
        }

        if ($configs['password_hashers']) {
            $hasherMap = $hashClasses = [];

            foreach ($configs['password_hashers'] as $class => $hasher) {
                $hasherMap[$hashClasses[] = $class] = isset($hasher['id']) ? new Reference($hasher['id']) : $this->createHasher($hasher);
            }

            $container->autowire('security.password_hasher_factory', new Definition(PasswordHasherFactory::class, [$hasherMap]));
            $container->autowire('security.user_password_hasher', new Definition(UserPasswordHasher::class, [new Reference('security.password_hasher_factory')]));

            if ($container->has('console')) {
                $container->set('console.command.user_password_encoder', new Definition(UserPasswordHashCommand::class, [1 => $hashClasses]))->public(false)->tag('console.command', 'security:hash-password');
            }
        }

        if (!empty($configs['providers'])) {
            $userProviders = [];
            $nbUserProviders = 0;

            foreach ($configs['providers'] as $nUP => $provider) {
                ++$nbUserProviders;
                $userProviders[] = $this->createUserDaoProvider(\str_replace('-', '_', $nUP), $provider, $container);
            }

            if ($nbUserProviders > 1) {
                $container->autowire($providerId = 'security.user_providers', new Definition(ChainUserProvider::class, [$userProviders]));

                foreach ($userProviders as $userProvider) {
                    $container->definition((string) $userProvider)->public(true);
                }
            } elseif (1 === $nbUserProviders) {
                $container->definition($providerId = (string) $userProviders[0])->typed();
            } else {
                // This may never be reached ...
                $container->set($providerId = 'security.missing_user_provider', new Definition(MissingUserProvider::class))->typed();
            }

            if ($container->has('console')) {
                $container->set('console.command.user_status', new Definition(UserStatusCommand::class, [new Reference($providerId)]))
                    ->public(false)->tag('console.command', 'security:user-status');
            }
        }

        if (!empty($configs['authenticators'])) {
            $authenticators = [];

            foreach ($configs['authenticators'] as $nAP => $provider) {
                foreach ($this->factoryProviders as $factory) {
                    if ($nAP === $key = \str_replace('-', '_', $factory->getKey())) {
                        $factory->create($container, $authenticators[] = 'security.authenticator.'.$key, $provider);
                    }
                }
            }

            $authenticators = $configs['include_authenticators'] ? \array_map(fn (string $factory) => new Reference($factory), $authenticators) : [];
        }

        if ($configs['throttling']['enabled'] && null === $configs['throttling']['limiter']) {
            if (!\class_exists(RateLimiterFactory::class)) {
                throw new \LogicException('Login throttling requires the Rate Limiter component. Try running "composer require symfony/rate-limiter".');
            }

            $limiterOptions = [
                'policy' => 'fixed_window',
                'limit' => $configs['throttling']['max_attempts'],
                'interval' => $configs['throttling']['interval'],
                'lock_factory' => $configs['throttling']['lock_factory'],
            ];
            (new Symfony\RateLimiterExtension())->register($container, [
                'enabled' => true,
                'limiters' => [
                    $localId = 'login_local' => $limiterOptions,
                    $globalId = 'login_global' => ['limit' => 5 * $configs['throttling']['max_attempts']] + $limiterOptions,
                ],
            ]);
            $container->autowire('security.authenticator_throttling', new Definition(DefaultLoginRateLimiter::class, [new Reference('limiter.'.$globalId), new Reference('limiter.'.$localId)]));
        }

        if ('cache' === $configs['token_storage']) {
            if (empty($container->getExtensionConfig(Symfony\CacheExtension::class, $container->hasExtension(Symfony\FrameworkExtension::class) ? 'symfony' : null))) {
                throw new \LogicException(\sprintf('You cannot use the "cache" token storage without the "%s" extension.', Symfony\CacheExtension::class));
            }
            $tokenType = new Reference('cache.app');
        } elseif ($container->has('http.session')) {
            $tokenType = new Reference('http.session');
        }

        $container->autowire('security.authenticator', new Definition(Authenticator::class, [
            $authenticators ?? [],
            4 => new Reference('?'.($configs['throttling']['limiter'] ?? 'security.authenticator_throttling')),
            7 => $configs['hide_user_not_found'],
            8 => $configs['erase_credentials'],
            9 => $configs['firewall_name'],
        ]));
        $container->autowire('security.token_storage', isset($tokenType)
            ? new Definition(CacheableTokenStorage::class, [$tokenType, new Statement('Nette\Utils\DateTime::from', [$configs['token_storage_expiry']])])
            : new Definition(TokenStorage::class));
        $container->set('security.access.authenticated_voter', new Definition(AuthenticatedVoter::class, [new Statement(AuthenticationTrustResolver::class)]))->public(false)->tag('security.voter', ['priority' => 250]);
        $container->set('security.logout_handler', new Definition(LogoutHandler::class, [new Reference('security.token_storage')]))->typed();

        if ($container->hasExtension(Symfony\ValidatorExtension::class)) {
            $container->set('security.validator.user_password', new Definition(UserPasswordValidator::class))->public(false)->tag('validator.constraint_validator', ['alias' => 'security.validator.user_password']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        foreach ($this->factoryProviders as $factory) {
            if (!$factory instanceof BootExtensionInterface) {
                continue;
            }
            $factory->boot($container);
        }
    }

    private function addProvidersSection(ArrayNodeDefinition $rootNode): void
    {
        $providerNodeBuilder = $rootNode
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('providers')
                    ->example([
                        'my_memory_provider' => [
                            'memory' => [
                                'users' => [
                                    'foo' => ['password' => 'foo', 'roles' => 'ROLE_USER'],
                                    'bar' => ['password' => 'bar', 'roles' => ['ROLE_USER', 'ROLE_ADMIN']],
                                ],
                            ],
                        ],
                        'my_entity_provider' => ['entity' => ['class' => 'Security::User', 'property' => 'username']],
                    ])
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
        ;

        $providerNodeBuilder
            ->beforeNormalization()
                ->ifString()->then(fn ($v) => ['id' => $v])
            ->end()
            ->children()
                ->scalarNode('id')->end()
                ->arrayNode('chain')
                    ->fixXmlConfig('provider')
                    ->children()
                        ->arrayNode('providers')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(fn ($v) => \preg_split('/\s*,\s*/', $v))
                            ->end()
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        foreach ($this->userProviders as $factory) {
            $name = \str_replace('-', '_', $factory->getKey());
            $factoryNode = $providerNodeBuilder->children()->arrayNode($name)->canBeUnset();
            $factory->addConfiguration($factoryNode);
        }

        $providerNodeBuilder
            ->validate()
                ->ifTrue(fn ($v) => \count($v) > 1)
                ->thenInvalid('You cannot set multiple provider types for the same provider')
            ->end()
            ->validate()
                ->ifTrue(fn ($v) => 0 === \count($v))
                ->thenInvalid('You must set a provider definition for the provider.')
            ->end()
        ;
    }

    private function addAuthenticatorsSection(ArrayNodeDefinition $definition): void
    {
        $authenticatorNodeBuilder = $definition
            ->fixXmlConfig('authenticator')
            ->children()
                ->arrayNode('authenticators')
                    ->example([
                        'form_login' => [
                            'user_parameter' => '_username',
                            'provider' => 'my_memory_provider',
                        ],
                        'my_custom' => [
                            'custom' => [
                                'class' => 'My\Custom\Authenticator',
                            ],
                        ],
                    ])
                    ->children()
        ;

        foreach ($this->factoryProviders as $factory) {
            $name = \str_replace('-', '_', $factory->getKey());
            $factoryNode = $authenticatorNodeBuilder->arrayNode($name)->canBeUnset();
            $factory->addConfiguration($factoryNode);
        }

        $authenticatorNodeBuilder->end();
    }

    private function createHasher(array $config): array
    {
        if ($config['migrate_from'] ?? false) {
            return $config;
        }

        // plaintext hasher
        if ('plaintext' === $config['algorithm']) {
            $arguments = [$config['ignore_case']];

            return [
                'class' => PlaintextPasswordHasher::class,
                'arguments' => $arguments,
            ];
        }

        // pbkdf2 hasher
        if ('pbkdf2' === $config['algorithm']) {
            return [
                'class' => Pbkdf2PasswordHasher::class,
                'arguments' => [
                    $config['hash_algorithm'],
                    $config['encode_as_base64'],
                    $config['iterations'],
                    $config['key_length'],
                ],
            ];
        }

        // bcrypt hasher
        if ('bcrypt' === $config['algorithm']) {
            $config['algorithm'] = 'native';
            $config['native_algorithm'] = \PASSWORD_BCRYPT;

            return $this->createHasher($config);
        }

        // Argon2i hasher
        if ('argon2i' === $config['algorithm']) {
            if (SodiumPasswordHasher::isSupported() && !\defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                $config['algorithm'] = 'sodium';
            } elseif (\defined('PASSWORD_ARGON2I')) {
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_ARGON2I;
            } else {
                throw new InvalidConfigurationException(\sprintf('Algorithm "argon2i" is not available. Either use "%s" or upgrade to PHP 7.2+ instead.', \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13') ? 'argon2id", "auto' : 'auto'));
            }

            return $this->createHasher($config);
        }

        if ('argon2id' === $config['algorithm']) {
            if (($hasSodium = SodiumPasswordHasher::isSupported()) && \defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')) {
                $config['algorithm'] = 'sodium';
            } elseif (\defined('PASSWORD_ARGON2ID')) {
                $config['algorithm'] = 'native';
                $config['native_algorithm'] = \PASSWORD_ARGON2ID;
            } else {
                throw new InvalidConfigurationException(\sprintf('Algorithm "argon2id" is not available. Either use "%s", upgrade to PHP 7.3+ or use libsodium 1.0.15+ instead.', \defined('PASSWORD_ARGON2I') || $hasSodium ? 'argon2i", "auto' : 'auto'));
            }

            return $this->createHasher($config);
        }

        if ('native' === $config['algorithm']) {
            return [
                'class' => NativePasswordHasher::class,
                'arguments' => [
                    $config['time_cost'],
                    (($config['memory_cost'] ?? 0) << 10) ?: null,
                    $config['cost'],
                ] + (isset($config['native_algorithm']) ? [3 => $config['native_algorithm']] : []),
            ];
        }

        if ('sodium' === $config['algorithm']) {
            if (!SodiumPasswordHasher::isSupported()) {
                throw new InvalidConfigurationException('Libsodium is not available. Install the sodium extension or use "auto" instead.');
            }

            return [
                'class' => SodiumPasswordHasher::class,
                'arguments' => [
                    $config['time_cost'],
                    (($config['memory_cost'] ?? 0) << 10) ?: null,
                ],
            ];
        }

        // run-time configured hasher
        return $config;
    }

    // Parses a <provider> tag and returns the id for the related user provider service
    private function createUserDaoProvider(string $name, array $provider, Container $container): Reference
    {
        $name = 'security.user.provider.concrete.'.\strtolower($name);

        // Doctrine Entity and In-memory DAO provider are managed by factories
        foreach ($this->userProviders as $factory) {
            $key = \str_replace('-', '_', $factory->getKey());

            if (!empty($provider[$key])) {
                $factory->create($container, $name, $provider[$key]);

                return new Reference($name);
            }
        }

        // Existing DAO service provider
        if (isset($provider['id'])) {
            $container->alias($name, $provider['id']);

            return new Reference($provider['id']);
        }

        // Chain provider
        if (isset($provider['chain'])) {
            $providers = [];

            foreach ($provider['chain']['providers'] as $providerName) {
                $providers[] = new Reference('security.user.provider.concrete.'.\strtolower($providerName));
            }
            $container->set($name, new Definition(ChainUserProvider::class, [$providers]))->public(false);

            return new Reference($name);
        }

        throw new InvalidConfigurationException(\sprintf('Unable to create definition for "%s" user provider.', $name));
    }

    /**
     * @throws \InvalidArgumentException if the $strategy is invalid
     */
    private function createStrategyStatement(string $strategy, bool $allowIfAllAbstainDecisions, bool $allowIfEqualGrantedDeniedDecisions): Statement
    {
        switch ($strategy) {
            case 'affirmative':
                return new Statement(AffirmativeStrategy::class, [$allowIfAllAbstainDecisions]);
            case 'consensus':
                return new Statement(ConsensusStrategy::class, [$allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions]);
            case 'unanimous':
                return new Statement(UnanimousStrategy::class, [$allowIfAllAbstainDecisions]);
            case 'priority':
                return new Statement(PriorityStrategy::class, [$allowIfAllAbstainDecisions]);
        }

        throw new \InvalidArgumentException(\sprintf('The strategy "%s" is not supported.', $strategy));
    }

    private function createRequestMatcher(Container $container, string $path = null, string $host = null, int $port = null, array $methods = [], array $ips = null, array $attributes = []): Statement
    {
        if ($methods) {
            $methods = \array_map('strtoupper', $methods);
        }

        if (null !== $ips) {
            foreach ($ips as $ip) {
                $ip = $container->parameter($ip);

                if (!$this->isValidIps($ip)) {
                    throw new \LogicException(\sprintf('The given value "%s" in the "security.access_control" config option is not a valid IP address.', $ip));
                }
            }
        }

        // only add arguments that are necessary
        $arguments = [$path, $host, $methods, $ips, $attributes, null, $port];

        while (\count($arguments) > 0 && !\end($arguments)) {
            \array_pop($arguments);
        }

        return new Statement(RequestMatcher::class, $arguments);
    }

    /**
     * @param string|array<int,string> $ips
     */
    private function isValidIps($ips): bool
    {
        $ipsList = \array_reduce((array) $ips, static fn (array $ips, string $ip) => \array_merge($ips, \preg_split('/\s*,\s*/', $ip)), []);

        if (!$ipsList) {
            return false;
        }

        foreach ($ipsList as $cidr) {
            if (!$this->isValidIp($cidr)) {
                return false;
            }
        }

        return true;
    }

    private function isValidIp(string $cidr): bool
    {
        $cidrParts = \explode('/', $cidr);

        if (1 === \count($cidrParts)) {
            return false !== \filter_var($cidrParts[0], \FILTER_VALIDATE_IP);
        }

        $ip = $cidrParts[0];
        $netmask = $cidrParts[1];

        if (!\ctype_digit($netmask)) {
            return false;
        }

        if (\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return $netmask <= 32;
        }

        if (\filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return $netmask <= 128;
        }

        return false;
    }
}
