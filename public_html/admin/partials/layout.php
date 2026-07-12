<?php
/** Shared admin chrome: admin_header('Page Title', 'active-nav') … admin_footer() */

function admin_header(string $title, string $active = ''): void
{
    $items = [
        'dashboard'    => ['dashboard.php',    'Dashboard'],
        'enquiries'    => ['enquiries.php',    'Customer Database'],
        'projects'     => ['projects.php',     'Projects & Photos'],
        'testimonials' => ['testimonials.php', 'Testimonials'],
        'password'     => ['password.php',     'Change Password'],
    ];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<meta name="robots" content="noindex,nofollow">'
       . '<title>' . e($title) . ' — JSD Admin</title>'
       . '<link rel="icon" type="image/svg+xml" href="../assets/img/logos/favicon.svg">'
       . '<link rel="stylesheet" href="style.css"></head><body>'
       . '<div class="admin-shell"><aside class="admin-side">'
       . '<div class="brand">JSD ADMIN<small>CONSTRUCTION</small></div>';
    foreach ($items as $key => [$href, $label]) {
        $cls = $key === $active ? 'nav-item active' : 'nav-item';
        echo "<a class=\"{$cls}\" href=\"{$href}\">{$label}</a>";
    }
    echo '<div class="spacer"></div>'
       . '<a class="nav-item" href="../index.html" target="_blank">View Website ↗</a>'
       . '<a class="nav-item" href="logout.php">Log out</a>'
       . '</aside><main class="admin-main">';
}

function admin_footer(): void
{
    echo '</main></div></body></html>';
}
