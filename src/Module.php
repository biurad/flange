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

namespace Flange;

/**
 * A module extension feature (A.K.A plugin).
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class Module implements \JsonSerializable, \IteratorAggregate
{
    private array $moduleConfig;
    private string $directory;

    public function __construct(string $directory, array $moduleConfig)
    {
        $this->directory = $directory;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Checks if module is enabled or not.
     */
    public function isEnabled(): bool
    {
        return $this->moduleConfig['enabled'] ?? false;
    }

    /**
     * If the module has an author.
     */
    public function getAuthor(): ?string
    {
        return $this->moduleConfig['author'] ?? null;
    }

    /**
     * {@see Rade\Traits\HelperTrait::loadExtensions()}.
     */
    public function getExtensions(): array
    {
        return $this->moduleConfig['extensions'] ?? [];
    }

    /**
     * Gets the module's directory.
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->moduleConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->moduleConfig);
    }
}
