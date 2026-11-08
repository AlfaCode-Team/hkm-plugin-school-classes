# Port status — `school.classes`

**Partially ported.** The catalogue is done and verified. The stored half is not
started.

## Ported, and proven identical to the legacy system

The **class catalogue** — 12 sections, 38 tracks, 147 classes — from
`public/class_catalog.php` (513 lines), as `Domain\Catalog\ClassCatalog` plus the
`ClassTrack` value object, published through all 9 methods of
`SchoolClassesContract`.

This was not ported by reading and re-implementing. The legacy functions and this
port were **run side by side** over every class, track, section, label,
promotion step and grouping, and the outputs compared:

```
$ php tools/diff-against-legacy.php /path/to/ElimuSystem
classes compared : 147
tracks           : 38
sections         : 12
RESULT: IDENTICAL to legacy
```

The recorded legacy run is committed as
`tests/fixtures/legacy-class-catalog.json`, so the suite — **170 tests, 647
assertions** — runs with no access to the legacy tree. The harness skips cleanly
(exit 0) when the tree is absent.

That comparison caught three defects this port would otherwise have shipped:
`group()` sorts each bucket in reading order and keeps empty tracks plus an
`ungrouped` bucket; it matches by pattern only, never by exact value, removing
matches from the pool so an earlier track wins an overlap; and `labelFor()` must
**not** trim, because the label is printed onto a bulletin and may not quietly
rewrite its input.

### One deliberate behaviour change

`gradingSystemFor()` replaces the legacy `isPrimaryLevel()`, which decided a
class's entire academic calendar with `preg_match('/\bprimaire\b/ui', $name)`
over its **display name**. The answer now comes from the catalogue, so renaming a
class in the UI can no longer move its marks onto a different calendar. Output is
identical for all 147 classes; only the mechanism differs.

## Not ported

Everything that reads or writes a table:

| | |
|---|---|
| Legacy tables | `classes`, `class_access`, `subjects`, `subject_subsubjects` |
| Legacy source | `manage_subject.php`, `assign_teacher.php` |

No migrations, no repository, no routes, no controllers. The catalogue needed
none of this because it is **declared data, not stored data** — which is exactly
why it could be ported first, with no database.

Those four tables are among the ~50 with **no DDL anywhere** in the legacy tree.
They have to be reconstructed from the queries that read them before a repository
can be written here (PORT-PLAN.md §8).

Two of them also carry no `school_id` (`class_access`, `subjects`) — defects S3/S4
in the port plan. That decision is open and blocks the stored half, because it
determines whether these rows are per-school or global.

## The plan this follows

`PORT-PLAN.md` and `PLUGIN-PLAN.md` in the **ElimuSystem** repository. Not public.
