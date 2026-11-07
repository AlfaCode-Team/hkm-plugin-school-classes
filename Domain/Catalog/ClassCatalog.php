<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses\Domain\Catalog;

use Plugins\SchoolClasses\API\Enums\GradingSystem;

/**
 * The single source of truth for every class the system knows about.
 *
 * Ported verbatim from `public/class_catalog.php` (513 lines) in the legacy
 * Elimu system, which exists because the master list used to live as raw
 * `<option>` markup and every screen that needed to ORDER, GROUP or advance a
 * class kept its own copy — and those copies drifted the moment the official
 * list grew.
 *
 * Two things this class adds over the legacy functions:
 *
 *  1. `gradingSystemFor()` replaces `isPrimaryLevel()` in
 *     `teacher/period_helpers.php`, which decided a class's whole academic
 *     calendar with `preg_match('/\bprimaire\b/ui', $displayName)`. The answer
 *     now comes from the catalogue, so renaming a class in the UI cannot move
 *     its marks onto a different calendar.
 *  2. `resolve()` separates a KNOWN class value from a legacy misspelling, so a
 *     caller can tell "this is 4ème année Électricité" from "this is some old
 *     row that buckets into ELEC".
 *
 * Group order is the order the legacy dropdown showed, and a class belongs to
 * the FIRST track whose pattern matches — so patterns must stay mutually
 * exclusive. Do not reorder.
 */
final class ClassCatalog
{
    /** Tracks where finishing the last class leads automatically into the next track. */
    private const PROMOTION_CHAIN = ['Maternelle', 'École Primaire', 'EB (Éducation de Base)'];

    /** @var list<ClassTrack>|null */
    private static ?array $tracks = null;

    /** @return list<ClassTrack> in catalogue order */
    public static function tracks(): array
    {
        return self::$tracks ??= self::build();
    }

