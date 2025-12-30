<?php

namespace App\Services;

/**
 * GroupService - Handles map group operations
 */
class GroupService
{
    private $db;
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    /**
     * Get all groups
     */
    public function getAllGroups(): array
    {
        return $this->db->query("SELECT * FROM map_groups ORDER BY sort_order, name");
    }
    
    /**
     * Get a single group by ID
     */
    public function getGroup(int $id): ?array
    {
        return $this->db->queryOne("SELECT * FROM map_groups WHERE id = ?", [$id]);
    }
    
    /**
     * Create a new group
     */
    public function createGroup(array $data): int
    {
        // Get next sort order
        $result = $this->db->queryOne("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM map_groups");
        $sortOrder = $result['next_order'] ?? 1;
        
        return $this->db->insert('map_groups', [
            'name' => $data['name'] ?? 'New Group',
            'sort_order' => $data['sort_order'] ?? $sortOrder
        ]);
    }
    
    /**
     * Update a group
     */
    public function updateGroup(int $id, array $data): bool
    {
        $group = $this->getGroup($id);
        if (!$group) {
            return false;
        }
        
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['sort_order'])) {
            $updateData['sort_order'] = $data['sort_order'];
        }
        
        if (empty($updateData)) {
            return true;
        }
        
        return $this->db->update('map_groups', $updateData, 'id = ?', [$id]) >= 0;
    }
    
    /**
     * Delete a group
     */
    public function deleteGroup(int $id): bool
    {
        // Don't delete the default group (id = 1)
        if ($id === 1) {
            return false;
        }
        
        // Move maps from this group to default group
        $this->db->update('maps', ['group_id' => 1], 'group_id = ?', [$id]);
        
        // Delete the group
        return $this->db->delete('map_groups', 'id = ?', [$id]) > 0;
    }
    
    /**
     * Get groups with map count
     */
    public function getGroupsWithMapCount(): array
    {
        return $this->db->query("
            SELECT g.*, COUNT(m.id) as map_count
            FROM map_groups g
            LEFT JOIN maps m ON g.id = m.group_id
            GROUP BY g.id
            ORDER BY g.sort_order, g.name
        ");
    }
}
