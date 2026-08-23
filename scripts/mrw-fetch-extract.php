<?php

declare(strict_types=1);

/**
 * Downloads and extracts text from The Missionary Review of the
 * World's scanned archive at cafis.org (see
 * SVM/missionary-review-pdfs.txt), then writes one lightweight JSON
 * summary per issue to exports/missionary-review/ for
 * scripts/import-missionary-review.php to consume.
 *
 * Standalone CLI, no WordPress/WP-CLI dependency (same category as
 * scripts/build-country-maps.py / reconcile-m49.py):
 *
 *   php scripts/mrw-fetch-extract.php [--write] [--year=1888] [--limit=20] [--source=PATH]
 *
 * Default is a dry run per scripts/README.md's stated policy: lists
 * what would be fetched/extracted, touches no network or filesystem
 * beyond reading the source list. Pass --write to actually download,
 * run pdftotext, and write JSON.
 *
 * The source PDFs are already OCR'd by cafis.org (confirmed by hand
 * before building this: pdftotext extracts real text directly), so no
 * local OCR engine is invoked. "-combined.pdf" files (a duplicate
 * concatenation of that year's 12 monthly issues) and the one bulk
 * "MRW-Indexes 1888-1939.pdf" (a duplicate concatenation of the 52
 * per-year index files) are deliberately skipped.
 *
 * Country/person tagging is automated and best-effort, never
 * editorially verified — see docs/decisions/0004-missionary-review-archive-ingestion.md.
 * Country names are matched against the theme's own canonical
 * includes/data/country-manifest.json (so tags line up with real
 * `country` posts), plus a small table of unambiguous historical name
 * aliases (e.g. "Persia" -> iran) used in 1888-1939 prose; ambiguous
 * historical regions that split into multiple modern countries (e.g.
 * "French Equatorial Africa") are deliberately left untagged rather
 * than guessed.
 *
 * Idempotent and resumable: PDFs and their extracted text are cached
 * by filename under SVM/pdf-cache/ and SVM/text-cache/ and skipped on
 * re-runs, so an interrupted --write run can simply be re-run.
 *
 * @package CountryWeek
 */

$repo_root = dirname(__DIR__);
$source_default = dirname(dirname($repo_root)) . '/SVM';

$options = parse_options($argv);
$write = (bool) ($options['write'] ?? false);
$year_filter = isset($options['year']) ? (int) $options['year'] : null;
$limit = isset($options['limit']) ? (int) $options['limit'] : null;
$source_dir = is_string($options['source'] ?? null) ? $options['source'] : $source_default;

$list_file = $source_dir . '/missionary-review-pdfs.txt';
$pdf_cache_dir = $source_dir . '/pdf-cache';
$text_cache_dir = $source_dir . '/text-cache';
$export_dir = $repo_root . '/exports/missionary-review';
$manifest_path = $repo_root . '/theme/country-week/includes/data/country-manifest.json';
$reports_dir = $repo_root . '/reports';

if (!is_file($list_file)) {
    fwrite(STDERR, "Source list not found: {$list_file}\n");
    exit(1);
}

