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

namespace Rade\Debug\Tracy;

use Tracy\Helpers;
use Tracy\IBarPanel;
use Biurad\UI\Template;

final class TemplatesPanel implements IbarPanel
{
    private Template $render;
    private int $templateCount = 0;

    /** @var array<int,string> */
    private array $templates = [];

    /**
     * Initialize the panel.
     */
    public function __construct(Template $template)
    {
        $this->render = $template;
    }

    /**
     * {@inheritdoc}
     */
    public function getPanel(): string
    {
        return Helpers::capture(fn () => require __DIR__ . '/templates/TemplatesPanel.panel.phtml');
    }

    /**
     * {@inheritdoc}
     */
    public function getTab(): string
    {
        ($prop = new \ReflectionProperty($this->render, 'loadedTemplates'))->setAccessible(true);

        foreach ($prop->getValue($this->render) as $name => $profile) {
            ++$this->templateCount;
            $this->templates[] = [
                'name' => $name,
                'path' => \str_replace('html:', '', $profile[0]),
                'render' => \get_class($this->render->getRenders()[$profile[1]]),
            ];
        }

        return Helpers::capture(static fn () => require __DIR__ . '/templates/TemplatesPanel.tab.phtml');
    }
}
