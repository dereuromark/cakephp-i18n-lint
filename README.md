# CakePHP i18n Lint

[![CI](https://github.com/dereuromark/cakephp-i18n-lint/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/dereuromark/cakephp-i18n-lint/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Checks the translation catalogs of a CakePHP plugin or application:

- `check` fails when a POT template no longer matches the `__()` calls in the code.
- `extract` regenerates the templates.
- `sync` adds new strings to the PO files and drops removed ones.
- `lint` validates POT and PO files: syntax, placeholders, ICU messages, plural forms.

Extraction uses CakePHP's own `i18n extract` command, so the result is the same file
`bin/cake i18n extract` would write. It runs without a booted application, which is
what makes it usable in the CI of a plugin.

## Installation

```bash
composer require --dev dereuromark/cakephp-i18n-lint
```

Requires PHP 8.2+ and CakePHP 5.1+.

## Usage

Run the commands from the root of the plugin or application.

```bash
vendor/bin/i18n-lint check
vendor/bin/i18n-lint extract
vendor/bin/i18n-lint sync
vendor/bin/i18n-lint lint
```

By default `src/` and `templates/` are scanned and the catalogs are expected in
`resources/locales/`. All commands accept:

| Option      | Meaning                                                    |
|-------------|------------------------------------------------------------|
| `--root`    | Project root, defaults to the working directory.           |
| `--paths`   | Comma-separated source paths, relative to the root.        |
| `--locales` | Locales directory, relative to the root.                   |
| `-v`        | Show what the extractor reported and per-string details.   |

For a plugin inside an application, point `--root` at it:

```bash
vendor/bin/i18n-lint check --root plugins/Blog
```

### check

Extracts into a temporary directory and compares the result with the committed
templates, message by message. The creation date in the header and the order of the
entries do not count as a difference.

```text
resources/locales/comments.pot: error: "Invalid parent id." is missing from the template. [pot.missing]
resources/locales/comments.pot: error: "The Comment has been saved." is no longer in the source code. [pot.stale]

How to fix:
  [pot.missing] Run `vendor/bin/i18n-lint extract` and commit the result.
  [pot.stale] Run `vendor/bin/i18n-lint extract` and commit the result.
2 difference(s). Run `vendor/bin/i18n-lint extract` and commit the result.
```

The printed commands can be pasted as they are: they repeat the `--root`, `--paths` and
`--locales` options the failing run was called with.

Exits with `1` on any difference. A template whose domain has no strings left in the
code is reported as a warning only.

### extract

Writes the templates. A template whose messages did not change is left untouched, so
the creation date does not produce a diff on every run. Source locations are not
written.

### sync

Updates every existing `resources/locales/<locale>/<domain>.po` from its template:
missing strings are appended untranslated, strings that left the template are removed.
Existing entries keep their exact text, comments and order.

An entry whose plural form changed in the template (singular to plural, or another
`msgid_plural`) is reset to untranslated, because the old translation no longer fits.

| Option            | Meaning                                                  |
|-------------------|----------------------------------------------------------|
| `--dry-run`       | Report only, and exit with `1` if a file would change.   |
| `--keep-obsolete` | Do not remove strings that left the template.            |

### lint

| Rule                     | Severity | Meaning                                                            |
|--------------------------|----------|--------------------------------------------------------------------|
| `po.syntax`              | error    | The file cannot be parsed.                                         |
| `po.encoding`            | error    | The file is not valid UTF-8.                                       |
| `po.duplicate`           | error    | The same string (and context) is defined twice.                    |
| `po.placeholder-unknown` | error    | The translation uses a placeholder the source does not have.       |
| `po.icu`                 | error    | The translation is not a valid ICU message.                        |
| `po.printf`              | error    | The `sprintf` specifiers differ from the source.                   |
| `po.plural-count`        | error    | Number of plural forms differs from `nplurals` in the header.      |
| `po.plural-mismatch`     | error    | Plural in the template but singular in the PO file, or vice versa. |
| `pot.icu`                | error    | A source string is not a valid ICU message.                        |
| `po.placeholder-missing` | warning  | The translation drops a placeholder.                               |
| `po.untranslated`        | warning  | Empty translation.                                                 |
| `po.fuzzy`               | warning  | Marked fuzzy. CakePHP does not skip fuzzy entries.                 |
| `po.missing`             | warning  | A template string is not in the PO file. Fix with `sync`.          |
| `po.obsolete`            | warning  | A PO string is not in the template anymore. Fix with `sync`.       |
| `po.whitespace`          | warning  | Leading or trailing whitespace differs from the source.            |
| `po.plural-header`       | warning  | Plural entries exist, but the header has no `Plural-Forms`.        |

Errors exit with `1`. Pass `--strict` to fail on warnings too, or `--fail-on` to
promote selected warnings only:

```bash
vendor/bin/i18n-lint lint --fail-on po.missing,po.obsolete
```

That setup requires every PO file to be in sync with its template, without requiring
every string to be translated. `sync` fixes both rules.

After the findings, a `How to fix:` block lists one line per reported rule, with the
`sync` or `extract` command to run where that is the fix.

ICU validation only applies to strings with placeholders. CakePHP returns a string
without arguments as it is, so a lone `{` or an apostrophe in such a string is fine.

## CI

One run on the highest PHP and CakePHP version is enough.

```yaml
  i18n:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: mbstring, intl
          coverage: none
      - run: composer update --no-progress
      - run: vendor/bin/i18n-lint check
      - run: vendor/bin/i18n-lint lint
```

On GitHub Actions `check` and `lint` emit workflow annotations. Use `--format text`
or `--format github` to choose explicitly.

As Composer scripts:

```json
"scripts": {
    "i18n-check": "i18n-lint check",
    "i18n-extract": "i18n-lint extract",
    "i18n-lint": "i18n-lint lint"
}
```

## Strings the extractor cannot see

A call with a variable, such as `__d('feedback', $priority)`, cannot be extracted.
`check` and `extract` warn about each of them:

```text
Invalid marker content in src/Store/Priorities.php:35
1 call(s) with a non-literal string cannot be extracted.
```

If such strings are added to the template by hand, `check` reports them as stale. List
them as literals in a file that is only there to be scanned, and add its directory to
the paths:

```php
<?php
// resources/i18n/strings.php - never loaded, only read by the extractor.
__d('feedback', 'low');
__d('feedback', 'medium');
__d('feedback', 'high');
```

```bash
vendor/bin/i18n-lint check --paths src,templates,resources/i18n
```

## Limits

- CakePHP 5.4+ reflects the scanned classes to read enum labels. A class whose parent
  cannot be autoloaded is skipped for that step, its `__()` calls are still found. Run
  with `-v` to see which classes were skipped.
- Only existing PO files are synced. A new locale starts as a copy of the template.
- `sync` does not create gettext-style `#~` obsolete entries. Removed strings are gone.
