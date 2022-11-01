<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Debug;

use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

class AppResource implements SelfCheckingResourceInterface
{
    public function __construct(private string $resource = \PHP_VERSION_ID.\PHP_SAPI.\PHP_OS)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return __CLASS__;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh(int $timestamp): bool
    {
        return $this->resource === \PHP_VERSION_ID.\PHP_SAPI.\PHP_OS;
    }
}
