<?php
/**
 * GET /api/projects.php
 * Public JSON feed of published projects (used by index.html + projects.html).
 * Images are served from /uploads/projects (admin-cropped to 3:2).
 */

require_once __DIR__ . '/bootstrap.php';

try {
    $rows = db()->query(
        'SELECT p.id, p.title, p.category, p.location, p.description,
                COALESCE(
                    (SELECT pi.file_path FROM project_images pi
                      WHERE pi.project_id = p.id ORDER BY pi.sort_order, pi.id LIMIT 1),
                    p.cover_image
                ) AS image
           FROM projects p
          WHERE p.is_published = 1
          ORDER BY p.sort_order, p.created_at DESC'
    )->fetchAll();

    json_response(['ok' => true, 'projects' => $rows]);
} catch (Throwable $e) {
    error_log('projects.php: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Unable to load projects'], 500);
}
