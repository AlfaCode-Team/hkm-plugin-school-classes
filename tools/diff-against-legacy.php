<?php
declare(strict_types=1);
/* Differential check: the ported ClassCatalog vs the legacy class_catalog.php. */

/*
 * Usage:  php tools/diff-against-legacy.php [/path/to/ElimuSystem]
 * The legacy tree is not public; without it this script SKIPS (exit 0) so it
 * is safe to run in CI. ELIMU_LEGACY_ROOT works in place of the argument.
 */
$legacyRoot = $argv[1] ?? getenv('ELIMU_LEGACY_ROOT') ?: null;
$LEGACY     = $legacyRoot !== null ? rtrim($legacyRoot, '/') . '/public' : null;
$PLUGIN     = dirname(__DIR__);

if ($LEGACY === null || ! is_file($LEGACY . '/class_catalog.php')) {
    fwrite(STDERR, "SKIP: legacy tree not found"
        . ($LEGACY !== null ? " at {$LEGACY}" : " (pass its path, or set ELIMU_LEGACY_ROOT)") . "\n");
    exit(0);
}

// The legacy file include-guards on SCRIPT_FILENAME; satisfy it.
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require $LEGACY . '/class_catalog.php';
require $LEGACY . '/teacher/period_helpers.php';

require $PLUGIN . '/API/Enums/GradingSystem.php';
require $PLUGIN . '/Domain/Catalog/ClassTrack.php';
require $PLUGIN . '/Domain/Catalog/ClassCatalog.php';

use Plugins\SchoolClasses\API\Enums\GradingSystem;
use Plugins\SchoolClasses\Domain\Catalog\ClassCatalog;

$fail = 0;
$check = function (string $what, $legacy, $ported) use (&$fail): void {
    if ($legacy === $ported) { return; }
    $fail++;
    echo "MISMATCH  $what\n";
    echo "   legacy: " . json_encode($legacy, JSON_UNESCAPED_UNICODE) . "\n";
    echo "   ported: " . json_encode($ported, JSON_UNESCAPED_UNICODE) . "\n";
};

// 1 — the flat ordered class list
$check('order()', elimu_class_order(), ClassCatalog::order());

// 2 — label => classes[]
$check('map()', elimu_class_map(), ClassCatalog::map());

// 3 — track labels + shorts, in order
$check('track labels', array_column(elimu_class_groups(), 'label'),
    array_map(fn($t) => $t->label, ClassCatalog::tracks()));
$check('track shorts', array_column(elimu_class_groups(), 'short'),
    array_map(fn($t) => $t->short, ClassCatalog::tracks()));
$check('track patterns', array_column(elimu_class_groups(), 'pattern'),
    array_map(fn($t) => $t->pattern, ClassCatalog::tracks()));

// 4 — per class: label, next class, grading system
$all = elimu_class_order();
foreach ($all as $c) {
    $check("labelFor('$c')",  elimu_class_label($c),  ClassCatalog::labelFor($c));
    $check("nextClass('$c')", elimu_next_class($c),   ClassCatalog::nextClass($c));
    $check("gradingSystem('$c')",
        isPrimaryLevel($c) ? 'trimester' : 'session',
        ClassCatalog::gradingSystemFor($c)->value);
}

// 5 — grouping, over the whole list and over legacy misspellings
$check('group(all)', elimu_group_classes($all), ClassCatalog::group($all));

$legacySpellings = [
    '1ère année Electricité', '2ème année Elec', '3ème année Mecanique',
    '1ère année Méc. Auto', '2ème année Prof. Auto', '4ème année CONST',
    '1ère année NUTRITION', 'Maternelle PS', '7ème année EB',
];
$check('group(legacy spellings)',
    elimu_group_classes($legacySpellings), ClassCatalog::group($legacySpellings));
foreach ($legacySpellings as $c) {
    $check("gradingSystem(legacy '$c')",
        isPrimaryLevel($c) ? 'trimester' : 'session',
        ClassCatalog::gradingSystemFor($c)->value);
}

// 6 — edge cases
foreach (['', '   ', 'Unknown Class', 'primaire', 'MATERNELLE'] as $c) {
    $check("nextClass(edge '$c')", elimu_next_class($c), ClassCatalog::nextClass($c));
    $check("gradingSystem(edge '$c')",
        isPrimaryLevel($c) ? 'trimester' : 'session',
        ClassCatalog::gradingSystemFor($c)->value);
}

echo "\nclasses compared : " . count($all) . "\n";
echo "tracks           : " . count(ClassCatalog::tracks()) . "\n";
echo "sections         : " . count(ClassCatalog::sections()) . "\n";
echo $fail === 0 ? "RESULT: IDENTICAL to legacy\n" : "RESULT: $fail MISMATCH(ES)\n";
exit($fail === 0 ? 0 : 1);
