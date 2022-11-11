Global Humanitarian Overview - Footnotes Component
==================================================

Styling for the footnotes and their references in text paragraphs.

See the `gho_footnotes` custom module for the processing and templates.

There is a [script](gho-footnotes.js) that handles the display of the footnotes
in a "popup" at the bottom of the screen, when footnote references are present
in the upper half of the viewport.

**TODO**

- The display of the references and backlinks seem to be different in Arabic,
  notably it looks like the references are not rendered as "super" but normal
  height in italic (`[lang="ar"] .gho-footnote-reference`).
- The hit area is really small. This [site](https://wet-boew.github.io/v4.0-ci/demos/footnotes/footnotes-en.html) has nice large references that work well on mobile.

