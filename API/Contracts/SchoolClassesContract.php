<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses\API\Contracts;

use Plugins\SchoolClasses\API\Enums\GradingSystem;

/**
 * Published contract for `school.classes`.
 *
 * The class catalogue (12 sections, 38 tracks, 147 classes), classes, subjects,
 * sub-subjects and teacher-to-class grants.
 *
 * Legacy source : class_catalog.php (513 lines), classes, class_access,
 *                 subjects, subject_subsubjects, manage_subject.php, assign_teacher.php
 * Legacy tables : classes, class_access, subjects, subject_subsubjects, class_catalog
 *
 * Consumers declare `"requires": ["school.classes"]` and resolve THIS
 * interface. Nothing outside this plugin may touch its internals.
 *
 * ## The catalogue is the authority, not a class's name
 *
 * The legacy system decided a class's whole academic calendar by regex on its
 * DISPLAY NAME (`preg_match('/\bprimaire\b/ui', $name)` in
 * `teacher/period_helpers.php`). `gradingSystemFor()` replaces that: the answer
 * comes from the catalogue, so renaming a class in the UI cannot move its marks
 * onto a different calendar. `school.grading` keys its entire period model on
 * the returned {@see GradingSystem}.
 *
 * Every method below is pure and database-free — the catalogue is declared, not
 * stored. They are pinned to the legacy behaviour by
 * `tests/Unit/ClassCatalogTest.php` against a golden fixture recorded from the
 * legacy source, because the legacy app cannot be run.
 */
interface SchoolClassesContract
{
    /** Every class value the system knows, flattened, in catalogue order. */
    public function classOrder(): array;

    /** Track label => class values, in catalogue order. */
    public function classMap(): array;

    /** The 12 section names, in catalogue order. */
    public function sections(): array;

    /** Is this an exact, known class value (not merely a resolvable misspelling)? */
    public function isKnownClass(string $className): bool;

    /** The section a class belongs to, resolving legacy spellings. Null if unknown. */
    public function sectionFor(string $className): ?string;

    /**
     * Display label for a class value — the value itself, except on the Cycle
     * Court tracks where the stored value and the printed label differ.
     */
    public function labelFor(string $className): string;

    /** Which academic calendar this class is graded on. Unknown falls back to Session. */
    public function gradingSystemFor(string $className): GradingSystem;

    /**
     * The next class up within the same track.
     *
     * Only the three general cycles chain — Maternelle into École Primaire,
     * École Primaire into EB. A humanités track ends and the pupil picks an
     * option, which is a decision rather than a promotion, so that returns null.
     */
    public function nextClass(?string $className): ?string;

    /**
     * Bucket class names into the catalogue tracks for display.
     *
     * @param  list<string> $classNames
     * @return array{groups: list<array{label: string, classes: list<string>}>, ungrouped: list<string>}
     */
    public function groupClasses(array $classNames): array;
}
