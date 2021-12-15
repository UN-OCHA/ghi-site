Global Humanitarian Overview - Footnotes module
===============================================

This module processes text paragraph footnotes.

The current approach is to display the footnotes as close as possible to the
paragraph that has references to them. This is similar in concept to the print
version where footnotes are at the bottom of the page with references to them as
opposed to have them grouped at the end of the document.

There is also an "accumulated" mode that groups all the footnotes at the bottom
of the page. This is done in 2 steps: first by altering the rendering of the
text + footnote fields and then parsing the resulting HTML (that could come from
the cache, thus the first step) to extract the footnotes, update the references
and add the footnote list at the bottom.

This attempts to provide accessible markup as much as possible while also
ensuring translations can work. Further improvements can be made with CSS and
javascript if necessary.

This assumes there is a `text` paragraph type with a `field_text` field and a
`field_footnotes` field. So this works in conjunction with the footnotes styles
for the ckeditor present in the `common_design_subtheme`.

**Notes**

The footnotes processing is in a separate module and not in the
`common_design_subtheme` so that the processing is also done for text paragraphs
in the edit forms.

See the [gho-footnotes](../../themes/custom/common_design_subtheme/components/gho-footnotes)
for the styling.

Templates
---------

This module provides several templates for the references, footnotes, backlinks
etc.