if (!is_file($manifest_path)) {
    fwrite(STDERR, "Country manifest not found: {$manifest_path}\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifest_path), true);
$name_to_key = build_country_lookup(is_array($manifest['countries'] ?? null) ? $manifest['countries'] : []);

$entries = parse_source_list($list_file, $year_filter);

if ($limit !== null) {
    $entries = array_slice($entries, 0, $limit);
}

fwrite(STDOUT, sprintf(
    "%d file(s) selected (year=%s, limit=%s, source=%s).\n",
    count($entries),
    $year_filter ?? 'all',
    $limit ?? 'none',
    $source_dir
));

if (!$write) {
    fwrite(STDOUT, "Dry run - pass --write to actually fetch/extract. Would process:\n");

    foreach ($entries as $entry) {
        fwrite(STDOUT, "  {$entry['key']}  {$entry['url']}\n");
    }

    exit(0);
}

mkdir_p($pdf_cache_dir);
mkdir_p($text_cache_dir);
mkdir_p($export_dir);
mkdir_p($reports_dir);

$stats = ['downloaded' => 0, 'pdf_cached' => 0, 'extracted' => 0, 'text_cached' => 0, 'failed' => 0, 'written' => 0];

foreach ($entries as $entry) {
    $pdf_path = $pdf_cache_dir . '/' . $entry['filename'];
    $txt_path = $text_cache_dir . '/' . preg_replace('/\.pdf$/i', '.txt', $entry['filename']);

    if (!is_file($pdf_path)) {
        if (!download_file($entry['url'], $pdf_path)) {
            fwrite(STDERR, "FAILED download: {$entry['url']}\n");
            $stats['failed']++;

            continue;
        }

        $stats['downloaded']++;
        usleep(300000); // Polite pacing between fresh downloads from cafis.org.
    } else {
        $stats['pdf_cached']++;
    }

    if (!is_file($txt_path) || filesize($txt_path) === 0) {
        if (!extract_text($pdf_path, $txt_path)) {
            fwrite(STDERR, "FAILED pdftotext: {$entry['filename']}\n");
            $stats['failed']++;

            continue;
        }

        $stats['extracted']++;
    } else {
        $stats['text_cached']++;
    }

    $text = (string) file_get_contents($txt_path);
    // Defensive: guarantee valid UTF-8 regardless of pdftotext's actual
    // output for a given scan, since both the /u-flagged person regex
    // and json_encode() below would otherwise silently fail closed
    // (empty results / an empty written file) rather than error loudly.
    $text = (string) @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    $countries = detect_countries($text, $name_to_key);
    $persons = detect_persons($text);

    $record = [
        'key' => $entry['key'],
        'kind' => $entry['kind'],
        'year' => $entry['year'],
        'month' => $entry['month'],
        'source_pdf_url' => $entry['url'],
        'volume_label' => detect_volume_label($text),
        'countries' => $countries,
        'persons' => $persons,
        'excerpt' => make_excerpt($text),
        'word_count' => str_word_count($text),
    ];

    file_put_contents(
        $export_dir . '/' . $entry['key'] . '.json',
        json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
    $stats['written']++;

    fwrite(STDOUT, sprintf("OK  %-16s countries=%d persons=%d\n", $entry['key'], count($countries), count($persons)));
}

file_put_contents(
    $reports_dir . '/mrw-ingest-progress.json',
    json_encode(['generated_at' => gmdate('c'), 'selected' => count($entries), 'stats' => $stats], JSON_PRETTY_PRINT) . "\n"
);

fwrite(STDOUT, "\nDone. " . json_encode($stats) . "\n");

/**
 * Minimal `--flag` / `--flag=value` parser. No external CLI arg
 * library is warranted for four options.
 */
function parse_options(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $body = substr($arg, 2);

        if (str_contains($body, '=')) {
            [$key, $value] = explode('=', $body, 2);
            $options[$key] = $value;
        } else {
            $options[$body] = true;
        }
    }

    return $options;
}

/**
 * Read SVM/missionary-review-pdfs.txt and resolve it into the concrete
 * set of files to fetch: every monthly issue and yearly index, with
 * both duplicate "combined" file types skipped (see file docblock).
 */
function parse_source_list(string $file, ?int $year_filter): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entries = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if (!str_starts_with($line, 'http')) {
            continue;
        }

        $filename = basename(urldecode($line));

        if (preg_match('/-combined\.pdf$/i', $filename)) {
            continue;
        }

        if (!preg_match('/^MRW-(\d{4})-(\d{1,2}|Index)\.pdf$/i', $filename, $m)) {
            continue; // e.g. the bulk "MRW-Indexes 1888-1939.pdf" — not a single issue/index.
        }

        $year = (int) $m[1];

        if ($year_filter !== null && $year !== $year_filter) {
            continue;
        }

        $is_index = strtolower($m[2]) === 'index';
        $month = $is_index ? null : (int) $m[2];
        $kind = $is_index ? 'index' : 'issue';
        $key = $is_index ? sprintf('mrw-%d-index', $year) : sprintf('mrw-%d-%02d', $year, $month);

        $entries[] = [
            'key' => $key,
            'kind' => $kind,
            'year' => $year,
            'month' => $month,
            'url' => $line,
            'filename' => $filename,
        ];
    }

    usort($entries, fn (array $a, array $b) => [$a['year'], $a['month'] ?? 13] <=> [$b['year'], $b['month'] ?? 13]);

    return $entries;
}

