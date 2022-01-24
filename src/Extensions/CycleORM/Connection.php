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

namespace Rade\DI\Extensions\CycleORM;

use Cycle\Database\Config\PDOConnectionConfig;

/**
 * {@inheritdoc}
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Connection extends PDOConnectionConfig
{
    private string $dsn;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(string $dsn, ?string $username, ?string $password, array $options)
    {
        $this->dsn = $dsn;
        parent::__construct($username, $password, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return \substr($this->dsn, \strpos($this->dsn, ':'));
    }

    /**
     * {@inheritdoc}
     */
    public function getDsn(): string
    {
        return $this->dsn;
    }
}
