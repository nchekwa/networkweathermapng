<?php
/**
 * NetworkWeathermapNG - Application Bootstrap
 */

declare(strict_types=1);

namespace App\Core;

class Application
{
    private Config $config;
    private Router $router;
    private ?Database $database = null;
    
    public function __construct()
    {
        $this->config = new Config();
        $this->router = new Router();
        
        $this->initialize();
    }
    
    private function initialize(): void
    {
        // Set timezone
        date_default_timezone_set($this->config->get('APP_TIMEZONE', 'UTC'));
        
        // Set error reporting based on debug mode
        if ($this->config->get('APP_DEBUG', 'false') === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        }
        
        // Initialize database
        $this->database = new Database($this->config);
        
        // Register routes
        $this->registerRoutes();
    }
    
    private function registerRoutes(): void
    {
        // Health check
        $this->router->get('/health', function() {
            header('Content-Type: text/plain');
            echo 'OK';
        });
        
        // Home / Map list
        $this->router->get('/', 'MapController@index');
        $this->router->get('/maps', 'MapController@index');
        
        // View single map
        $this->router->get('/map/{id}', 'MapController@view');
        $this->router->get('/map/{id}/image', 'MapController@image');
        $this->router->get('/map/{id}/thumb', 'MapController@thumbnail');
        $this->router->get('/map/{id}/link/{linkId}/graph', 'MapController@linkGraph');
        
        // Map cycling
        $this->router->get('/cycle', 'MapController@cycle');
        $this->router->get('/cycle/{group}', 'MapController@cycle');
        
        // Editor - Visual Map Editor
        $this->router->get('/editor', 'EditorController@index');
        $this->router->get('/editor/edit/{id}', 'EditorController@edit');
        $this->router->get('/editor/draw/{id}', 'EditorController@draw');
        $this->router->get('/editor/area/{id}', 'EditorController@getAreaData');
        $this->router->get('/editor/mapjs/{id}', 'EditorController@getMapJS');
        $this->router->get('/editor/mapdata/{id}', 'EditorController@getMapData');
        $this->router->get('/editor/load_area_data', 'EditorController@loadAreaData');
        $this->router->get('/editor/load_map_javascript', 'EditorController@loadMapJavascript');
        $this->router->get('/editor/config/{id}', 'EditorController@config');
        $this->router->post('/editor/action/{id}', 'EditorController@action');
        $this->router->post('/editor/save/{id}', 'EditorController@save');
        $this->router->get('/editor/preview/{id}', 'EditorController@preview');
        
        // API endpoints
        $this->router->get('/api/maps', 'ApiController@maps');
        $this->router->get('/api/map/{id}', 'ApiController@map');
        $this->router->get('/api/data-sources', 'ApiController@dataSources');
        $this->router->get('/api/data-sources/{sourceId}/hosts', 'ApiController@dataSourceHosts');
        $this->router->get('/api/data-sources/{sourceId}/hosts/{hostId}/interfaces', 'ApiController@dataSourceInterfaces');
        $this->router->get('/api/data-sources/{sourceId}/hosts/{hostId}/items', 'ApiController@dataSourceItems');
        $this->router->get('/api/data-sources/{sourceId}/hosts/{hostId}/bandwidths', 'ApiController@dataSourceBandwidths');
        $this->router->get('/api/data-sources/{sourceId}/link-bandwidth', 'ApiController@dataSourceLinkBandwidth');
        
        // Authentication
        $this->router->get('/login', 'AuthController@loginForm');
        $this->router->post('/login', 'AuthController@login');
        $this->router->get('/logout', 'AuthController@logout');

        // Account
        $this->router->get('/account/password', 'AccountController@changePasswordForm');
        $this->router->post('/account/password', 'AccountController@changePassword');
        
        // Admin
        $this->router->get('/admin', 'AdminController@index');
        $this->router->get('/admin/maps', 'AdminController@maps');
        $this->router->get('/admin/maps/create', 'AdminController@createMap');
        $this->router->post('/admin/maps/create', 'AdminController@createMap');
        $this->router->get('/admin/maps/edit/{id}', 'AdminController@editMap');
        $this->router->post('/admin/maps/edit/{id}', 'AdminController@editMap');
        $this->router->get('/admin/maps/delete/{id}', 'AdminController@deleteMap');
        $this->router->get('/admin/maps/duplicate/{id}', 'AdminController@duplicateMap');
        
        $this->router->get('/admin/groups', 'AdminController@groups');
        $this->router->get('/admin/groups/create', 'AdminController@createGroup');
        $this->router->post('/admin/groups/create', 'AdminController@createGroup');
        $this->router->get('/admin/groups/edit/{id}', 'AdminController@editGroup');
        $this->router->post('/admin/groups/edit/{id}', 'AdminController@editGroup');
        $this->router->get('/admin/groups/delete/{id}', 'AdminController@deleteGroup');
        
        $this->router->get('/admin/users', 'AdminController@users');
        $this->router->get('/admin/users/create', 'AdminController@createUser');
        $this->router->post('/admin/users/create', 'AdminController@createUser');
        $this->router->get('/admin/users/edit/{id}', 'AdminController@editUser');
        $this->router->post('/admin/users/edit/{id}', 'AdminController@editUser');
        $this->router->get('/admin/users/delete/{id}', 'AdminController@deleteUser');
        $this->router->get('/admin/settings', 'AdminController@settings');
        
        // Data Sources
        $this->router->get('/admin/data-sources', 'AdminController@dataSources');
        $this->router->get('/admin/data-sources/create', 'AdminController@createDataSource');
        $this->router->post('/admin/data-sources/create', 'AdminController@createDataSource');
        $this->router->get('/admin/data-sources/edit/{id}', 'AdminController@editDataSource');
        $this->router->post('/admin/data-sources/edit/{id}', 'AdminController@editDataSource');
        $this->router->get('/admin/data-sources/delete/{id}', 'AdminController@deleteDataSource');
        $this->router->get('/admin/data-sources/test/{id}', 'AdminController@testDataSource');
    }
    
    public function run(): void
    {
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get request info
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Dispatch the request
        $this->router->dispatch($method, $uri, [
            'config' => $this->config,
            'database' => $this->database,
        ]);
    }
    
    public function getConfig(): Config
    {
        return $this->config;
    }
    
    public function getDatabase(): Database
    {
        return $this->database;
    }
}