    /** @return list<ClassTrack> */
    private static function build(): array
    {
        /** Almost every humanités option runs four years with the code appended. */
        $y4 = static fn(string $c): array => [
            "1ère année $c", "2ème année $c", "3ème année $c", "4ème année $c",
        ];
        $t = static fn(string $section, string $label, string $short, string $pattern,
                       array $classes, array $labels = []): ClassTrack
            => new ClassTrack($section, $label, $short, $pattern, $classes, $labels);

        $MAT  = 'Maternelle';
        $PRIM = 'École Primaire';
        $EB   = 'Éducation de Base';
        $HG   = 'Humanités Générales';
        $PED  = 'Pédagogie';
        $COM  = 'Humanités Commerciales & Administratives';
        $SOC  = 'Humanités Sociales & Services';
        $IND  = 'Humanités Industrielles';
        $AGR  = 'Humanités Agricoles';
        $MED  = 'Humanités Médicales';
        $ART  = 'Humanités Artistiques & Sportives';
        $CC3  = 'Cycle Court / Professionnel (3 ans)';

        return [
            /* ── Maternelle & fondamental ─────────────────────────── */
            $t($MAT, 'Maternelle', 'Maternelle', '/maternelle/iu',
                ['Maternelle PS', 'Maternelle MS', 'Maternelle GS'],
                ['Maternelle PS' => 'Petite Section (PS)',
                 'Maternelle MS' => 'Moyenne Section (MS)',
                 'Maternelle GS' => 'Grande Section (GS)']),
            $t($PRIM, 'École Primaire', 'École Primaire', '/primaire/iu', [
                '1ère année primaire', '2ème année primaire', '3ème année primaire',
                '4ème année primaire', '5ème année primaire', '6ème année primaire',
            ]),
            $t($EB, 'EB (Éducation de Base)', 'EB', '/\bEB\b/u',
                ['7ème année EB', '8ème année EB']),

            /* ── Humanités Générales ──────────────────────────────── */
            $t($HG, 'SC (Sciences - Math-Physique / Bio-Chimie)', 'SC', '/\bSC\b/u', $y4('SC')),
            $t($HG, 'HP (Humanités et Philosophie / Latin-Philo)', 'HP', '/\bHP\b/u', $y4('HP')),
            $t($HG, 'LG (Latin-Grec)', 'LG', '/\bLG\b/u', $y4('LG')),
            $t($HG, 'LM (Latin-Mathématiques)', 'LM', '/\bLM\b/u', $y4('LM')),
            $t($HG, 'HL (Humanités Littéraires)', 'HL', '/\bHL\b/u', $y4('HL')),

            /* ── Pédagogie ────────────────────────────────────────── */
            $t($PED, 'PG (Pédagogie Générale)', 'PG', '/\bPG\b/u', $y4('PG')),
            $t($PED, 'PM (Pédagogie Maternelle)', 'PM', '/\bPM\b/u', $y4('PM')),
            $t($PED, 'PPS (Pédagogie Pré-Scolaire)', 'PPS', '/\bPPS\b/u', $y4('PPS')),
            $t($PED, 'PGCC (Pédagogie Générale - Cycle Court)', 'PGCC', '/\bPGCC\b/u',
                ['1ère année PGCC', '2ème année PGCC']),

            /* ── Commerciales & Administratives ───────────────────── */
            $t($COM, 'CG (Commerciale et Gestion)', 'CG', '/\bCG\b/u', $y4('CG')),
            $t($COM, 'IG (Informatique de Gestion)', 'IG', '/\bIG\b/u', $y4('IG')),
            $t($COM, 'SEC (Secrétariat et Administration)', 'SEC', '/\bSEC\b/u', $y4('SEC')),

            /* ── Sociales, Hôtellerie & Services ──────────────────── */
            $t($SOC, 'HTS (Techniques et Sociales)', 'HTS', '/\bHTS\b/u', $y4('HTS')),
            $t($SOC, 'CC (Coupe et Couture)', 'CC', '/\bCC\b/u', $y4('CC')),
            $t($SOC, 'COIF (Coiffure)', 'COIF', '/\bCOIF\b/u', $y4('COIF')),
            $t($SOC, 'HR (Hôtellerie et Restauration)', 'HR', '/\bHR\b/u', $y4('HR')),
            $t($SOC, "HEB (Technique d'Hébergement)", 'HEB', '/\bHEB\b/u', $y4('HEB')),

            /* ── Industrielles ────────────────────────────────────── */
            // "Electricit"/"Elec" catch rows created before the accent and the ELEC/ELT split
            $t($IND, 'ELEC (Électricité Industrielle)', 'ELEC', '/(?:[ÉE]lectricit|\bElec\b)/iu', [
                '1ère année Électricité', '2ème année Électricité',
                '3ème année Électricité', '4ème année Électricité',
            ]),
            $t($IND, 'ELT (Électronique et Télécoms)', 'ELT', '/\bELT\b/u', $y4('ELT')),
            // Full word only — "Méc. Auto" belongs to the Cycle Court group further down
            $t($IND, 'MEC (Mécanique Générale & Machines-Outils)', 'MEC', '/M[ée]canique/iu', [
                '1ère année Mécanique', '2ème année Mécanique',
                '3ème année Mécanique', '4ème année Mécanique',
            ]),
            $t($IND, 'MEN (Menuiserie - Ébénisterie)', 'MEN', '/\bMEN\b/u', $y4('MEN')),
            $t($IND, 'HYD (Hydro-Pneumatique)', 'HYD', '/\bHYD\b/u', $y4('HYD')),
            $t($IND, 'CONST (Construction et BTP)', 'CONST', '/CONST/iu', [
                '1ère année CONST.', '2ème année CONST.',
                '3ème année CONST.', '4ème année CONST.',
            ]),

            /* ── Agricoles ────────────────────────────────────────── */
            $t($AGR, 'AGRO (Agriculture Générale)', 'AGRO', '/\bAGRO\b/u', $y4('AGRO')),
            $t($AGR, 'IA (Industries Agricoles)', 'IA', '/\bIA\b/u', $y4('IA')),
            $t($AGR, 'FOR (Foresterie)', 'FOR', '/\bFOR\b/u', $y4('FOR')),
            $t($AGR, 'PN (Pêche et Navigation)', 'PN', '/\bPN\b/u', $y4('PN')),
            $t($AGR, 'VET (Vétérinaire)', 'VET', '/\bVET\b/u', $y4('VET')),

            /* ── Médicales ────────────────────────────────────────── */
            $t($MED, 'INF (Infirmerie Générale)', 'INF', '/\bINF\b/u', $y4('INF')),
            $t($MED, 'NUTRITION (Nutrition et Diététique)', 'NUTRITION', '/NUTRITION/iu', [
                '1ère année NUTRITION', '2ème année NUTRITION',
                '3ème année NUTRITION', '4ème année NUTRITION',
            ]),

            /* ── Arts & Sport ─────────────────────────────────────── */
            $t($ART, 'AP (Arts Plastiques)', 'AP', '/\bAP\b/u', $y4('AP')),
            $t($ART, 'MUS (Musique)', 'MUS', '/\bMUS\b/u', $y4('MUS')),
            $t($ART, 'EPS (Éducation Physique)', 'EPS', '/\bEPS\b/u', $y4('EPS')),

            /* ── Cycle Court / Professionnel (3 ans) ──────────────── */
            $t($CC3, 'PROF (Cycle Court - Mécanique Auto)', 'PROF Auto',
                '/(?:Prof\.?\s*Auto|M[ée]c\.\s*Auto)/iu',
                ['1ère année Prof. Auto', '2ème année Prof. Auto', '3ème année Prof. Auto'],
                ['1ère année Prof. Auto' => '1ère année Méc. Auto',
                 '2ème année Prof. Auto' => '2ème année Méc. Auto',
                 '3ème année Prof. Auto' => '3ème année Méc. Auto']),
            $t($CC3, 'PROF (Cycle Court - Menuiserie)', 'PROF Menuiserie', '/Menuiserie/iu', [
                '1ère année Prof. Menuiserie', '2ème année Prof. Menuiserie',
                '3ème année Prof. Menuiserie',
            ], ['1ère année Prof. Menuiserie' => '1ère année Menuiserie',
                '2ème année Prof. Menuiserie' => '2ème année Menuiserie',
                '3ème année Prof. Menuiserie' => '3ème année Menuiserie']),
        ];
    }

