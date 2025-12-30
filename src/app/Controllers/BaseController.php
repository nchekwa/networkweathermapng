<?php
/**
 * Zabbix Weathermap - Base Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Auth;

abstract class BaseController
{
    protected Config $config;
    protected Database $database;
    protected Auth $auth;
    
    public function __construct(array $context)
    {
        $this->config = $context['config'];
        $this->database = $context['database'];
        $this->auth = new Auth($this->database, $this->config);
    }
    
    protected function render(string $view, array $data = [], bool $useLayout = true): void
    {
        $viewPath = APP_ROOT . '/app/Views/' . $view . '.php';
        
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        
        // Make data available to view
        extract($data);
        
        // Add common variables
        $auth = $this->auth;
        $config = $this->config;
        $user = $this->auth->user();
        
        // Start output buffering
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        
        // Include layout if not API request and layout is enabled
        if (!$this->isApiRequest() && $useLayout) {
            $layoutPath = APP_ROOT . '/app/Views/layouts/main.php';
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }
    
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
    
    protected function requireAuth(): void
    {
        if (!$this->auth->check()) {
            if ($this->isApiRequest()) {
                $this->json(['error' => 'Unauthorized'], 401);
                exit;
            }
            $this->redirect('/login');
        }
    }
    
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        
        if (!$this->auth->isAdmin()) {
            if ($this->isApiRequest()) {
                $this->json(['error' => 'Forbidden'], 403);
                exit;
            }
            http_response_code(403);
            echo '<h1>403 Forbidden</h1>';
            exit;
        }
    }
    
    protected function isApiRequest(): bool
    {
        return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }
    
    protected function getInput(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
    
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    protected function flash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'][$type] = $message;
    }
    
    protected function getFlash(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
}
