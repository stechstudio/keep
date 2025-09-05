<?php

namespace STS\Keep\Server;

use STS\Keep\KeepManager;
use Exception;

/**
 * Lightweight router for the Keep Web UI server.
 * 
 * This simple router handles our API endpoints without the overhead
 * of a full framework router. For our ~10 endpoints, this is more
 * than sufficient and keeps dependencies minimal.
 */
class Router
{
    private array $routes = [];
    private KeepManager $manager;
    private array $middleware = [];

    public function __construct(KeepManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Register a GET route
     */
    public function get(string $pattern, array $handler): self
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $pattern, array $handler): self
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    /**
     * Register a PUT route
     */
    public function put(string $pattern, array $handler): self
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $pattern, array $handler): self
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $pattern, array $handler): self
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    /**
     * Add middleware to be run before route dispatch
     */
    public function middleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    private function addRoute(string $method, string $pattern, array $handler): self
    {
        // Pre-compile the regex pattern for better performance
        $regex = $this->compilePattern($pattern);
        
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler
        ];
        
        return $this;
    }

    /**
     * Dispatch a request to the appropriate handler
     */
    public function dispatch(string $method, string $path, array $query = [], array $body = []): array
    {
        // Run middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware($method, $path, $query, $body);
            if ($result !== null) {
                return $result; // Middleware can short-circuit
            }
        }
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== '*') {
                continue;
            }

            $params = $this->matchRoute($route['regex'], $path);
            if ($params === false) {
                continue;
            }

            try {
                [$class, $action] = $route['handler'];
                $controller = new $class($this->manager, $query, $body);
                
                // Call method with extracted parameters
                return call_user_func_array([$controller, $action], $params);
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

    /**
     * Compile a route pattern into a regex
     */
    private function compilePattern(string $pattern): string
    {
        // Convert route pattern to regex
        // :param becomes ([^/]+) - matches any non-slash characters
        // :param? becomes ([^/]*) - optional parameter
        $pattern = preg_replace('/:([^\/]+)\?/', '([^/]*)', $pattern);
        $pattern = preg_replace('/:([^\/]+)/', '([^/]+)', $pattern);
        
        return '#^' . $pattern . '$#';
    }

    /**
     * Match a path against a compiled route regex
     */
    private function matchRoute(string $regex, string $path): array|false
    {
        if (preg_match($regex, $path, $matches)) {
            array_shift($matches); // Remove full match
            return array_map('urldecode', $matches); // Decode URL parameters
        }

        return false;
    }
}