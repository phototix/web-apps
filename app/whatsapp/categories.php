<?php

declare(strict_types=1);

// WhatsApp Categories Management

function app_whatsapp_create_category(int $userId, string $name, ?string $description = null, ?string $color = null, ?int $parentId = null): int {
    $pdo = app_db();
    
    // Validate parent category belongs to same user
    if ($parentId) {
        $parent = app_whatsapp_get_category($parentId);
        if (!$parent || $parent['user_id'] !== $userId) {
            throw new Exception('Parent category not found or access denied');
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO categories 
        (user_id, name, description, color, parent_id, created_at)
        VALUES (:user_id, :name, :description, :color, :parent_id, NOW())
    ");
    
    $stmt->execute([
        'user_id' => $userId,
        'name' => $name,
        'description' => $description,
        'color' => $color ?? '#6c757d',
        'parent_id' => $parentId
    ]);
    
    return (int) $pdo->lastInsertId();
}

function app_whatsapp_update_category(int $categoryId, int $userId, array $updates): bool {
    $category = app_whatsapp_get_category($categoryId);
    if (!$category || $category['user_id'] !== $userId) {
        throw new Exception('Category not found or access denied');
    }
    
    // Validate parent category if changing
    if (isset($updates['parent_id'])) {
        $newParentId = $updates['parent_id'];
        if ($newParentId) {
            $parent = app_whatsapp_get_category($newParentId);
            if (!$parent || $parent['user_id'] !== $userId) {
                throw new Exception('Parent category not found or access denied');
            }
            
            // Prevent circular reference (category cannot be parent of itself or its ancestors)
            if ($newParentId == $categoryId) {
                throw new Exception('Category cannot be its own parent');
            }
            
            // Check for circular reference (would need recursive check in production)
            // For simplicity, we'll just prevent direct self-reference
        }
    }
    
    $pdo = app_db();
    $setClauses = [];
    $params = ['id' => $categoryId, 'user_id' => $userId];
    
    if (isset($updates['name'])) {
        $setClauses[] = 'name = :name';
        $params['name'] = $updates['name'];
    }
    
    if (isset($updates['description'])) {
        $setClauses[] = 'description = :description';
        $params['description'] = $updates['description'];
    }
    
    if (isset($updates['color'])) {
        $setClauses[] = 'color = :color';
        $params['color'] = $updates['color'];
    }
    
    if (isset($updates['parent_id'])) {
        $setClauses[] = 'parent_id = :parent_id';
        $params['parent_id'] = $updates['parent_id'];
    }
    
    if (isset($updates['sort_order'])) {
        $setClauses[] = 'sort_order = :sort_order';
        $params['sort_order'] = $updates['sort_order'];
    }
    
    if (isset($updates['is_active'])) {
        $setClauses[] = 'is_active = :is_active';
        $params['is_active'] = $updates['is_active'];
    }
    
    if (empty($setClauses)) {
        return false;
    }
    
    $query = "UPDATE categories SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($query);
    return $stmt->execute($params);
}

function app_whatsapp_delete_category(int $categoryId, int $userId): bool {
    $category = app_whatsapp_get_category($categoryId);
    if (!$category || $category['user_id'] !== $userId) {
        throw new Exception('Category not found or access denied');
    }
    
    $pdo = app_db();
    
    // Check if category has subcategories
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = :category_id");
    $stmt->execute(['category_id' => $categoryId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        throw new Exception('Cannot delete category that has subcategories. Delete subcategories first.');
    }
    
    // Check if category is used by any messages
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM group_messages WHERE category_id = :category_id");
    $stmt->execute(['category_id' => $categoryId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        throw new Exception('Cannot delete category that is assigned to messages. Remove category from messages first.');
    }
    
    // Check if category is used by any groups
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM whatsapp_groups WHERE category_id = :category_id");
    $stmt->execute(['category_id' => $categoryId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        throw new Exception('Cannot delete category that is assigned to groups. Remove category from groups first.');
    }
    
    // Delete the category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id AND user_id = :user_id");
    return $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);
}

function app_whatsapp_get_category(int $categoryId): ?array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT c.*, 
               p.name as parent_name,
               (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count,
               (SELECT COUNT(*) FROM group_messages WHERE category_id = c.id) as message_count,
               (SELECT COUNT(*) FROM whatsapp_groups WHERE category_id = c.id) as group_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $categoryId]);
    return $stmt->fetch() ?: null;
}

function app_whatsapp_get_user_categories(int $userId, ?int $parentId = null, bool $all = false): array {
    $pdo = app_db();
    
    $query = "
        SELECT c.*, 
               p.name as parent_name,
               (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count,
               (SELECT COUNT(*) FROM group_messages WHERE category_id = c.id) as message_count,
               (SELECT COUNT(*) FROM whatsapp_groups WHERE category_id = c.id) as group_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.user_id = :user_id AND c.is_active = TRUE
    ";
    
    $params = ['user_id' => $userId];
    
    if (!$all) {
        if ($parentId === null) {
            // Get root categories (parent_id IS NULL)
            $query .= " AND c.parent_id IS NULL";
        } else {
            $query .= " AND c.parent_id = :parent_id";
            $params['parent_id'] = $parentId;
        }
    }
    
    $query .= " ORDER BY c.sort_order ASC, c.name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function app_whatsapp_get_category_tree(int $userId): array {
    $pdo = app_db();
    
    // Get all categories for user
    $stmt = $pdo->prepare("
        SELECT c.*, 
               p.name as parent_name,
               (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as subcategory_count,
               (SELECT COUNT(*) FROM group_messages WHERE category_id = c.id) as message_count,
               (SELECT COUNT(*) FROM whatsapp_groups WHERE category_id = c.id) as group_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.user_id = :user_id AND c.is_active = TRUE
        ORDER BY c.parent_id IS NULL DESC, c.sort_order ASC, c.name ASC
    ");
    $stmt->execute(['user_id' => $userId]);
    $categories = $stmt->fetchAll();
    
    // Build tree structure
    $tree = [];
    $indexed = [];
    
    // First pass: index all categories
    foreach ($categories as $category) {
        $category['subcategories'] = [];
        $indexed[$category['id']] = $category;
    }
    
    // Second pass: build tree
    foreach ($indexed as $id => $category) {
        $parentId = $category['parent_id'];
        
        if ($parentId && isset($indexed[$parentId])) {
            $indexed[$parentId]['subcategories'][] = &$indexed[$id];
        } else {
            $tree[] = &$indexed[$id];
        }
    }
    
    return $tree;
}

function app_whatsapp_assign_message_category(int $messageId, ?int $categoryId, int $userId): bool {
    $pdo = app_db();
    
    // Verify message exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT gm.id 
        FROM group_messages gm
        JOIN whatsapp_groups wg ON gm.session_id = wg.session_id AND gm.group_id = wg.group_id
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        WHERE gm.id = :message_id AND ws.user_id = :user_id
    ");
    $stmt->execute(['message_id' => $messageId, 'user_id' => $userId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Message not found or access denied');
    }
    
    // Verify category belongs to user if provided
    if ($categoryId) {
        $category = app_whatsapp_get_category($categoryId);
        if (!$category || $category['user_id'] !== $userId) {
            throw new Exception('Category not found or access denied');
        }
    }
    
    // Update message category
    $stmt = $pdo->prepare("
        UPDATE group_messages 
        SET category_id = :category_id
        WHERE id = :message_id
    ");
    
    return $stmt->execute([
        'message_id' => $messageId,
        'category_id' => $categoryId
    ]);
}

function app_whatsapp_assign_group_category(int $groupId, ?int $categoryId, int $userId): bool {
    $pdo = app_db();
    
    // Verify group exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT wg.id 
        FROM whatsapp_groups wg
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        WHERE wg.id = :group_id AND ws.user_id = :user_id
    ");
    $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Group not found or access denied');
    }
    
    // Verify category belongs to user if provided
    if ($categoryId) {
        $category = app_whatsapp_get_category($categoryId);
        if (!$category || $category['user_id'] !== $userId) {
            throw new Exception('Category not found or access denied');
        }
    }
    
    // Update group category
    $stmt = $pdo->prepare("
        UPDATE whatsapp_groups 
        SET category_id = :category_id
        WHERE id = :group_id
    ");
    
    return $stmt->execute([
        'group_id' => $groupId,
        'category_id' => $categoryId
    ]);
}

function app_whatsapp_get_messages_by_category(int $userId, int $categoryId, int $limit = 50, ?int $beforeTimestamp = null): array {
    $pdo = app_db();
    
    $query = "
        SELECT gm.*, 
               wg.name as group_name,
               ws.session_name
        FROM group_messages gm
        JOIN whatsapp_groups wg ON gm.session_id = wg.session_id AND gm.group_id = wg.group_id
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        WHERE ws.user_id = :user_id AND gm.category_id = :category_id
    ";
    
    $params = ['user_id' => $userId, 'category_id' => $categoryId];
    
    if ($beforeTimestamp) {
        $query .= " AND gm.timestamp < :before_timestamp";
        $params['before_timestamp'] = $beforeTimestamp;
    }
    
    $query .= " ORDER BY gm.timestamp DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue('category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    if ($beforeTimestamp) {
        $stmt->bindValue('before_timestamp', $beforeTimestamp, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_whatsapp_get_groups_by_category(int $userId, int $categoryId): array {
    $pdo = app_db();
    
    $stmt = $pdo->prepare("
        SELECT wg.*, ws.session_name as whatsapp_session_name
        FROM whatsapp_groups wg
        JOIN whatsapp_sessions ws ON wg.session_id = ws.id
        WHERE ws.user_id = :user_id AND wg.category_id = :category_id
        ORDER BY 
            CASE WHEN wg.last_message_timestamp IS NULL THEN 1 ELSE 0 END,
            wg.last_message_timestamp DESC,
            wg.updated_at DESC
    ");
    
    $stmt->execute(['user_id' => $userId, 'category_id' => $categoryId]);
    return $stmt->fetchAll();
}