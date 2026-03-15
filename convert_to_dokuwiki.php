#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Converts README.md to DokuWiki markup for wiki.php.net using pandoc.
 *
 * Requirements: pandoc >= 2.0  (brew install pandoc)
 *
 * Usage:
 *   php convert_to_dokuwiki.php [input.md [output.txt]]
 *
 * Defaults: README.md → rfc.dokuwiki.txt
 */

$scriptDir = dirname(__FILE__);
$input     = $argv[1] ?? $scriptDir . '/README.md';
$output    = $argv[2] ?? $scriptDir . '/rfc.dokuwiki.txt';

if (!file_exists($input)) {
    fwrite(STDERR, "Input file not found: $input\n");
    exit(1);
}

// ── 1. Verify pandoc is available ─────────────────────────────────────────────
$pandoc = trim((string) shell_exec('command -v pandoc 2>/dev/null'));
if ($pandoc === '') {
    fwrite(STDERR, "pandoc not found. Install it from https://pandoc.org/installing.html\n");
    exit(1);
}

// ── 2. Regenerate TOC in the markdown file ────────────────────────────────────
$mdContent = file_get_contents($input);
if ($mdContent === false) {
    fwrite(STDERR, "Failed to read input file: $input\n");
    exit(1);
}

// Extract all headings (lines starting with #), skip h1 and the TOC heading itself.
$lines = explode("\n", $mdContent);
$tocLines = [];
$minLevel = PHP_INT_MAX;
foreach ($lines as $line) {
    if (!preg_match('/^(#{2,6}) (.+)$/', $line, $m)) {
        continue;
    }
    $level = strlen($m[1]);
    $text  = trim($m[2]);
    if (strtolower($text) === 'table of contents') {
        continue;
    }
    if ($level < $minLevel) {
        $minLevel = $level;
    }
    // GitHub-style slug: lowercase, spaces→hyphens, keep alphanumerics and hyphens only.
    $slug = strtolower($text);
    $slug = preg_replace('/[^\w\s-]/u', '', $slug) ?? $slug;  // strip punctuation
    $slug = preg_replace('/\s+/', '-', trim($slug)) ?? $slug;
    $slug = preg_replace('/-+/', '-', $slug) ?? $slug;

    $indent   = str_repeat('  ', $level - $minLevel);
    $tocLines[] = "{$indent}- [{$text}](#{$slug})";
}

$newToc = implode("\n", $tocLines);

// Replace the existing TOC block (content between the TOC heading and the next heading).
$mdContent = preg_replace(
    '/^(## Table of Contents\n\n).*?(\n\n(?=#{1,6} ))/ms',
    "$1{$newToc}$2",
    $mdContent
) ?? $mdContent;

if (file_put_contents($input, $mdContent) === false) {
    fwrite(STDERR, "Failed to write updated TOC to: $input\n");
    exit(1);
}
echo "TOC updated in: $input\n";

// ── 3. Run pandoc ─────────────────────────────────────────────────────────────
// Re-read the (possibly updated) markdown.
$cmd      = $pandoc . ' ' . escapeshellarg($input) . ' --from=markdown --to=dokuwiki';
$dokuwiki = shell_exec($cmd);

if ($dokuwiki === null || $dokuwiki === '') {
    fwrite(STDERR, "pandoc produced no output. Command: $cmd\n");
    exit(1);
}

// ── 4. Strip the auto-generated title line (first ====== … ======) ───────────
$dokuwiki = preg_replace('/^={6}[^=]+={6}\s*\n/m', '', $dokuwiki, 1) ?? $dokuwiki;

// ── 5. Fix unlabelled <code> blocks → <code php> ─────────────────────────────
$dokuwiki = preg_replace('/^<code>$/m', '<code php>', $dokuwiki) ?? $dokuwiki;

$dokuwiki = preg_replace('/.*Click here to read the discussion thread.*/', '', $dokuwiki) ?? $dokuwiki;
$dokuwiki = preg_replace('/.*Click here to read this RFC proposal.*/', '', $dokuwiki) ?? $dokuwiki;

// ── 6. Fix internal anchor links: replace - with _ in slugs ──────────────────────
//   DokuWiki anchor IDs use underscores, not hyphens.
//   Matches [[#some-slug]] and [[#some-slug|Label text]].
$dokuwiki = preg_replace_callback(
    '/\[\[#([^\]|]+)(\|[^\]]*)?\]\]/',
    static function (array $m): string {
        $slug  = str_replace('-', '_', $m[1]);
        $label = $m[2] ?? '';
        return "[[#$slug$label]]";
    },
    $dokuwiki
) ?? $dokuwiki;

// ── 7. Prepend the RFC header ─────────────────────────────────────────────────
$header = <<<HEADER
====== PHP RFC: php-community: a faster-moving, community-driven PHP. ======
  * Version: 1.0.1
  * Date: 2026-03-14
  * Author: Daniil Gentili, daniil.gentili@gmail.com
  * Status: Under Discussion
  * Repo of the RFC itself: https://github.com/danog/php-community-rfc
  * Discussion thread: https://externals.io/message/130313


HEADER;

$dokuwiki = $header . ltrim($dokuwiki);

// ── 8. Write output ───────────────────────────────────────────────────────────
if (file_put_contents($output, $dokuwiki) === false) {
    fwrite(STDERR, "Failed to write output to: $output\n");
    exit(1);
}

echo "Written to: $output\n";
