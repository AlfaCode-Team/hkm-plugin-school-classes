<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses\Domain\Catalog;

/**
 * One row of the class catalogue: a track (filière) and the classes it runs.
 *
 * Ported from `elimu_class_catalog()` in `public/class_catalog.php`, which is
 * the legacy system's declared single source of truth for every class it knows.
 *
 * `$pattern` is the LEGACY-SPELLING resolver, not the identity of the track. It
 * exists because rows written before 2026 carry older spellings — `Electricité`
 * before the accent, `Elec` before ELEC/ELT were split, `Méc. Auto` before the
 * Cycle Court rename — and those rows must still bucket into the right section
 * or their bulletins detach from it. Patterns are matched in catalogue order and
 * the FIRST hit wins, so they must stay mutually exclusive; the legacy file says
 * so, and notes that `HYD` was chosen over `HP` for Hydro-Pneumatique precisely
 * because `HP` was already Latin-Philo.
 */
final readonly class ClassTrack
{
    /**
     * @param list<string>          $classes classes in progression order
     * @param array<string, string> $labels  class value => display label, where they differ
     */
    public function __construct(
        public string $section,
        public string $label,
        public string $short,
        public string $pattern,
        public array  $classes,
        public array  $labels = [],
    ) {}

    /** Does a legacy, possibly misspelled class name belong to this track? */
    public function matchesLegacySpelling(string $className): bool
    {
        return (bool) preg_match($this->pattern, $className);
    }

    public function hasClass(string $className): bool
    {
        return in_array($className, $this->classes, true);
    }

    /** Display label for a class value — falls back to the value itself. */
    public function labelFor(string $className): string
    {
        return $this->labels[$className] ?? $className;
    }

    /** The class after this one WITHIN this track, or null at the end of it. */
    public function classAfter(string $className): ?string
    {
        $i = array_search($className, $this->classes, true);
        if ($i === false) {
            return null;
        }

        return $this->classes[$i + 1] ?? null;
    }
}
