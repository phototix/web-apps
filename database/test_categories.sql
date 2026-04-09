-- Test data for categories
-- Run this after running migration_add_categories.sql

-- Insert test categories for user with id 1
INSERT INTO categories (user_id, name, description, color, parent_id, sort_order) VALUES
(1, 'Sales', 'Sales related messages and groups', '#28a745', NULL, 1),
(1, 'Support', 'Customer support inquiries', '#17a2b8', NULL, 2),
(1, 'Marketing', 'Marketing campaigns and promotions', '#ffc107', NULL, 3),
(1, 'Leads', 'Potential customers', '#6f42c1', 1, 1),
(1, 'Customers', 'Existing customers', '#20c997', 1, 2),
(1, 'Technical', 'Technical support issues', '#dc3545', 2, 1),
(1, 'Billing', 'Billing and payment issues', '#fd7e14', 2, 2);

-- Update some messages to have categories
UPDATE group_messages SET category_id = 1 WHERE id IN (1, 2, 3);
UPDATE group_messages SET category_id = 4 WHERE id IN (4, 5);
UPDATE group_messages SET category_id = 6 WHERE id IN (6, 7);

-- Update some groups to have categories
UPDATE whatsapp_groups SET category_id = 1 WHERE id IN (1, 2);
UPDATE whatsapp_groups SET category_id = 2 WHERE id IN (3, 4);