#!/usr/bin/env bash
# Converts README.md to DokuWiki markup for wiki.php.net.
# Requires: pandoc >= 2.0  (brew install pandoc)
#
# Usage:
#   ./convert_to_dokuwiki.sh [input.md [output.txt]]
#
# Defaults: README.md → rfc.dokuwiki.txt

set -euo pipefail

INPUT="${1:-$(dirname "$0")/README.md}"
OUTPUT="${2:-$(dirname "$0")/rfc.dokuwiki.txt}"

RFC_HEADER='====== PHP RFC: php-community: a faster-moving, community-driven PHP. ======
  * Version: 1.0
  * Date: 2026-03-14
  * Author: Daniil Gentili, daniil.gentili@gmail.com
  * Status: Draft
  * Repo of the RFC itself: https://github.com/danog/php-community-rfc

'

# Run pandoc, then:
#   1. Drop the first heading line (we replace it with the RFC header above).
#   2. Replace the manual ToC block (everything between the ToC heading and the
#      first real section heading) with DokuWiki's native ~~TOC~~ directive,
#      which is already included in RFC_HEADER above, so just strip the block.
#   3. Fix unlabelled <code> blocks → <code php>.
body=$(
  pandoc "$INPUT" --from=markdown --to=dokuwiki \
  | awk '
    # Skip the auto-generated title line (first ====== … ======)
    NR == 1 && /^======/ { next }

    { print }
  ' \
  | sed 's|^<code>$|<code php>|'
)

printf '%s%s\n' "$RFC_HEADER" "$body" > "$OUTPUT"
echo "Written to: $OUTPUT"
