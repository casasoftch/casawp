Select2 4.1.0
=============

Source: https://www.npmjs.com/package/select2/v/4.1.0
License: MIT (see LICENSE.md).

The distribution JavaScript and locale files replace `.fn.select2` with
`.fn.casawpSelect2` to isolate the plugin registration and AMD loader from
Select2 versions loaded by other WordPress plugins. The internal outside-click
close call also uses `.casawpSelect2()`, and its open-container selector is scoped
to `.select2-container--casawp` so it does not manage another plugin’s dropdowns.
No other vendor changes.
English is included in the main bundle; German, French and Italian are bundled separately.
