<?php

namespace STS\Keep\Server;

use STS\Keep\KeepManager;
use Exception;

class Router
{
    private array $routes = [];
    private KeepManager $manager;

    public function __construct(KeepManager $manager)
    {
        $this->manager = $manager;
    }

    public function get(string $pattern, array $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, array $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, array $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    private function addRoute(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function dispatch(string $method, string $path, array $query = [], array $body = []): array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $path);
            if ($params === false) {
                continue;
            }

            try {
                [$class, $method] = $route['handler'];
                $controller = new $class($this->manager, $query, $body);
                
                // Call method with extracted parameters
                return call_user_func_array([$controller, $method], $params);
            } catch (Exception $e) {
                return [
                    'error' => $e->getMessage(),
                    'type' => get_class($e),
                    '_status' => 500
                ];
            }
        }

        return ['error' => 'Not found', '_status' => 404];
    }

    private function matchRoute(string $pattern, string $path): array|false
    {
        // Convert route pattern to regex
        // /api/secrets/:key becomes /api/secrets/([^/]+)
        $regex = preg_replace('/:([^\/]+)/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            array_shift($matches); // Remove full match
            return $matches;
        }

        return false;
    }
}