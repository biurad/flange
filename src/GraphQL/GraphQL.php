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

namespace Rade\GraphQL;

use Biurad\Http\Response\JsonResponse;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Server\Helper;
use GraphQL\Server\ServerConfig;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use Psr\Http\Message\RequestInterface;

/**
 * Represents a GraphQL schema.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class GraphQL
{
    private Schema $schema;

    public function __construct(DocumentNode|Schema $schema, callable $typeConfigDecorator = null, array $options = [])
    {
        if ($schema instanceof DocumentNode) {
            $schema = (new BuildSchema($schema, $typeConfigDecorator, $options))->buildSchema();
        }

        $this->schema = $schema;
    }

    public function getScheme(): Schema
    {
        return $this->schema;
    }

    /**
     * @param mixed|callable $context
     */
    public function run(RequestInterface $request, $context): JsonResponse
    {
        $config = new ServerConfig();
        $config->setSchema($this->schema);
        $config->setContext($context);

        $helper = new Helper();
        $parsedBody = $helper->parsePsrRequest($request);

        if (\is_array($parsedBody)) {
            $result = $helper->executeBatch($config, $parsedBody);
        }

        return new JsonResponse($result ?? $helper->executeOperation($config, $parsedBody));
    }
}
