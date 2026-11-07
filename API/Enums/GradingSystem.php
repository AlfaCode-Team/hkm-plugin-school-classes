<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses\API\Enums;

/**
 * Which academic calendar a class level is graded on.
 *
 * PUBLISHED, because `school.grading` keys its whole period model on it and may
 * not reach into this plugin's Domain. Legacy source: `teacher/period_helpers.php`.
 *
 * The legacy code decided this with a regex on the class's DISPLAY NAME:
 *
 *     stripos($cl, 'maternelle') !== false || preg_match('/\bprimaire\b/ui', $cl)
 *
 * That is why it is an enum here and why `ClassCatalog` answers the question —
 * a class renamed in the UI must not silently change which calendar its marks
 * are entered against.
 */
enum GradingSystem: string
{
    /** Maternelle + École Primaire: three trimesters, each P1 + P2 + exam. */
    case Trimester = 'trimester';

    /** Éducation de Base and every Humanités track: two sessions, each S1 + S2 + exam. */
    case Session = 'session';

    /** How many marking periods the calendar has, exams included. */
    public function periodCount(): int
    {
        return match ($this) {
            self::Trimester => 9,
            self::Session   => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Trimester => 'Trois trimestres',
            self::Session   => 'Deux sessions',
        };
    }
}
