-- Seed: services
-- Purpose: Insert the 10 approved initial SKSL services
-- Per approved quotation and docs/00_PROJECT_OVERVIEW.md
-- GST fixed at 18% for all V1 services
-- Run AFTER migrations (services table must exist)

INSERT INTO `services`
    (`name`, `slug`, `description`, `price`, `gst_percent`, `duration_minutes`, `capacity`, `image`, `status`)
VALUES
    ('Spa',          'spa',          NULL, 999.00, 18.00, 30, 4, NULL, 'active'),
    ('Sauna',        'sauna',        NULL, 499.00, 18.00, 10, 4, NULL, 'active'),
    ('Steam',        'steam',        NULL, 299.00, 18.00, 10, 8, NULL, 'active'),
    ('Ice Bath',     'ice-bath',     NULL, 449.00, 18.00, 10, 8, NULL, 'active'),
    ('Hot Bath',     'hot-bath',     NULL, 199.00, 18.00, 10, 4, NULL, 'active'),
    ('Endless Pool', 'endless-pool', NULL, 499.00, 18.00, 30, 2, NULL, 'active'),
    ('Cycle',        'cycle',        NULL, 149.00, 18.00, 15, 1, NULL, 'active'),
    ('Treadmill',    'treadmill',    NULL, 149.00, 18.00, 15, 1, NULL, 'active'),
    ('Walker',       'walker',       NULL, 149.00, 18.00, 15, 1, NULL, 'active'),
    ('Lap Pool',     'lap-pool',     NULL, 499.00, 18.00, 45, 1, NULL, 'active');
