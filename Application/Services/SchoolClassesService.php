<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses\Application\Services;

use Plugins\SchoolClasses\API\Contracts\SchoolClassesContract;
use Plugins\SchoolClasses\API\Enums\GradingSystem;
use Plugins\SchoolClasses\Domain\Catalog\ClassCatalog;

/**
 * Fulfils `school.classes`.
 *
 * Only the catalogue half is implemented so far: it is pure, declared data, so
 * it needs no repository and no `SchoolScope`. The stored half — `classes`,
 * `class_access`, `subjects`, `subject_subsubjects` — is still to be ported, and
 * those tables have NO DDL in the legacy tree, so they wait on the schema
 * reconstruction (PORT-PLAN.md section 8).
 *
 * No transaction or event machinery here yet because nothing mutates. When the
 * stored half lands, the mutating methods take the mandatory shape:
 * authorize -> beginCollection -> begin -> work -> commit -> dispatch after
 * commit -> discard in every catch.
 */
final class SchoolClassesService implements SchoolClassesContract
{
    public function classOrder(): array
    {
        return ClassCatalog::order();
    }

    public function classMap(): array
    {
        return ClassCatalog::map();
    }

    public function sections(): array
    {
        return ClassCatalog::sections();
    }

    public function isKnownClass(string $className): bool
    {
        return ClassCatalog::isKnown($className);
    }

    public function sectionFor(string $className): ?string
    {
        return ClassCatalog::sectionFor($className);
    }

    public function labelFor(string $className): string
    {
        return ClassCatalog::labelFor($className);
    }

    public function gradingSystemFor(string $className): GradingSystem
    {
        return ClassCatalog::gradingSystemFor($className);
    }

    public function nextClass(?string $className): ?string
    {
        return ClassCatalog::nextClass($className);
    }

    public function groupClasses(array $classNames): array
    {
        return ClassCatalog::group($classNames);
    }
}
