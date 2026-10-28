<?php

declare(strict_types=1);

namespace App;

final class Router
{
    private array $routes = [];
    private array $middleware = [];

    /**
     * @param string $path URL path
     * @param callable|array $handler Closure or [Class::class, 'method'] array
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * @param string $path URL path
     * @param callable|array $handler Closure or [Class::class, 'method'] array
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * @param string $path URL path
     * @param callable|array $handler Closure or [Class::class, 'method'] array
     */
    public function any(string $path, callable|array $handler): void
    {
        $this->addRoute('*', $path, $handler);
    }

    /**
     * @param string $method HTTP method or '*'
     * @param string $path URL path
     * @param callable|array $handler Closure or [Class::class, 'method'] array
     */
    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([a-zA-Z0-9_.-]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[$method . '|' . $path] = [
            'pattern' => $pattern,
            'handler' => $handler,
            'params'  => $this->extractParamNames($path),
        ];
    }

    private function extractParamNames(string $path): array
    {
        $params = [];
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, $matches);
        foreach ($matches[1] as $name) {
            $params[] = $name;
        }
        return $params;
    }

    public function addMiddleware(string $path, string $middlewareClass): void
    {
        $this->middleware[$path][] = $middlewareClass;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = strtok($uri, '?');

        $key = $method . '|' . $uri;
        if (isset($this->routes[$key])) {
            $this->executeRoute($this->routes[$key], [], $uri);
            return;
        }

        foreach ($this->routes as $routeKey => $route) {
            [$routeMethod, $routePath] = explode('|', $routeKey);
            if ($routeMethod !== $method && $routeMethod !== '*') {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['params'], array_map(fn($v) => urldecode($v), $matches));
                $this->executeRoute($route, $params, $uri);
                return;
            }
        }

        http_response_code(404);
        Response::html('<h1>404 — Page Not Found</h1><p>The requested page does not exist.</p>', 404);
    }

    /**
     * @param array{handler: callable|array, params: string[]} $route
     * @param array<string, string> $params
     */
    private function executeRoute(array $route, array $params, string $uri): void
    {
        $handler = $route['handler'];

        // Apply middleware for this route (match by exact path first, then by pattern)
        $matchedPath = null;
        foreach ($this->routes as $routeKey => $r) {
            if ($r['pattern'] === $route['pattern']) {
                $matchedPath = explode('|', $routeKey)[1];
                break;
            }
        }
        $middleware = $matchedPath ? ($this->middleware[$matchedPath] ?? []) : [];
        foreach ($middleware as $middlewareClass) {
            $instance = new $middlewareClass();
            if (method_exists($instance, 'handle')) {
                $result = $instance->handle();
                if ($result === false) {
                    return; // Middleware blocked the request
                }
            }
        }

        // If handler is an array [Class::class, 'method'], instantiate and call
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            $controller = new $handler[0]();
            $method = new \ReflectionMethod($handler[0], $handler[1]);
            $args = [];
            foreach ($method->getParameters() as $param) {
                if (array_key_exists($param->getName(), $params)) {
                    $value = $params[$param->getName()];
                    $type = $param->getType();
                    if ($type instanceof \ReflectionNamedType) {
                        $typeName = $type->getName();
                        if ($typeName === 'int') {
                            $value = (int) $value;
                        } elseif ($typeName === 'float') {
                            $value = (float) $value;
                        } elseif ($typeName === 'bool') {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        } elseif ($typeName === 'string') {
                            $value = (string) $value;
                        }
                    }
                    $args[] = $value;
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                }
            }
            $controller->{$handler[1]}(...$args);
        } elseif (is_callable($handler)) {
            $handler($params);
        } else {
            http_response_code(500);
            Response::html('<h1>500 — Internal Server Error</h1><p>Invalid route handler.</p>', 500);
        }
    }
}
