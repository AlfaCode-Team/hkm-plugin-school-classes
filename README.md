# hkm-plugin-school-classes

> Solves **`school.classes`** · namespace **`Plugins\SchoolClasses\`** · on-demand GDA module
> Family: Elimu / school domain

The class catalog (12 sections), classes, subjects, sub-subjects and teacher-to-class grants.

## Status

**The class catalogue is ported and verified. The stored half is not.**

| Half | State |
|---|---|
| The catalogue — 12 sections, 38 tracks, 147 classes, labels, promotion, grading system, grouping | **Done.** Pure declared data; no database |
| `classes`, `class_access`, `subjects`, `subject_subsubjects` | Not started — those tables have **no DDL** in the legacy tree and wait on the schema reconstruction (`PORT-PLAN.md` §8) |

### How the catalogue was verified

The legacy Elimu app cannot be run — its database was built by hand on
production and there is no dump — so there is normally no oracle to check a port
against. `class_catalog.php` and `teacher/period_helpers.php` are the exception:
they are **pure functions with no database**, so both implementations were run
side by side and compared.

```bash
php tools/diff-against-legacy.php   # needs the legacy tree; prints IDENTICAL or the mismatches
php tools/regenerate-fixture.php    # re-records tests/fixtures/legacy-class-catalog.json
vendor/bin/phpunit                  # 170 tests, 647 assertions — runs WITHOUT the legacy tree
```

`tests/fixtures/legacy-class-catalog.json` is a **golden file** recorded from the
legacy source: 147 classes, 38 tracks, every known legacy misspelling and the
blank/unknown edge cases. A test failure means the port drifted — do not
regenerate the fixture to make one pass.

That comparison found two real bugs in the first draft of this port, which is
why it exists:

- `group()` sorts each bucket `SORT_NATURAL | SORT_FLAG_CASE`, so `Maternelle`
  comes back **GS, MS, PS** — reading order, not progression order — and it keeps
  every track including the empty ones, plus an `ungrouped` bucket.
- `labelFor()` does **not** trim its input. It returned `''` where the legacy
  function returns `'   '`. Harmless alone, but the label is printed onto a
  bulletin, so it may not quietly rewrite what it was given.

### The one deliberate behaviour change

`gradingSystemFor()` replaces `isPrimaryLevel()`, which decided a class's entire
academic calendar by regex on its **display name**:

```php
stripos($cl, 'maternelle') !== false || preg_match('/\bprimaire\b/ui', $cl)
```

The answer now comes from the catalogue, so renaming a class in the UI cannot
move its marks onto a different calendar. The outcome is identical for all 147
known classes and every legacy misspelling — the fixture proves it — but the
mechanism is no longer a string match on a label.

## Where it comes from

| | |
|---|---|
| Legacy source | `class_catalog.php (513 lines), classes, class_access, subjects, subject_subsubjects, manage_subject.php, assign_teacher.php` |
| Legacy tables | `classes, class_access, subjects, subject_subsubjects, class_catalog` |

The port plan, the reconstructed domain model and the per-plugin recipe live in
the **ElimuSystem** repository: `PORT-PLAN.md` and `PLUGIN-PLAN.md`.

## Requires

- `database.management`
- `school.directory`

Declared as module **domains**, never port or contract class names. An unknown
domain fails the boot.

## Using it

```jsonc
// the consumer's module.json / proj.json
{ "requires": ["school.classes"] }
```

```php
$svc = $container->make(\Plugins\SchoolClasses\API\Contracts\SchoolClassesContract::class);
```

## Installing

```bash
hkm plugins enable SchoolClasses <project>
```
