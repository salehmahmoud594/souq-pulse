<?php
/**
 * Script to verify that all translatable strings in the code are present in the .po file.
 * Run this from the root of the plugin directory: php scripts/check_i18n.php
 */

if (!file_exists('wp-cli.phar')) {
    echo "wp-cli.phar not found in the root directory. Please download it first.\n";
    exit(1);
}

echo "Generating temporary POT file to count total strings...\n";
exec('php wp-cli.phar i18n make-pot . temp.pot --quiet');

if (!file_exists('temp.pot')) {
    echo "Failed to generate temp.pot\n";
    exit(1);
}

$pot_content = file_get_contents('temp.pot');
$po_content = file_get_contents('languages/souq-pulse-ar.po');

preg_match_all('/^msgid "(.*?)"\r?$/m', $pot_content, $pot_matches);
preg_match_all('/^msgid "(.*?)"\r?$/m', $po_content, $po_matches);

$total_strings = count(array_unique($pot_matches[1]));
$po_strings = count(array_unique($po_matches[1]));

// Check untranslated
preg_match_all('/^msgid "(.*?)"\r?\nmsgstr ""\r?$/m', $po_content, $untranslated_matches);
// Remove the header empty string match if present
$untranslated_count = 0;
foreach ($untranslated_matches[1] as $msgid) {
    if (!empty($msgid)) {
        $untranslated_count++;
    }
}

unlink('temp.pot');

echo "Total unique strings in code: $total_strings\n";
echo "Total unique strings in ar.po: $po_strings\n";
echo "Untranslated strings: $untranslated_count\n\n";

if ($total_strings > $po_strings) {
    echo "❌ ERROR: Mismatch! There are strings in the code missing from ar.po.\n";
    echo "Please run: php wp-cli.phar i18n make-pot . languages/souq-pulse.pot && php wp-cli.phar i18n update-po languages/souq-pulse.pot languages/souq-pulse-ar.po\n";
    exit(1);
}

if ($untranslated_count > 0) {
    echo "❌ ERROR: Found $untranslated_count untranslated strings in ar.po!\n";
    exit(1);
}

echo "✅ SUCCESS: All strings are extracted and translated!\n";
exit(0);
