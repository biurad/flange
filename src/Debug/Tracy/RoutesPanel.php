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

use Biurad\Http\Uri;
use Flight\Routing\Route;
use Flight\Routing\Router;
use Nette;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tracy;

/**
 * Routing debugger for Debug Bar.
 */
final class RoutesPanel implements Tracy\IBarPanel
{
    use Nette\SmartObject;

    private int $routeCount = 0;
    private Router $profiler;
    private ?Request $request;

    /** @var array<int,mixed> */
    private array $routes = [];

    /** @var \ReflectionClass|\ReflectionFunction|\ReflectionMethod|string */
    private ?\Reflector $source = null;

    public function __construct(Router $router, RequestStack $request)
    {
        $this->profiler = $router;
        $this->request = $request->getMainRequest();
    }

    /**
     * Renders tab.
     */
    public function getTab(): string
    {
        foreach ($this->profiler->getCollection()->getRoutes() as $route) {
            $this->processData($route);
        }

        return Nette\Utils\Helpers::capture(function (): void {
            require __DIR__ . '/templates/RoutingPanel.tab.phtml';
        });
    }

    /**
     * Renders panel.
     */
    public function getPanel(): string
    {
        return Nette\Utils\Helpers::capture(function (): void {
            require __DIR__ . '/templates/RoutingPanel.panel.phtml';
        });
    }

    private function processData(Route $profile): void
    {
        ++$this->routeCount;
        $data = ['matched' => false, 'route' => $profile, 'name' => $profile->getName()];

        if (null !== ($r = $this->request) && $profile === $this->profiler->match($r->getMethod(), new Uri($r->getUri()))) {
            $data['matched'] = true;
        }

        $this->routes[] = $data;
    }
}
