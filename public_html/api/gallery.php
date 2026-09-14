<?php
/**
 * GET /api/gallery.php
 * Filesystem auto-gallery — NO database, NO config required.
 *
 * Scans uploads/gallery/ (and uploads/jsd_gallery/ as a fallback root) and
 * returns a grouped structure the frontend renders:
 *
 *   featured   -> home "Featured Projects" (images only)
 *   projects   -> Projects page, grouped into collections (filter buttons)
 *   buildTypes -> Projects page "Build Types" boxes (6 AI images w/ labels)
 *   video      -> home cinematic video (first .mp4 found in the video folder)
 *
 * Folder names are matched case/space/dash-insensitively via aliases, so the
 * owner can keep their original Mac folder names OR use the clean slugs.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$publicRoot = dirname(__DIR__);
$ROOTS = [
    ['fs' => $publicRoot . '/uploads/gallery',     'url' => 'uploads/gallery'],
    ['fs' => $publicRoot . '/uploads/jsd_gallery',  'url' => 'uploads/jsd_gallery'],
];

$IMG = ['jpg', 'jpeg', 'png', 'webp'];
$VID = ['mp4', 'webm', 'mov'];

// collection key => [label, type, aliases...]
$COLLECTIONS = [
    'featured'   => ['label' => 'Featured',                 'type' => 'featured',   'aliases' => ['featured', 'featured photos', 'featured-photos']],
    'evergreen'  => ['label' => 'Evergreen St, Rochedale',  'type' => 'project',    'aliases' => ['evergreen', 'evergreen st rochedale', 'evergreen-st-rochedale', 'evergreen st, rochedale']],
    'kenmore'    => ['label' => 'Kenmore',                  'type' => 'project',    'aliases' => ['kenmore']],
    'sunnydale'  => ['label' => 'Sunnydale',               'type' => 'project',    'aliases' => ['sunnydale', 'sunnydale project', 'sunnydale-project', 'sunnydale street']],
    'active'     => ['label' => 'Active Projects',          'type' => 'project',    'aliases' => ['active', 'active projects', 'active-projects', 'ongoing projects', 'ongoing-projects', 'ongoing']],
    'buildtypes' => ['label' => 'Build Types',             'type' => 'buildtypes', 'aliases' => ['buildtypes', 'build-types', 'build types', 'ai', 'ai images', 'ai-images']],
    'video'      => ['label' => 'Video',                   'type' => 'video',      'aliases' => ['video', 'videos']],
];

// AI image number (1..6) => fixed build-type label (exactly as specified)
$BUILD_TYPES = [
    1 => ['category' => 'High-Set',    'location' => 'Rochedale, QLD',         'description' => 'Elevated family home maximising airflow and under-house living on a sloping block.'],
    2 => ['category' => 'Low-Set',     'location' => 'Springwood, QLD',        'description' => 'Open-plan single-level build with strong street presence on a flat allotment.'],
    3 => ['category' => 'Split-Level', 'location' => 'Eight Mile Plains, QLD', 'description' => 'Split-level design following the natural terrain with tiered outdoor living.'],
    4 => ['category' => 'Commercial',  'location' => 'Underwood, QLD',         'description' => 'Low-rise retail and mixed-use space delivered end-to-end under QLD low-rise licence.'],
    5 => ['category' => 'High-Set',    'location' => 'Calamvale, QLD',         'description' => 'Contemporary high-set with premium timber framing and energy-efficient design.'],
    6 => ['category' => 'Commercial',  'location' => 'Ipswich, QLD',           'description' => 'Low-rise commercial project with full in-house project management.'],
];

// build alias -> key lookup
$ALIAS = [];
foreach ($COLLECTIONS as $key => $c) {
    foreach ($c['aliases'] as $a) $ALIAS[$a] = $key;
}

function firstNumber(string $file): int
{
    $name = pathinfo($file, PATHINFO_FILENAME);
    if (preg_match('/(\d+)/', $name, $m)) return (int) $m[1];
    return 999;
}

$featured   = [];
$projects   = [];   // flat list, each has collection + collectionLabel
$collSet    = [];   // which project collections actually have images
$buildTypes = [];
$video      = null;

foreach ($ROOTS as $root) {
    if (!is_dir($root['fs'])) continue;

    foreach (scandir($root['fs']) as $dir) {
        if ($dir[0] === '.' ) continue;
        $dirFs = $root['fs'] . '/' . $dir;
        if (!is_dir($dirFs)) continue;

        $key = $ALIAS[strtolower(trim($dir))] ?? null;
        if ($key === null) continue;
        $type = $COLLECTIONS[$key]['type'];

        $files = scandir($dirFs);
        natcasesort($files);
        foreach ($files as $file) {
            if ($file[0] === '.') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $url = $root['url'] . '/' . rawurlencode($dir) . '/' . rawurlencode($file);
            $n   = firstNumber($file);

            if ($type === 'video') {
                if (in_array($ext, $VID, true) && $video === null) $video = $url;
                continue;
            }
            if (!in_array($ext, $IMG, true)) continue;

            if ($type === 'featured') {
                $featured[] = ['image' => $url, 'sort' => $n];
            } elseif ($type === 'buildtypes') {
                $meta = $BUILD_TYPES[$n] ?? $BUILD_TYPES[(count($buildTypes) % 6) + 1];
                $buildTypes[] = [
                    'image' => $url, 'sort' => $n,
                    'category' => $meta['category'], 'location' => $meta['location'], 'description' => $meta['description'],
                ];
            } elseif ($type === 'project') {
                $projects[] = [
                    'collection' => $key,
                    'collectionLabel' => $COLLECTIONS[$key]['label'],
                    'image' => $url, 'sort' => $n,
                ];
                $collSet[$key] = true;
            }
        }
    }
}

$bySort = fn($a, $b) => $a['sort'] <=> $b['sort'];
usort($featured, $bySort);
usort($buildTypes, $bySort);
usort($projects, function ($a, $b) {
    return [$a['collection'], $a['sort']] <=> [$b['collection'], $b['sort']];
});

// ordered list of non-empty project collections (for filter buttons)
$collections = [];
foreach ($COLLECTIONS as $key => $c) {
    if ($c['type'] === 'project' && !empty($collSet[$key])) {
        $collections[] = ['key' => $key, 'label' => $c['label']];
    }
}

echo json_encode([
    'ok'         => true,
    'featured'   => $featured,
    'projects'   => ['collections' => $collections, 'items' => $projects],
    'buildTypes' => $buildTypes,
    'video'      => $video,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
