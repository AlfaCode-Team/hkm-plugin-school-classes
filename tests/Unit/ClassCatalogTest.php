<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugins\SchoolClasses\API\Enums\GradingSystem;
use Plugins\SchoolClasses\Domain\Catalog\ClassCatalog;

/**
 * Pins the ported catalogue to the behaviour of the legacy implementation.
 *
 * The legacy Elimu system CANNOT BE RUN — its database was built by hand on
 * production and there is no dump — so there is no oracle to diff against at
 * runtime. `class_catalog.php` and `teacher/period_helpers.php` are the
 * exception: they are pure functions with no database, so the two
 * implementations were run side by side and `tests/fixtures/legacy-class-catalog.json`
 * records what the legacy one answered for all 147 classes, 38 tracks, every
 * known legacy misspelling and the empty/unknown edge cases.
 *
 * The fixture is a GOLDEN FILE. A failure here means the port drifted, not that
 * the expectation is stale — do not regenerate it to make a test pass.
 *
 * Four of these assertions exist because the first version of the port got them
 * wrong and the diff caught it: `group()` matches by pattern (never by exact
 * value), removes matches from the pool so the earlier track wins, sorts each
 * bucket naturally rather than by progression, and keeps empty tracks plus an
 * `ungrouped` bucket.
 */
final class ClassCatalogTest extends TestCase
{
    /** @var array<string, mixed> */
    private static array $legacy;

    public static function setUpBeforeClass(): void
    {
        $path = __DIR__ . '/../fixtures/legacy-class-catalog.json';
        self::$legacy = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_flat_class_order_matches_legacy(): void
    {
        self::assertSame(self::$legacy['order'], ClassCatalog::order());
    }

    public function test_the_label_to_classes_map_matches_legacy(): void
    {
        self::assertSame(self::$legacy['map'], ClassCatalog::map());
    }

    public function test_track_labels_shorts_and_patterns_match_legacy_in_order(): void
    {
        $tracks = ClassCatalog::tracks();
        self::assertSame(self::$legacy['labels'],   array_map(static fn($t) => $t->label, $tracks));
        self::assertSame(self::$legacy['shorts'],   array_map(static fn($t) => $t->short, $tracks));
        self::assertSame(self::$legacy['patterns'], array_map(static fn($t) => $t->pattern, $tracks));
    }

    public function test_there_are_twelve_sections(): void
    {
        self::assertCount(12, ClassCatalog::sections());
        self::assertSame('Maternelle', ClassCatalog::sections()[0]);
        self::assertSame('Cycle Court / Professionnel (3 ans)', ClassCatalog::sections()[11]);
    }

    /** @return iterable<string, array{string, array{label: string, next: ?string, grading: string}}> */
    public static function legacyPerClassCases(): iterable
    {
        $path = __DIR__ . '/../fixtures/legacy-class-catalog.json';
        $f = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        foreach ($f['perClass'] as $class => $expected) {
            // PHPUnit rejects an empty data-set key, and the fixture carries
            // '' and '   ' on purpose — they are the edge cases the legacy
            // helpers were asked about.
            $key = trim((string) $class) === ''
                ? sprintf('(blank: %d char(s))', strlen((string) $class))
                : (string) $class;

            yield $key => [(string) $class, $expected];
        }
    }

    /** @param array{label: string, next: ?string, grading: string} $expected */
    #[DataProvider('legacyPerClassCases')]
    public function test_label_promotion_and_grading_system_match_legacy(string $class, array $expected): void
    {
        self::assertSame($expected['label'],   ClassCatalog::labelFor($class),  'label');
        self::assertSame($expected['next'],    ClassCatalog::nextClass($class), 'next class');
        self::assertSame($expected['grading'], ClassCatalog::gradingSystemFor($class)->value, 'grading system');
    }

    public function test_grouping_the_whole_catalogue_matches_legacy(): void
    {
        self::assertSame(self::$legacy['groupAll'], ClassCatalog::group(self::$legacy['order']));
    }

    public function test_grouping_legacy_misspellings_matches_legacy(): void
    {
        $input = [
            '1ère année Electricité', '2ème année Elec', '3ème année Mecanique',
            '1ère année Méc. Auto', '2ème année Prof. Auto', '4ème année CONST',
            '1ère année NUTRITION', 'Maternelle PS', '7ème année EB',
        ];
        self::assertSame(self::$legacy['groupLegacySpellings'], ClassCatalog::group($input));
    }

    public function test_group_keeps_every_track_including_empty_ones(): void
    {
        $result = ClassCatalog::group(['Maternelle PS']);
        self::assertCount(38, $result['groups']);
        self::assertSame([], $result['ungrouped']);
        self::assertSame(['Maternelle PS'], $result['groups'][0]['classes']);
        self::assertSame([], $result['groups'][1]['classes']);
    }

    public function test_group_returns_unmatched_names_as_ungrouped(): void
    {
        $result = ClassCatalog::group(['Totally Unknown']);
        self::assertSame(['Totally Unknown'], $result['ungrouped']);
    }

    public function test_group_sorts_each_bucket_naturally_not_by_progression(): void
    {
        // Progression order is PS, MS, GS. Natural sort is GS, MS, PS — and the
        // legacy function sorts, so callers see reading order.
        $result = ClassCatalog::group(['Maternelle PS', 'Maternelle MS', 'Maternelle GS']);
        self::assertSame(['Maternelle GS', 'Maternelle MS', 'Maternelle PS'], $result['groups'][0]['classes']);
    }

    public function test_the_three_general_cycles_chain_and_nothing_else_does(): void
    {
        self::assertSame('1ère année primaire', ClassCatalog::nextClass('Maternelle GS'));
        self::assertSame('7ème année EB',       ClassCatalog::nextClass('6ème année primaire'));
        self::assertNull(ClassCatalog::nextClass('8ème année EB'), 'after EB the pupil picks an option');
        self::assertNull(ClassCatalog::nextClass('4ème année SC'), 'a humanités track ends');
    }

    public function test_maternelle_and_primaire_are_the_only_trimester_sections(): void
    {
        foreach (ClassCatalog::order() as $class) {
            $expected = in_array(ClassCatalog::sectionFor($class), ['Maternelle', 'École Primaire'], true)
                ? GradingSystem::Trimester
                : GradingSystem::Session;
            self::assertSame($expected, ClassCatalog::gradingSystemFor($class), $class);
        }
        self::assertSame(GradingSystem::Session, ClassCatalog::gradingSystemFor('7ème année EB'),
            'Éducation de Base runs two sessions, not three trimesters');
    }

    public function test_resolve_prefers_an_exact_value_over_a_pattern(): void
    {
        // '1ère année IA' is owned by AGRO's IA track. Resolving by pattern
        // first could let an earlier track claim it; exact-value-first cannot.
        self::assertSame('IA (Industries Agricoles)', ClassCatalog::resolve('1ère année IA')?->label);
        self::assertTrue(ClassCatalog::isKnown('1ère année IA'));
        self::assertFalse(ClassCatalog::isKnown('1ère année Electricité'), 'a misspelling is not a known value');
        self::assertSame('ELEC (Électricité Industrielle)',
            ClassCatalog::resolve('1ère année Electricité')?->label, 'but it still resolves');
    }

    public function test_an_unknown_class_resolves_to_nothing(): void
    {
        self::assertNull(ClassCatalog::resolve(''));
        self::assertNull(ClassCatalog::resolve('   '));
        self::assertNull(ClassCatalog::resolve('Totally Unknown'));
        self::assertNull(ClassCatalog::sectionFor('Totally Unknown'));
    }
}
