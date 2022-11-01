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

namespace Flange\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema as TypeSchema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;

/**
 * Definitions of GraphQL schemas.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Schema implements \Countable
{
    private const CACHE_PREFIX = '.cache.php';

    private array $schemas;
    private int $count = 0;

    public function __construct(array $schemas = [], private $cacheDir = null)
    {
        foreach ($schemas as $name => $schema) {
            if ($schema instanceof TypeSchema) {
                $this->schemas[$name] = $schema;
                ++$this->count;

                continue;
            }

            $this->set($name, $schema);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->count;
    }

    public function set(string $name, string|TypeSchema|\Closure $schema): void
    {
        ++$this->count;

        if (null !== $cache = $this->cacheDir) {
            if (\file_exists($cache = ($cache . $name . self::CACHE_PREFIX))) {
                $this->schemas[$name] = AST::fromArray(require $cache);

                return;
            }
        }

        $this->schemas[$name] = $schema = $this->resolveSchema($schema instanceof \Closure ? $schema() : $schema, $cache);
    }

    public function get(string $name): ?TypeSchema
    {
        return $this->schemas[$name] ?? null;
    }

    private function resolveSchema($schema, ?string $cache): TypeSchema
    {
        if ($schema instanceof TypeSchema) {
            return $schema;
        }

        if ($schema instanceof DocumentNode) {
            $schema = Parser::parse($schema);
        } elseif (\is_string($schema)) {
            if (!\is_file($schema)) {
                throw new \InvalidArgumentException(\sprintf('Schema file "%s" does not exist.', $schema));
            }

            if ('php' === \pathinfo($schema, \PATHINFO_EXTENSION)) {
                return require $schema;
            }

            $schema = Parser::parse(new Source(\file_get_contents($schema), \pathinfo($schema, \PATHINFO_BASENAME)));
        }

        if (null !== $cache) {
            if (!\is_dir($directory = \dirname($cache))) {
                @\mkdir($directory, 0775, true);
            }

            \file_put_contents($cache, "<?php\nreturn " . \var_export(AST::toArray($schema), true) . ";\n");
        }

        return (new BuildSchema($schema))->buildSchema();
    }
}
