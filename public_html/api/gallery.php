<?php
/**
 * GET /api/gallery.php
 * Filesystem auto-gallery — NO database, NO config required.
 *
 * Scans public_html/uploads/gallery/<category>/*.{jpg,jpeg,png,webp} and
 * returns them as JSON. This is the "drop a folder and you're live" path:
 * the site owner simply organises photos into category subfolders and uploads
 * them via cPanel — this endpoint picks them up automatically.
 *
 * Folder → category mapping (subfolder name, case/space/dash-insensitive):
 *   high-set / highset / high         -> highset
 *   low-set  / lowset  / low          -> lowset
 *   split-level / split / splitlevel  -> split
 *   commercial / commerc' / retail    -> commercial
 *   featured / hero / showcase        -> featured   (used for hero + highlights)
 *
 * Filename → title & order:
 *   "01 Rochedale Family Home.jpg"  -> order 1,  title "Rochedale Family Home"
 *   "03_Calamvale-Hillside.jpg"     -> order 3,  title "Calamvale Hillside"
 *   "Sunnybank Duplex.jpg"          -> order 99, title "Sunnybank Duplex"
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$root      = dirname(__DIR__);                 // public_html
$galleryFs = $root . '/uploads/gallery';       // filesystem path
$galleryUrl = 'uploads/gallery';               // web path (relative to site root)

$CATEGORY_MAP = [
    'highset' => 'highset', 'high-set' => 'highset', 'high_set' => 'highset', 'high' => 'highset',
    'lowset' => 'lowset', 'low-set' => 'lowset', 'low_set' => 'lowset', 'low' => 'lowset',
    'split' => 'split', 'split-level' => 'split', 'split_level' => 'split', 'splitlevel' => 'split',
    'commercial' => 'commercial', 'retail' => 'commercial', 'commercial-projects' => 'commercial',
    'featured' => 'featured', 'hero' => 'featured', 'showcase' => 'featured', 'highlights' => 'featured',
];

$ALLOWED = ['jpg', 'jpeg', 'png', 'webp'];

function titleFromFilename(string $file): array
{
    $name = pathinfo($file, PATHINFO_FILENAME);
    // leading number => sort order
    $order = 99;
    if (preg_match('/^\s*(\d{1,3})\s*[-_.)]*\s*(.*)$/', $name, $m)) {
        $order = (int) $m[1];
        $name = $m[2] !== '' ? $m[2] : $name;
    }
    // tidy separators into spaces
    $title = trim(preg_replace('/[\-_]+/', ' ', $name));
    $title = preg_replace('/\s+/', ' ', $title);
    if ($title === '') $title = 'JSD Project';
    // Title Case-ish (leave existing capitals)
    return [$order, $title];
}

$out = [];

if (is_dir($galleryFs)) {
    foreach (scandir($galleryFs) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $dirPath = $galleryFs . '/' . $dir;
        if (!is_dir($dirPath)) continue;

        $key = strtolower(trim($dir));
        $category = $CATEGORY_MAP[$key] ?? null;
        if ($category === null) continue; // ignore unknown folders

        $files = scandir($dirPath);
        natcasesort($files);
        foreach ($files as $file) {
            if ($file[0] === '.') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $ALLOWED, true)) continue;
            if (!is_file($dirPath . '/' . $file)) continue;

            [$order, $title] = titleFromFilename($file);
            $out[] = [
                'category' => $category,
                'title'    => $title,
                'location' => '',
                'description' => '',
                'image'    => $galleryUrl . '/' . rawurlencode($dir) . '/' . rawurlencode($file),
                'sort'     => $order,
            ];
        }
    }
}

// sort: featured first (they lead the home page), then by category, order, title
$PRIORITY = ['featured' => 0, 'highset' => 1, 'lowset' => 2, 'split' => 3, 'commercial' => 4];
usort($out, function ($a, $b) use ($PRIORITY) {
    $pa = $PRIORITY[$a['category']] ?? 9;
    $pb = $PRIORITY[$b['category']] ?? 9;
    return [$pa, $a['sort'], $a['title']] <=> [$pb, $b['sort'], $b['title']];
});

echo json_encode(['ok' => true, 'count' => count($out), 'projects' => $out],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
