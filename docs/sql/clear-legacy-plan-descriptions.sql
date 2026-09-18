-- Optional MySQL/MariaDB data cleanup; no schema change is required.
-- Pricing cards and detail pages no longer render product descriptions.
-- Preview the old Tokyo-template descriptions before clearing them.
SELECT id, name, description
FROM products
WHERE description LIKE '日本东京节点%套餐%';

UPDATE products
SET description = NULL, updated_at = CURRENT_TIMESTAMP
WHERE description LIKE '日本东京节点%套餐%';