/**
 * Canonical country name (lowercase) -> manifest key, from the theme's
 * own country-manifest.json, plus a small table of unambiguous
 * historical-name aliases actually used in 1888-1939 prose. An alias
 * is only added if its target key genuinely exists in the current
 * manifest — never fabricated, never guessed for ambiguous
 * multi-country historical regions (see file docblock).
 */
function build_country_lookup(array $countries): array
{
    $valid_keys = [];
    $lookup = [];

    foreach ($countries as $country) {
        if (empty($country['key']) || empty($country['name'])) {
            continue;
        }

        $valid_keys[$country['key']] = true;
        $lookup[strtolower($country['name'])] = $country['key'];
    }

    $aliases = [
        'persia' => ['iran'],
        'siam' => ['thailand'],
        'ceylon' => ['sri-lanka'],
        'abyssinia' => ['ethiopia'],
        'gold coast' => ['ghana'],
        'formosa' => ['taiwan'],
        'new hebrides' => ['vanuatu'],
        'basutoland' => ['lesotho'],
        'bechuanaland' => ['botswana'],
        'nyasaland' => ['malawi'],
        'dutch east indies' => ['indonesia'],
        'netherlands east indies' => ['indonesia'],
        'british guiana' => ['guyana'],
        'dutch guiana' => ['suriname'],
        'french somaliland' => ['djibouti'],
        'portuguese east africa' => ['mozambique'],
        'portuguese west africa' => ['angola'],
    ];

    foreach ($aliases as $name => $candidates) {
        foreach ($candidates as $candidate) {
            if (isset($valid_keys[$candidate])) {
                $lookup[$name] = $candidate;

                break;
            }
        }
    }

    return $lookup;
}

/**
 * Whole-word, case-insensitive match of every known country/alias name
 * against the issue's OCR text. Deliberately simple string matching,
 * not NLP — good enough as a browsing aid, not a verified index (see
 * file docblock and the archive template's on-page disclaimer).
 */
function detect_countries(string $text, array $name_to_key): array
{
    $found = [];

    foreach ($name_to_key as $name => $key) {
        if (mb_strlen($name) < 4) {
            continue; // Skip anything too short to avoid noisy false positives.
        }

        if (preg_match('/\b' . preg_quote($name, '/') . '\b/i', $text) === 1) {
            $found[$key] = true;
        }
    }

    $keys = array_keys($found);
    sort($keys);

    // A high safety cap, not a normal limit: this magazine's whole
    // premise is a global monthly survey of missions, so a single
    // ~50,000-word issue legitimately mentioning 40-80 countries is
    // real content (confirmed against the 1888 pilot batch), not noise
    // to truncate down to.
    return array_slice($keys, 0, 120);
}

/**
 * Honorific + capitalized-name heuristic (Rev./Miss/Mrs./Dr./Bishop/
 * Mr. followed by 1-4 capitalized tokens). Explicitly best-effort
 * OCR-era name extraction, not verified identification — kept as the
 * full "Rev. J. M. Sherwood" style match (honorific included) since
 * that is what makes an entry citable back to the source text.
 *
 * The honorific alternation is case-insensitive (scoped with `(?i:`,
 * not a blanket `/i` flag) because period magazine text is frequently
 * typeset/OCR'd as small caps ("REV. ROYAL G. WILDER") rather than
 * "Rev." — a blanket /i would also make the name-capturing [A-Z] class
 * match lowercase letters and flood the result with junk. Punctuation
 * after the honorific is deliberately loose ([\s.,]{0,4}) since OCR
 * commonly renders "Rev." as "REV," or "REV ." with stray spacing.
 */
