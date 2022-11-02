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

namespace Flange\Database\Cycle\Config;

use Cycle\Database\Config\PDOConnectionConfig;

/**
 * {@inheritdoc}
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Connection extends PDOConnectionConfig
{
    /**
     * @param array<string,mixed> $options
     */
    public function __construct(private string $dsn, ?string $username, ?string $password, array $options)
    {
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
