<?php
/**
 * NetworkWeathermapNG - Admin Controller
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MapService;
use App\Services\GroupService;
use App\Services\DataSourceService;

class AdminController extends BaseController
{
    private MapService $mapService;
    private GroupService $groupService;
    private DataSourceService $dataSourceService;
    
    public function __construct(array $context)
    {
        parent::__construct($context);
        $this->mapService = new MapService($this->database, $this->config);
        $this->groupService = new GroupService($this->database);
        $this->dataSourceService = new DataSourceService($this->database);
    }
    
    public function index(array $params): void
    {
        $this->requireAdmin();
        
        // Dashboard stats
        $stats = [
            'maps' => $this->database->queryOne("SELECT COUNT(*) as count FROM maps")['count'] ?? 0,
            'groups' => $this->database->queryOne("SELECT COUNT(*) as count FROM map_groups")['count'] ?? 0,
            'users' => $this->database->queryOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0,
        ];
        
        $this->render('admin/index', [
            'stats' => $stats,
            'title' => 'Administration',
        ]);
    }
    
    public function maps(array $params): void
    {
        $this->requireAdmin();
        
        $maps = $this->database->query(
            "SELECT m.*, g.name as group_name 
             FROM maps m 
             LEFT JOIN map_groups g ON m.group_id = g.id 
             ORDER BY m.sort_order, m.name"
        );
        
        $groups = $this->groupService->getAllGroups();
        
        $this->render('admin/maps', [
            'maps' => $maps,
            'groups' => $groups,
            'title' => 'Manage Maps',
        ]);
    }
    
    /**
     * Create a new map
     */
    public function createMap(array $params): void
    {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $groupId = (int) ($_POST['group_id'] ?? 1);
            
            if (empty($name)) {
                $this->flash('error', 'Map name is required');
                $this->redirect('/admin/maps');
                return;
            }
            
            try {
                $mapId = $this->mapService->createMap([
                    'name' => $name,
                    'group_id' => $groupId,
                    'active' => 1
                ]);
                
                $this->flash('success', "Map '{$name}' created successfully");
                $this->redirect('/editor/edit/' . $mapId);
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to create map: ' . $e->getMessage());
                $this->redirect('/admin/maps');
            }
            return;
        }
        
        // Show create form
        $groups = $this->groupService->getAllGroups();
        
        $this->render('admin/map_form', [
            'map' => null,
            'groups' => $groups,
            'title' => 'Create New Map',
            'action' => 'create'
        ]);
    }
    
    /**
     * Edit an existing map
     */
    public function editMap(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $map = $this->mapService->getMap($id);
        
        if (!$map) {
            $this->flash('error', 'Map not found');
            $this->redirect('/admin/maps');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $groupId = (int) ($_POST['group_id'] ?? 1);
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name)) {
                $this->flash('error', 'Map name is required');
                $this->redirect('/admin/maps/edit/' . $id);
                return;
            }
            
            try {
                $this->mapService->updateMap($id, [
                    'name' => $name,
                    'group_id' => $groupId,
                    'active' => $active
                ]);
                
                $this->flash('success', "Map '{$name}' updated successfully");
                $this->redirect('/admin/maps');
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to update map: ' . $e->getMessage());
                $this->redirect('/admin/maps/edit/' . $id);
            }
            return;
        }
        
        $groups = $this->groupService->getAllGroups();
        
        $this->render('admin/map_form', [
            'map' => $map,
            'groups' => $groups,
            'title' => 'Edit Map: ' . $map['name'],
            'action' => 'edit'
        ]);
    }
    
    /**
     * Delete a map
     */
    public function deleteMap(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $map = $this->mapService->getMap($id);
        
        if (!$map) {
            $this->flash('error', 'Map not found');
            $this->redirect('/admin/maps');
            return;
        }
        
        try {
            $this->mapService->deleteMap($id);
            $this->flash('success', "Map '{$map['name']}' deleted successfully");
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to delete map: ' . $e->getMessage());
        }
        
        $this->redirect('/admin/maps');
    }
    
    /**
     * Duplicate a map
     */
    public function duplicateMap(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $map = $this->mapService->getMap($id);
        
        if (!$map) {
            $this->flash('error', 'Map not found');
            $this->redirect('/admin/maps');
            return;
        }
        
        try {
            $newId = $this->mapService->duplicateMap($id);
            $this->flash('success', "Map duplicated successfully");
            $this->redirect('/editor/edit/' . $newId);
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to duplicate map: ' . $e->getMessage());
            $this->redirect('/admin/maps');
        }
    }
    
    public function groups(array $params): void
    {
        $this->requireAdmin();
        
        $groups = $this->groupService->getGroupsWithMapCount();
        
        $this->render('admin/groups', [
            'groups' => $groups,
            'title' => 'Manage Groups',
        ]);
    }
    
    /**
     * Create a new group
     */
    public function createGroup(array $params): void
    {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name)) {
                $this->flash('error', 'Group name is required');
                $this->redirect('/admin/groups');
                return;
            }
            
            try {
                $this->groupService->createGroup(['name' => $name]);
                $this->flash('success', "Group '{$name}' created successfully");
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to create group: ' . $e->getMessage());
            }
            
            $this->redirect('/admin/groups');
            return;
        }
        
        $this->render('admin/group_form', [
            'group' => null,
            'title' => 'Create New Group',
            'action' => 'create'
        ]);
    }
    
    /**
     * Edit a group
     */
    public function editGroup(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $group = $this->groupService->getGroup($id);
        
        if (!$group) {
            $this->flash('error', 'Group not found');
            $this->redirect('/admin/groups');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name)) {
                $this->flash('error', 'Group name is required');
                $this->redirect('/admin/groups/edit/' . $id);
                return;
            }
            
            try {
                $this->groupService->updateGroup($id, ['name' => $name]);
                $this->flash('success', "Group '{$name}' updated successfully");
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to update group: ' . $e->getMessage());
            }
            
            $this->redirect('/admin/groups');
            return;
        }
        
        $this->render('admin/group_form', [
            'group' => $group,
            'title' => 'Edit Group: ' . $group['name'],
            'action' => 'edit'
        ]);
    }
    
    /**
     * Delete a group
     */
    public function deleteGroup(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        
        if ($id === 1) {
            $this->flash('error', 'Cannot delete the default group');
            $this->redirect('/admin/groups');
            return;
        }
        
        $group = $this->groupService->getGroup($id);
        
        if (!$group) {
            $this->flash('error', 'Group not found');
            $this->redirect('/admin/groups');
            return;
        }
        
        try {
            $this->groupService->deleteGroup($id);
            $this->flash('success', "Group '{$group['name']}' deleted successfully");
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to delete group: ' . $e->getMessage());
        }
        
        $this->redirect('/admin/groups');
    }
    
    public function users(array $params): void
    {
        $this->requireAdmin();
        
        $users = $this->database->query(
            "SELECT id, username, email, role, active, created_at FROM users ORDER BY username"
        );
        
        $this->render('admin/users', [
            'users' => $users,
            'title' => 'Manage Users',
        ]);
    }

    public function createUser(array $params): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $role = (string) ($_POST['role'] ?? 'viewer');
            $active = isset($_POST['active']) ? 1 : 0;
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($username === '') {
                $this->flash('error', 'Username is required');
                $this->redirect('/admin/users/create');
                return;
            }

            if (!in_array($role, ['admin', 'viewer'], true)) {
                $this->flash('error', 'Invalid role');
                $this->redirect('/admin/users/create');
                return;
            }

            if ($password === '' || $confirmPassword === '') {
                $this->flash('error', 'Password and confirmation are required');
                $this->redirect('/admin/users/create');
                return;
            }

            if ($password !== $confirmPassword) {
                $this->flash('error', 'Password and confirmation do not match');
                $this->redirect('/admin/users/create');
                return;
            }

            if (strlen($password) < 8) {
                $this->flash('error', 'Password must be at least 8 characters');
                $this->redirect('/admin/users/create');
                return;
            }

            $existing = $this->database->queryOne('SELECT id FROM users WHERE username = ?', [$username]);
            if ($existing) {
                $this->flash('error', 'Username already exists');
                $this->redirect('/admin/users/create');
                return;
            }

            try {
                $this->database->insert('users', [
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'email' => $email !== '' ? $email : null,
                    'role' => $role,
                    'active' => $active,
                ]);

                $this->flash('success', "User '{$username}' created successfully");
                $this->redirect('/admin/users');
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to create user: ' . $e->getMessage());
                $this->redirect('/admin/users/create');
            }
            return;
        }

        $this->render('admin/user_form', [
            'userData' => null,
            'title' => 'Add User',
            'action' => 'create',
        ]);
    }

    public function editUser(array $params): void
    {
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        $userData = $this->database->queryOne(
            'SELECT id, username, email, role, active, created_at FROM users WHERE id = ?',
            [$id]
        );

        if (!$userData) {
            $this->flash('error', 'User not found');
            $this->redirect('/admin/users');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $role = (string) ($_POST['role'] ?? $userData['role']);
            $active = isset($_POST['active']) ? 1 : 0;
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!in_array($role, ['admin', 'viewer'], true)) {
                $this->flash('error', 'Invalid role');
                $this->redirect('/admin/users/edit/' . $id);
                return;
            }

            if ($userData['username'] === 'admin') {
                $role = 'admin';
                $active = 1;
            }

            if (($password !== '') || ($confirmPassword !== '')) {
                if ($password === '' || $confirmPassword === '') {
                    $this->flash('error', 'Password and confirmation are required');
                    $this->redirect('/admin/users/edit/' . $id);
                    return;
                }

                if ($password !== $confirmPassword) {
                    $this->flash('error', 'Password and confirmation do not match');
                    $this->redirect('/admin/users/edit/' . $id);
                    return;
                }

                if (strlen($password) < 8) {
                    $this->flash('error', 'Password must be at least 8 characters');
                    $this->redirect('/admin/users/edit/' . $id);
                    return;
                }
            }

            $updateData = [
                'email' => $email !== '' ? $email : null,
                'role' => $role,
                'active' => $active,
            ];

            if ($password !== '') {
                $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            try {
                $this->database->update('users', $updateData, 'id = ?', [$id]);
                $this->flash('success', "User '{$userData['username']}' updated successfully");
                $this->redirect('/admin/users');
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to update user: ' . $e->getMessage());
                $this->redirect('/admin/users/edit/' . $id);
            }
            return;
        }

        $this->render('admin/user_form', [
            'userData' => $userData,
            'title' => 'Edit User: ' . $userData['username'],
            'action' => 'edit',
        ]);
    }

    public function deleteUser(array $params): void
    {
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        $userData = $this->database->queryOne('SELECT id, username FROM users WHERE id = ?', [$id]);

        if (!$userData) {
            $this->flash('error', 'User not found');
            $this->redirect('/admin/users');
            return;
        }

        if ($userData['username'] === 'admin') {
            $this->flash('error', 'Cannot delete the admin user');
            $this->redirect('/admin/users');
            return;
        }

        try {
            $this->database->delete('user_map_permissions', 'user_id = ?', [$id]);
            $this->database->delete('users', 'id = ?', [$id]);
            $this->flash('success', "User '{$userData['username']}' deleted successfully");
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to delete user: ' . $e->getMessage());
        }

        $this->redirect('/admin/users');
    }
    
    public function settings(array $params): void
    {
        $this->requireAdmin();
        
        $settings = $this->database->query("SELECT * FROM settings WHERE map_id = 0");
        
        $this->render('admin/settings', [
            'settings' => $settings,
            'title' => 'Settings',
        ]);
    }
    
    // Data Sources Management
    
    public function dataSources(array $params): void
    {
        $this->requireAdmin();
        
        $sources = $this->dataSourceService->getAllSources();
        
        $this->render('admin/data_sources', [
            'sources' => $sources,
            'title' => 'Data Sources',
        ]);
    }
    
    public function createDataSource(array $params): void
    {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'zabbix';
            $url = trim($_POST['url'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $apiToken = trim($_POST['api_token'] ?? '');
            
            if (empty($name) || empty($url)) {
                $this->flash('error', 'Name and URL are required');
                $this->redirect('/admin/data-sources/create');
                return;
            }
            
            try {
                $sourceId = $this->dataSourceService->createSource([
                    'name' => $name,
                    'type' => $type,
                    'url' => $url,
                    'username' => $username,
                    'password' => $password,
                    'api_token' => $apiToken,
                    'active' => 1,
                ]);
                
                $this->flash('success', "Data source '{$name}' created successfully");
                $this->redirect('/admin/data-sources');
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to create data source: ' . $e->getMessage());
                $this->redirect('/admin/data-sources/create');
            }
            return;
        }
        
        $this->render('admin/data_source_form', [
            'source' => null,
            'title' => 'Add Data Source',
            'action' => 'create',
            'types' => ['zabbix' => 'Zabbix API'],
        ]);
    }
    
    public function editDataSource(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $source = $this->dataSourceService->getSource($id);
        
        if (!$source) {
            $this->flash('error', 'Data source not found');
            $this->redirect('/admin/data-sources');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? 'zabbix';
            $url = trim($_POST['url'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $apiToken = trim($_POST['api_token'] ?? '');
            $active = isset($_POST['active']) ? 1 : 0;
            
            if (empty($name) || empty($url)) {
                $this->flash('error', 'Name and URL are required');
                $this->redirect('/admin/data-sources/edit/' . $id);
                return;
            }
            
            try {
                $this->dataSourceService->updateSource($id, [
                    'name' => $name,
                    'type' => $type,
                    'url' => $url,
                    'username' => $username,
                    'password' => $password,
                    'api_token' => $apiToken,
                    'active' => $active,
                ]);
                
                $this->flash('success', "Data source '{$name}' updated successfully");
                $this->redirect('/admin/data-sources');
            } catch (\Exception $e) {
                $this->flash('error', 'Failed to update data source: ' . $e->getMessage());
                $this->redirect('/admin/data-sources/edit/' . $id);
            }
            return;
        }
        
        $this->render('admin/data_source_form', [
            'source' => $source,
            'title' => 'Edit Data Source: ' . $source['name'],
            'action' => 'edit',
            'types' => ['zabbix' => 'Zabbix API'],
        ]);
    }
    
    public function deleteDataSource(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        $source = $this->dataSourceService->getSource($id);
        
        if (!$source) {
            $this->flash('error', 'Data source not found');
            $this->redirect('/admin/data-sources');
            return;
        }
        
        try {
            $this->dataSourceService->deleteSource($id);
            $this->flash('success', "Data source '{$source['name']}' deleted successfully");
        } catch (\Exception $e) {
            $this->flash('error', 'Failed to delete data source: ' . $e->getMessage());
        }
        
        $this->redirect('/admin/data-sources');
    }
    
    public function testDataSource(array $params): void
    {
        $this->requireAdmin();
        
        $id = (int) ($params['id'] ?? 0);
        
        $result = $this->dataSourceService->testConnection($id);
        
        $this->json($result);
    }
}
