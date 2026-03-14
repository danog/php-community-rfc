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

// ── 2. Run pandoc ─────────────────────────────────────────────────────────────
$cmd      = $pandoc . ' ' . escapeshellarg($input) . ' --from=markdown --to=dokuwiki';
$dokuwiki = shell_exec($cmd);

if ($dokuwiki === null || $dokuwiki === '') {
    fwrite(STDERR, "pandoc produced no output. Command: $cmd\n");
    exit(1);
}

// ── 3. Strip the auto-generated title line (first ====== … ======) ───────────
$dokuwiki = preg_replace('/^={6}[^=]+={6}\s*\n/m', '', $dokuwiki, 1) ?? $dokuwiki;

// ── 4. Fix unlabelled <code> blocks → <code php> ─────────────────────────────
$dokuwiki = preg_replace('/^<code>$/m', '<code php>', $dokuwiki) ?? $dokuwiki;

// ── 5. Fix internal anchor links: replace - with _ in slugs ──────────────────
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

// ── 6. Prepend the RFC header ─────────────────────────────────────────────────
$header = <<<HEADER
====== PHP RFC: php-community: a faster-moving, community-driven PHP. ======
  * Version: 1.0
  * Date: 2026-03-14
  * Author: Daniil Gentili, daniil.gentili@gmail.com
  * Status: Draft
  * Repo of the RFC itself: https://github.com/danog/php-community-rfc


HEADER;

$dokuwiki = $header . ltrim($dokuwiki);

// ── 7. Write output ───────────────────────────────────────────────────────────
if (file_put_contents($output, $dokuwiki) === false) {
    fwrite(STDERR, "Failed to write output to: $output\n");
    exit(1);
}

echo "Written to: $output\n";