function detect_persons(string $text): array
{
    $pattern = '/\b(?i:Rev|Miss|Mrs|Dr|Bishop|Mr)\b[\s.,]{0,4}((?:[A-Z][A-Za-z.\'-]{0,20}\s*){1,4})/u';

    if (preg_match_all($pattern, $text, $matches) === false) {
        return [];
    }

    $names = [];

    foreach ($matches[0] as $full) {
        $clean = preg_replace('/\s+/', ' ', trim($full));
        $clean = rtrim((string) $clean, ",.;:");
        // A trailing "'s" possessive (e.g. "Dr. Judson's") is the same
        // person as a plain "Dr. Judson" mention elsewhere — strip it
        // so the two collapse into one tag instead of two near-duplicates.
        $clean = preg_replace("/'s$/i", '', $clean);

        if ($clean === null || mb_strlen($clean) < 6 || mb_strlen($clean) > 60) {
            continue;
        }

        // A trailing hyphen means pdftotext preserved a mid-word line
        // break from the original typesetting (e.g. "Dr. Judson's Bur-")
        // rather than a real name — the fragment is incomplete, not
        // just noisy, so it's dropped rather than kept.
        if (str_ends_with($clean, '-')) {
            continue;
        }

        $names[$clean] = true;
    }

    $list = array_keys($names);
    sort($list);

    return array_slice($list, 0, 80);
}

function make_excerpt(string $text): string
{
    $clean = preg_replace('/\s+/', ' ', trim($text));

    return mb_substr((string) $clean, 0, 600);
}

/**
 * Best-effort citation line ("VOL. XI. No. 1...") pulled straight from
 * the masthead text near the top of each issue, when present.
 */
function detect_volume_label(string $text): ?string
{
    if (preg_match('/VOL\.[^\n]{0,80}/i', $text, $m) === 1) {
        return trim(preg_replace('/\s+/', ' ', $m[0]));
    }

    return null;
}

/**
 * Shells out to the `curl` CLI binary rather than using PHP's curl
 * extension: this machine's PHP build has no CA bundle configured for
 * libcurl/OpenSSL (verify fails with "unable to get local issuer
 * certificate"), while the standalone curl.exe correctly uses
 * Windows's native certificate store via Schannel.
 */
function download_file(string $url, string $dest): bool
{
    $tmp = $dest . '.part';
    $cmd = sprintf(
        'curl -sS --fail -A %s --max-time 120 -o %s %s',
        escapeshellarg('country-week-mrw-archive/1.0 (one-time archival fetch)'),
        escapeshellarg($tmp),
        escapeshellarg($url)
    );
    exec($cmd . ' 2>&1', $output, $exit_code);

    if ($exit_code !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);

        if ($output !== []) {
            fwrite(STDERR, '  curl error: ' . implode(' ', $output) . "\n");
        }

        return false;
    }

    rename($tmp, $dest);

    return true;
}

function extract_text(string $pdf_path, string $txt_path): bool
{
    $tmp = $txt_path . '.part';
    // Explicit -enc UTF-8: pdftotext otherwise defaults to Latin-1-ish
    // output, which silently broke every /u-flagged regex downstream
    // (preg_match_all returns false on invalid UTF-8 rather than
    // erroring loudly) and would have broken json_encode() too.
    $cmd = sprintf('pdftotext -layout -enc UTF-8 %s %s', escapeshellarg($pdf_path), escapeshellarg($tmp));
    exec($cmd, $output, $exit_code);

    if ($exit_code !== 0 || !is_file($tmp)) {
        @unlink($tmp);

        return false;
    }

    rename($tmp, $txt_path);

    return true;
}

function mkdir_p(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}
