<?php

declare(strict_types=1);

namespace LabTrack\Router;

use LabTrack\Http\Request;
use LabTrack\Http\Response;

class Router
{
    /** @var array<string, array<int, array{path: string, regex: ?string, params: array, handler: mixed}>> */
    private array $routes = [];

    public function get(string $path, mixed $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, mixed $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, mixed $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, mixed $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, mixed $handler): self
    {
        $method = strtoupper($method);
        $trimmed = trim($path, '/');
        $normalizedPath = $trimmed === '' ? '/' : '/' . $trimmed;

        $params = [];
        $regex = null;

        if (str_contains($normalizedPath, '{')) {
            // Pattern has parameters, e.g. /labs/{id}/edit
            $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use (&$params) {
                $params[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $normalizedPath);
            $regex = '#^' . $regex . '$#';
        }

        $this->routes[$method][] = [
            'path' => $normalizedPath,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        $routesForMethod = $this->routes[$method] ?? [];
        $matchedHandler = null;
        $routeParams = [];

        foreach ($routesForMethod as $route) {
            if ($route['regex'] === null) {
                if ($route['path'] === $path) {
                    $matchedHandler = $route['handler'];
                    break;
                }
            } else {
                if (preg_match($route['regex'], $path, $matches)) {
                    $matchedHandler = $route['handler'];
                    foreach ($route['params'] as $paramName) {
                        if (isset($matches[$paramName])) {
                            $routeParams[$paramName] = urldecode((string) $matches[$paramName]);
                        }
                    }
                    break;
                }
            }
        }

        if ($matchedHandler !== null) {
            $request->setRouteParams($routeParams);
            return $this->executeHandler($matchedHandler, $request, $routeParams);
        }

        // Check if path exists under another HTTP method for 405 Method Not Allowed
        $allowedMethods = [];
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if ($route['regex'] === null) {
                    if ($route['path'] === $path) {
                        $allowedMethods[] = $otherMethod;
                        break;
                    }
                } else {
                    if (preg_match($route['regex'], $path)) {
                        $allowedMethods[] = $otherMethod;
                        break;
                    }
                }
            }
        }

        if (!empty($allowedMethods)) {
            $allowedStr = implode(', ', array_unique($allowedMethods));
            return new Response(
                '405 Method Not Allowed. Allowed: ' . htmlspecialchars($allowedStr, ENT_QUOTES, 'UTF-8'),
                405,
                ['Allow' => $allowedStr, 'Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        // 404 Not Found
        return new Response('404 Not Found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8'), 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function executeHandler(mixed $handler, Request $request, array $routeParams): Response
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $instance = new $class();
            $result = $this->invokeControllerMethod($instance, $method, $request, $routeParams);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$target, $method] = $handler;
            $instance = is_object($target) ? $target : new $target();
            $result = $this->invokeControllerMethod($instance, $method, $request, $routeParams);
        } elseif (is_callable($handler)) {
            $result = call_user_func($handler, $request, ...array_values($routeParams));
        } else {
            throw new \RuntimeException('Invalid route handler configuration');
        }

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html((string) $result);
    }

    private function invokeControllerMethod(object $controller, string $method, Request $request, array $routeParams): mixed
    {
        if (!method_exists($controller, $method)) {
            throw new \BadMethodCallException('Method ' . get_class($controller) . '::' . $method . ' does not exist');
        }

        $reflection = new \ReflectionMethod($controller, $method);
        $parameters = $reflection->getParameters();
        $args = [];

        foreach ($parameters as $param) {
            $paramType = $param->getType();
            $paramName = $param->getName();

            if ($paramType instanceof \ReflectionNamedType && $paramType->getName() === Request::class) {
                $args[] = $request;
            } elseif (array_key_exists($paramName, $routeParams)) {
                $args[] = $routeParams[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = $routeParams[$paramName] ?? null;
            }
        }

        return $reflection->invokeArgs($controller, $args);
    }
}