    /** Every class value, flattened, in catalogue order. `elimu_class_order()`. */
    public static function order(): array
    {
        return array_merge(...array_map(static fn(ClassTrack $t): array => $t->classes, self::tracks()));
    }

    /** label => classes[], in catalogue order. `elimu_class_map()`. */
    public static function map(): array
    {
        $out = [];
        foreach (self::tracks() as $t) {
            $out[$t->label] = $t->classes;
        }

        return $out;
    }

    /** The 12 section names, in catalogue order, de-duplicated. */
    public static function sections(): array
    {
        return array_values(array_unique(array_map(
            static fn(ClassTrack $t): string => $t->section,
            self::tracks(),
        )));
    }

    public static function isKnown(string $className): bool
    {
        return self::trackOwning($className) !== null;
    }

    /** The track that DECLARES this exact class value. */
    public static function trackOwning(string $className): ?ClassTrack
    {
        $className = trim($className);
        foreach (self::tracks() as $t) {
            if ($t->hasClass($className)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * The track a class name belongs to — exact value first, then the
     * legacy-spelling patterns in catalogue order.
     *
     * This two-step order matters. Matching patterns first would let
     * `/\bSC\b/u` or `/\bIA\b/u` claim an exact value from another track that
     * happens to contain those letters as a word.
     */
    public static function resolve(string $className): ?ClassTrack
    {
        $className = trim($className);
        if ($className === '') {
            return null;
        }

        return self::trackOwning($className)
            ?? self::trackMatchingLegacySpelling($className);
    }

    public static function trackMatchingLegacySpelling(string $className): ?ClassTrack
    {
        foreach (self::tracks() as $t) {
            if ($t->matchesLegacySpelling($className)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * Display label for a class value. Faithful port of `elimu_class_label()`.
     *
     * A flat lookup across every track's `labels` map, falling back to the
     * value UNCHANGED — deliberately not trimmed, and deliberately not routed
     * through `trackOwning()`. Almost every class is its own label; the Cycle
     * Court tracks are the exception, where the stored value
     * ("1ère année Prof. Auto") and the printed label ("1ère année Méc. Auto")
     * differ on purpose.
     *
     * Trimming here was the port's own bug: `labelFor('   ')` returned `''`
     * where the legacy function returns `'   '`. Harmless on its own, but the
     * label is printed onto a bulletin, so it may not quietly rewrite its input.
     */
    public static function labelFor(string $className): string
    {
        static $labels = null;
        if ($labels === null) {
            $labels = [];
            foreach (self::tracks() as $track) {
                foreach ($track->labels as $value => $label) {
                    $labels[$value] = $label;
                }
            }
        }

        return $labels[$className] ?? $className;
    }

    public static function sectionFor(string $className): ?string
    {
        return self::resolve($className)?->section;
    }

    /**
     * Which academic calendar this class is graded on.
     *
     * Replaces `isPrimaryLevel()` in `teacher/period_helpers.php`: Maternelle
     * and École Primaire run three trimesters, everything else — Éducation de
     * Base included — runs two sessions. An unknown class falls back to
     * `Session`, which is what the legacy regex did (it only answered "primary"
     * on a positive match), so old rows keep landing where they always did.
     */
    public static function gradingSystemFor(string $className): GradingSystem
    {
        $section = self::sectionFor($className);

        return match ($section) {
            'Maternelle', 'École Primaire' => GradingSystem::Trimester,
            default                        => GradingSystem::Session,
        };
    }

    /**
     * The next class up WITHIN the same track. `elimu_next_class()`.
     *
     * At the end of a track only the general cycles continue elsewhere —
     * Maternelle into École Primaire, École Primaire into EB — because those
     * are the only ones where finishing leads automatically into the next. A
     * humanités track ends and the pupil leaves or changes filière, which is a
     * decision, not a promotion.
     */
    public static function nextClass(?string $className): ?string
    {
        $className = trim((string) $className);
        if ($className === '') {
            return null;
        }

        $tracks = self::tracks();
        foreach ($tracks as $i => $track) {
            if (! $track->hasClass($className)) {
                continue;
            }

            $within = $track->classAfter($className);
            if ($within !== null) {
                return $within;
            }

            $next = $tracks[$i + 1] ?? null;
            if ($next !== null
                && in_array($track->label, self::PROMOTION_CHAIN, true)
                && in_array($next->label, self::PROMOTION_CHAIN, true)
            ) {
                return $next->classes[0] ?? null;
            }

            return null;
        }

        return null;
    }

    /**
     * Assign class names to the catalogue tracks, in catalogue order.
     * Faithful port of `elimu_group_classes()`.
     *
     * Four behaviours here are load-bearing and were all found by diffing this
     * port against the legacy function — none is guesswork:
     *
     *  1. Matching is by PATTERN only, never by exact class value. That is what
     *     lets an old misspelling bucket at all.
     *  2. A matched class is REMOVED from the pool, so where two patterns
     *     overlap the earlier track wins and nothing is ever displayed — or
     *     billed — twice.
     *  3. Each bucket is sorted `SORT_NATURAL | SORT_FLAG_CASE`, so the result
     *     is in reading order, NOT in catalogue progression order.
     *     `Maternelle` therefore comes back GS, MS, PS — not PS, MS, GS.
     *  4. EVERY track appears, empty ones included, and whatever matched
     *     nothing comes back under `ungrouped`. Callers render the empty
     *     buckets as empty `<optgroup>`s.
     *
     * @param  list<string> $classNames
     * @return array{groups: list<array{label: string, classes: list<string>}>, ungrouped: list<string>}
     */
    public static function group(array $classNames): array
    {
        $remaining = array_values(array_unique(array_filter(
            $classNames,
            static fn($c): bool => $c !== '' && $c !== null,
        )));

        $groups = [];
        foreach (self::tracks() as $track) {
            $inside = [];
            foreach ($remaining as $name) {
                if ($track->matchesLegacySpelling((string) $name)) {
                    $inside[] = $name;
                }
            }

            // Remove as we go, so an earlier track wins any overlap.
            $remaining = array_values(array_diff($remaining, $inside));
            sort($inside, SORT_NATURAL | SORT_FLAG_CASE);
            $groups[] = ['label' => $track->label, 'classes' => $inside];
        }

        sort($remaining, SORT_NATURAL | SORT_FLAG_CASE);

        return ['groups' => $groups, 'ungrouped' => $remaining];
    }
}
