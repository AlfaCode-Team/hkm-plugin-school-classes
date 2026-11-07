<?php
declare(strict_types=1);
/*
 * Re-records tests/fixtures/legacy-class-catalog.json from the LEGACY source.
 * The golden fixture is committed, so this is only needed when the legacy
 * catalog itself changes.
 *
 *   php tools/regenerate-fixture.php [/path/to/ElimuSystem]
 */
$legacyRoot = $argv[1] ?? getenv('ELIMU_LEGACY_ROOT') ?: null;
if ($legacyRoot === null || ! is_file(rtrim($legacyRoot, '/') . '/public/class_catalog.php')) {
    fwrite(STDERR, "ABORT: legacy tree not found (pass its path, or set ELIMU_LEGACY_ROOT)\n");
    exit(1);
}
$L = rtrim($legacyRoot, '/') . '/public';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require $L . '/class_catalog.php';
require $L . '/teacher/period_helpers.php';

$all = elimu_class_order();
$legacySpellings = [
    '1ère année Electricité', '2ème année Elec', '3ème année Mecanique',
    '1ère année Méc. Auto', '2ème année Prof. Auto', '4ème année CONST',
    '1ère année NUTRITION', 'Maternelle PS', '7ème année EB',
];
$edges = ['', '   ', 'Unknown Class', 'primaire', 'MATERNELLE'];

$perClass = [];
foreach (array_merge($all, $legacySpellings, $edges) as $c) {
    $perClass[$c] = [
        'label'   => elimu_class_label($c),
        'next'    => elimu_next_class($c),
        'grading' => isPrimaryLevel($c) ? 'trimester' : 'session',
    ];
}

echo json_encode([
    '_generated' => 'from public/class_catalog.php + teacher/period_helpers.php of the legacy Elimu system',
    '_warning'   => 'GOLDEN FILE. Do not hand-edit. Regenerate only against the legacy source.',
    'order'      => $all,
    'map'        => elimu_class_map(),
    'labels'     => array_column(elimu_class_groups(), 'label'),
    'shorts'     => array_column(elimu_class_groups(), 'short'),
    'patterns'   => array_column(elimu_class_groups(), 'pattern'),
    'perClass'   => $perClass,
    'groupAll'   => elimu_group_classes($all),
    'groupLegacySpellings' => elimu_group_classes($legacySpellings),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
