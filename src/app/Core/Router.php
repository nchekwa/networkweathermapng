<?php
/**
 * Zabbix Weathermap - Simple Router
 */

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    
    public function get(string $path, mixed $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, mixed $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put(string $path, mixed $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete(string $path, mixed $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute(string $method, string $path, mixed $handler): void
    {
        // Convert path parameters to regex
        $pattern = preg_replace('/\{([a-zA-Z]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }
    
    public function dispatch(string $method, string $uri, array $context = []): void
    {
        // Remove trailing slash
        $uri = rtrim($uri, '/') ?: '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                $this->executeHandler($route['handler'], $params, $context);
                return;
            }
        }
        
        // No route found
        $this->notFound();
    }
    
    private function executeHandler(mixed $handler, array $params, array $context): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params, $context);
            return;
        }
        
        if (is_string($handler) && str_contains($handler, '@')) {
            [$controllerName, $method] = explode('@', $handler);
            
            $controllerClass = "App\\Controllers\\{$controllerName}";
            
            if (!class_exists($controllerClass)) {
                $this->serverError("Controller not found: {$controllerClass}");
                return;
            }
            
            $controller = new $controllerClass($context);
            
            if (!method_exists($controller, $method)) {
                $this->serverError("Method not found: {$controllerClass}::{$method}");
                return;
            }
            
            call_user_func([$controller, $method], $params);
            return;
        }
        
        $this->serverError("Invalid handler");
    }
    
    private function notFound(): void
    {
        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
        echo '<p>The requested page was not found.</p>';
    }
    
    private function serverError(string $message): void
    {
        http_response_code(500);
        echo '<h1>500 Internal Server Error</h1>';
        if (getenv('APP_DEBUG') === 'true') {
            echo '<p>' . htmlspecialchars($message) . '</p>';
        }
    }
}
