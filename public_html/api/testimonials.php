<?php
/**
 * GET /api/testimonials.php
 * Public JSON feed of approved testimonials (managed in the admin panel).
 */

require_once __DIR__ . '/bootstrap.php';

try {
    $rows = db()->query(
        'SELECT name, project, rating, quote
           FROM testimonials
          WHERE is_published = 1
          ORDER BY sort_order, created_at DESC
          LIMIT 12'
    )->fetchAll();

    json_response(['ok' => true, 'testimonials' => $rows]);
} catch (Throwable $e) {
    error_log('testimonials.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unable to load testimonials'], 500);
}
