<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void { $this->match(['GET'], $path, $handler); }
    public function post(string $path, array $handler): void { $this->match(['POST'], $path, $handler); }

    public function match(array $methods, string $path, array $handler): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        $this->routes[] = compact('methods', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . trim($uri, '/');
        if ($uri !== '/' && str_ends_with($uri, '/')) $uri = rtrim($uri, '/');
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) continue;
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $route['handler'];
                echo (new $class())->$action($params);
                return;
            }
        }
        http_response_code(404);
        echo View::render('pages/404', ['title' => 'Not found']);
    }
}
