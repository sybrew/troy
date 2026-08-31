---
name: wrap
description: >-
  Measures visual line width (tab = 4) and applies house wrap rules without
  treating 80 columns as a general max. Use when wrapping PHP or JS, compact
  if/return/continue/break, ternary or coalesce breaks, &&/|| conditions,
  function-argument wraps, or when guessing whether a line is too long.
---
# Wrap and visual width

Do not guess column counts. Tabs are 1 character in editors and in `.length`. House width is **tab = 4**.

Source of truth: `.github/copilot-instructions.md` and `.github/instructions/php.instructions.md`. This skill only stops misreads.

## Measure

From the repo root. Same command on Windows (PowerShell or cmd), macOS, and Linux:

```
php .cursor/skills/wrap/scripts/visual-width.php path/to/file.php
php .cursor/skills/wrap/scripts/visual-width.php --line 85 path/to/file.php
php .cursor/skills/wrap/scripts/visual-width.php --compact-if --gt 80 path/to/file.php
```

`--line N` one 1-based line. `--gt N` only lines wider than N. `--compact-if` only same-line `if ( … ) return|continue|break`. Output is `path:line:width`.

Pipe a snippet:

Windows PowerShell:

```
"if ( ! `$x ) return;" | php .cursor/skills/wrap/scripts/visual-width.php
```

macOS / Linux:

```
printf '%s\n' 'if ( ! $x ) return;' | php .cursor/skills/wrap/scripts/visual-width.php
```

PHP 7.4+ on `PATH`. If `php` is missing, say so and do not invent a width.

## Hold

Do not run a repo-wide wrap scan, and do not apply `do_action` / `return` blanks across the repo, until the user says **go**.

## Decision tree

**80 visual columns apply only to a compact void `if` / `elseif` that keeps `return;`, `continue;`, or `break;` on the same line.** Measure that whole line.

| Case | 80? | What to do |
|---|---|---|
| `if ( expr ) return;` / `continue;` / `break;` and vis ≤ 80 | yes | Keep same line |
| Same, vis > 80, condition is one expression (no `&&` / `\|\|`) | yes | Terminator next line, unbraced. Do not wrap the condition |
| Same, vis > 80, condition has `&&` / `\|\|` | yes | Wrap at those operators (first operand padded 3 spaces), braces. Do not wrap only the terminator |
| Valued `return` after `if` | no | Next line, unbraced. Line length is not the trigger |
| Assignment, ternary, coalesce | no | Stay one line unless another rule already forces a wrap |
| Ternary/coalesce RHS already multiline | no | Break after `=`, pad first operand 3 spaces so `?` / `:` / `??` align |
| Call arguments > 30 characters **total** | no (30) | Wrap those arguments |
| Array/object/closure among several arguments | no | Every argument of that call on its own line |
| SQL over 80 | SQL 80 | SQL clause rules, not compact-if |
| Function `{` blank | n/a | Functions/closures only, 2+ statements. Never after `if` / loop / `try` |
| Blank before `return` / `yield` | n/a | Interior has 2+ statements. Not before `continue` / `break` |
| `do_action()` then `return` | n/a | Blank before `return` (the return rule). Tight before `Template::output_view()`. Repo apply is on Hold until **go**. |

## Do not

- Treat 80 as a max line length for assignments, HTML, CSS-in-PHP, or comments.
- Wrap a single-expression condition to make room for `return;`.
- Break `$a = $cond ? $b : $c;` only because vis > 80.
- Pad a `return` that is not an assignment.
- Insert a function-body blank after `if` / `elseif` / `else` / loop / `try`.

## Misreads

Wrong: wrap this because it is ~110 columns:

```php
$repo_url = $has_repo ? Sanitize::url( $plugin['Troy'] ) : '';
```

Right: one line. 80 is not involved.

Wrong: wrap-and-brace a lone `in_array( … )` so `return;` fits on the `if` line.

Right: `return;` on the next line, condition unchanged.
