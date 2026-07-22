#!/bin/bash
# Script to verify that all strings in the code are translated in the .po file

echo "Generating temporary POT file to count total strings..."
php wp-cli.phar i18n make-pot . temp.pot --quiet

total_strings=$(grep -c "^msgid " temp.pot)
po_strings=$(grep -c "^msgid " languages/souq-pulse-ar.po)
untranslated=$(grep -B 1 "^msgstr \"\"" languages/souq-pulse-ar.po | grep -c "^msgid ")

rm temp.pot

echo "Total strings in code: $total_strings"
echo "Total strings in ar.po: $po_strings"
echo "Untranslated strings: $untranslated"

# The header also has a msgid "" / msgstr "" pair, so untranslated is 1 if fully translated
if [ "$total_strings" -ne "$po_strings" ]; then
    echo "❌ ERROR: Mismatch between code strings ($total_strings) and ar.po entries ($po_strings)!"
    echo "Please run: php wp-cli.phar i18n make-pot . languages/souq-pulse.pot && php wp-cli.phar i18n update-po languages/souq-pulse.pot languages/souq-pulse-ar.po"
    exit 1
fi

if [ "$untranslated" -gt 1 ]; then
    echo "❌ ERROR: Found $(($untranslated - 1)) untranslated strings in ar.po!"
    exit 1
fi

echo "✅ SUCCESS: All strings are extracted and translated!"
exit 0
