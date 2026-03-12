<?php

declare(strict_types=1);

/**
 * GitHub usernames of php internals whose votes should be reported explicitly.
 */
const INTERNALS_GH_USERNAMES = [
    'danog'
];

const GITHUB_API_BASE = 'https://api.github.com';
const GITHUB_API_VERSION = '2026-03-10';
const GITHUB_USERNAME = 'php-src';
const GITHUB_REPO = 'php-community-rfcs';
const MAX_RATE_LIMIT_RETRIES = 5;

if (count($argv) < 2) {
	fwrite(STDERR, "Usage: php gather_votes.php <issue_id>\n");
	exit(1);
}

$issueNumber = (int) $argv[1];

$token = getenv('GITHUB_TOKEN');
if (!$token) {
	fwrite(STDERR, "GITHUB_TOKEN environment variable is required.\n");
	fwrite(STDERR, "Create a token at: https://github.com/settings/tokens\n");
	fwrite(STDERR, "Required scopes: public_repo (for accessing public repository data)\n");
	exit(1);
}

$trackedLookup = [];
$trackedVotes = [];
foreach (INTERNALS_GH_USERNAMES as $username) {
	$trackedLookup[strtolower($username)] = $username;
	$trackedVotes[$username] = 'no vote';
}

$internalsRemaining = count($trackedVotes);

$internalsVoteCounts = [
	'👍' => 0,
	'👎' => 0,
	'👀' => 0,
];

$url = sprintf(
	'%s/repos/%s/%s/issues/%d/reactions?per_page=100',
	GITHUB_API_BASE,
	GITHUB_USERNAME,
	GITHUB_REPO,
	$issueNumber
);

while ($url !== null && $internalsRemaining > 0) {
	$parsedUrl = parse_url($url);
	$currentPage = 1;
	if (is_array($parsedUrl) && isset($parsedUrl['query'])) {
		parse_str($parsedUrl['query'], $query);
		if (isset($query['page']) && is_numeric($query['page'])) {
			$currentPage = (int) $query['page'];
		}
	}

	[
		$decoded,
		$nextPageUrl,
		$lastPage,
	] = githubGetReactionsPage($url, $token);

	if (!isset($lastPageHint) && isset($lastPage)) {
		$lastPageHint = $lastPage;
	} elseif (isset($lastPage) && isset($lastPageHint)) {
		$lastPageHint = max($lastPageHint, $lastPage);
	}

	if (isset($lastPageHint) && $lastPageHint > 0) {
		$percent = (int) min(100, round(($currentPage / $lastPageHint) * 100));
		fwrite(STDERR, "\rPagination: {$currentPage}/{$lastPageHint} ({$percent}%)");
	} else {
		fwrite(STDERR, "\rPagination: page {$currentPage} (...%)");
	}

	foreach ($decoded as $reaction) {
		if (!is_array($reaction)) {
			continue;
		}

		$content = $reaction['content'] ?? null;
		$userLogin = $reaction['user']['login'] ?? null;

		if (!is_string($content) || !is_string($userLogin)) {
			continue;
		}

		if ($content !== '+1' && $content !== '-1' && $content !== 'eyes') {
			continue;
		}

		$vote = match ($content) {
			'+1'   => '👍',
			'-1'   => '👎',
			'eyes' => '👀',
		};
		$userKey = strtolower($userLogin);

		if (isset($trackedLookup[$userKey])) {
			$trackedUsername = $trackedLookup[$userKey];
			$previousVote = $trackedVotes[$trackedUsername];
			if ($previousVote === 'no vote') {
				$internalsRemaining--;
			} elseif ($previousVote !== $vote) {
				$internalsVoteCounts[$previousVote]--;
			}
			if ($previousVote !== $vote) {
				$internalsVoteCounts[$vote]++;
				$trackedVotes[$trackedUsername] = $vote;
			}
			continue;
		}
	}

	$url = $nextPageUrl;
}

fwrite(STDERR, "\rPagination: done (100%)\n");

$totalsUrl = sprintf(
	'%s/repos/%s/%s/issues/%d',
	GITHUB_API_BASE,
	GITHUB_USERNAME,
	GITHUB_REPO,
	$issueNumber
);

$issueDecoded = githubGetIssueWithRateLimit($totalsUrl, $token);

$totalUp = $issueDecoded['reactions']['+1'] ?? 0;
$totalDown = $issueDecoded['reactions']['-1'] ?? 0;
if (!is_int($totalUp) || !is_int($totalDown)) {
	throw new RuntimeException("Unexpected reactions payload in issue response.");
}

$communityUp = max(0, $totalUp - $internalsVoteCounts['👍']);
$communityDown = max(0, $totalDown - $internalsVoteCounts['👎']);

// Abstain counts toward participation/quorum but not toward the yes/no ratio.
$internalsParticipated = $internalsVoteCounts['👍'] + $internalsVoteCounts['👎'] + $internalsVoteCounts['👀'];
$internalsTotal = $internalsVoteCounts['👍'] + $internalsVoteCounts['👎'];
$communityTotal = $communityUp + $communityDown;

$internalsNoVote = count(INTERNALS_GH_USERNAMES) - $internalsParticipated;

if (count(INTERNALS_GH_USERNAMES) === 0) {
    $quorumMet = false;
} else {
    // Quorum is met if at least 50% of tracked internals have participated (voted yes/no/abstain).
    $quorumMet = ($internalsParticipated / count(INTERNALS_GH_USERNAMES)) >= 0.5;
}

function pct(int $part, int $total): string {
	return $total > 0 ? sprintf('%.1f%%', $part / $total * 100) : 'N/A';
}

echo "RFC: https://github.com/" . GITHUB_USERNAME . "/" . GITHUB_REPO . "/issues/{$issueNumber}\n\n";

echo "Internals votes detailed:\n";
foreach ($trackedVotes as $username => $vote) {
	echo sprintf("- %s: %s\n", $username, $vote);
}

echo "\nInternals votes:\n";
echo sprintf("  👍 %d/%d (%s) / 👎 %d/%d (%s) / 👀 %d / Did not vote: %d\n\n",
	$internalsVoteCounts['👍'], $internalsTotal, pct($internalsVoteCounts['👍'], $internalsTotal),
	$internalsVoteCounts['👎'], $internalsTotal, pct($internalsVoteCounts['👎'], $internalsTotal),
	$internalsVoteCounts['👀'],
	$internalsNoVote,
);

echo sprintf("  Quorum (≥50%% of internals participated): %s (%s, %d/%d)\n",
	$quorumMet ? 'YES' : 'NO',
    pct($internalsParticipated, count(INTERNALS_GH_USERNAMES)),
	$internalsParticipated,
	count(INTERNALS_GH_USERNAMES),
);

echo "\nCommunity votes:\n";
echo sprintf("  👍 %d (%s) / 👎 %d (%s)\n",
	$communityUp, pct($communityUp, $communityTotal),
	$communityDown, pct($communityDown, $communityTotal),
);

// Each side accounts for 50% of the total vote; simple majority wins.
// Abstain votes are excluded from the yes/no ratio but count toward quorum.
// If a side has no votes, it contributes no weight and the other side carries 100%.
$internalsYesRate = $internalsTotal > 0 ? $internalsVoteCounts['👍'] / $internalsTotal : null;
$communityYesRate = $communityTotal > 0 ? $communityUp / $communityTotal : null;

$availableWeight = ($internalsYesRate !== null ? 0.5 : 0) + ($communityYesRate !== null ? 0.5 : 0);

echo "\nOutcome:\n";
if ($availableWeight === 0.0) {
	echo "  No votes cast yet.\n";
} else {
	$weightedYes = (($internalsYesRate ?? 0) * ($internalsTotal > 0 ? 0.5 : 0)
		+ ($communityYesRate ?? 0) * ($communityTotal > 0 ? 0.5 : 0))
		/ $availableWeight;

	$yesStr = sprintf('%.1f%%', $weightedYes * 100);
	$noStr  = sprintf('%.1f%%', (1 - $weightedYes) * 100);

	$result = $weightedYes > 0.5 ? 'ACCEPT' : ($weightedYes < 0.5 ? 'REJECT' : 'TIE');

	$notes = [];
	if (!$quorumMet) {
		$notes[] = 'internals quorum not met, automatic REJECT regardless of outcome';
        $result = 'REJECT';
	}
    if ($internalsYesRate === null && $communityYesRate === null) {
        $notes[] = 'no votes from either side';
        $result = 'PENDING';
    } elseif ($internalsYesRate === null) {
		$notes[] = 'no internals votes, weighted from community side only';
	} elseif ($communityYesRate === null) {
        $notes[] = 'no community votes, weighted from internals side only';
    }
	$caveat = $notes !== [] ? ' (' . implode('; ', $notes) . ')' : '';

	echo sprintf("  👍 %s  👎 %s  →  %s%s\n", $yesStr, $noStr, $result, $caveat);
}

echo "\n";

/**
 * @return array{array, ?string, ?int}
 */
function githubGetReactionsPage(string $url, string $token): array
{
	[$statusCode, $body, $linkHeader] = githubRequest($url, $token, true);
	if ($statusCode < 200 || $statusCode >= 300) {
		$message = extractApiErrorMessage($body);
		throw new RuntimeException("GitHub API request failed ({$statusCode}): {$message}");
	}

	$decoded = json_decode($body, true);
	if (!is_array($decoded)) {
		throw new RuntimeException("Invalid JSON response from GitHub API.");
	}

	$nextPageUrl = null;
	$lastPage = null;
	if ($linkHeader !== null) {
		$parts = array_map('trim', explode(',', $linkHeader));
		foreach ($parts as $part) {
			if (!preg_match('/^<([^>]+)>;\s*rel="([^"]+)"$/', $part, $matches)) {
				continue;
			}

			$linkUrl = $matches[1] ?? null;
			$rel = $matches[2] ?? '';

			if ($rel === 'next') {
				$nextPageUrl = $linkUrl;
			}

			if ($rel === 'last' && is_string($linkUrl)) {
				$lastParsed = parse_url($linkUrl);
				if (is_array($lastParsed) && isset($lastParsed['query'])) {
					parse_str($lastParsed['query'], $lastQuery);
					if (isset($lastQuery['page']) && is_numeric($lastQuery['page'])) {
						$lastPage = (int) $lastQuery['page'];
					}
				}
			}
		}
	}

	return [$decoded, $nextPageUrl, $lastPage];
}

/**
 * @return array
 */
function githubGetIssueWithRateLimit(string $url, string $token): array
{
	[$statusCode, $body] = githubRequest($url, $token, false);
	if ($statusCode < 200 || $statusCode >= 300) {
		$message = extractApiErrorMessage($body);
		throw new RuntimeException("GitHub issue fetch failed ({$statusCode}): {$message}");
	}

	$decoded = json_decode($body, true);
	if (!is_array($decoded)) {
		throw new RuntimeException("Invalid JSON response from GitHub issue endpoint.");
	}

	return $decoded;
}

/**
 * @return array{int, string, ?string}
 */
function githubRequest(string $url, string $token, bool $captureLinkHeader): array
{
	$attempt = 0;

	while (true) {
		$ch = curl_init($url);
		if ($ch === false) {
			throw new RuntimeException("Could not initialize cURL.");
		}

		$linkHeader = null;
		$rateLimitRemaining = null;
		$rateLimitReset = null;
		$retryAfter = null;

		$headers = [
			'Accept: application/vnd.github+json',
			'User-Agent: php-community-rfc-vote-gatherer',
			'X-GitHub-Api-Version: ' . GITHUB_API_VERSION,
		];

		$headers[] = 'Authorization: Bearer ' . $token;

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HEADERFUNCTION => static function ($_, string $headerLine) use (&$linkHeader, &$rateLimitRemaining, &$rateLimitReset, &$retryAfter, $captureLinkHeader): int {
				$trimmed = trim($headerLine);
				if ($trimmed === '') {
					return strlen($headerLine);
				}

				if ($captureLinkHeader && stripos($trimmed, 'link:') === 0) {
					$linkHeader = trim(substr($trimmed, 5));
				} elseif (stripos($trimmed, 'x-ratelimit-remaining:') === 0) {
					$value = trim(substr($trimmed, 22));
					if (is_numeric($value)) {
						$rateLimitRemaining = (int) $value;
					}
				} elseif (stripos($trimmed, 'x-ratelimit-reset:') === 0) {
					$value = trim(substr($trimmed, 18));
					if (is_numeric($value)) {
						$rateLimitReset = (int) $value;
					}
				} elseif (stripos($trimmed, 'retry-after:') === 0) {
					$value = trim(substr($trimmed, 12));
					if (is_numeric($value)) {
						$retryAfter = (int) $value;
					}
				}

				return strlen($headerLine);
			},
		]);

		$body = curl_exec($ch);
		if ($body === false) {
			throw new RuntimeException("Network error while calling GitHub API: " . curl_error($ch));
		}

		$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

		$apiMessage = strtolower(extractApiErrorMessage($body));

		$isRateLimited = $statusCode === 429
			|| ($statusCode === 403 && ($rateLimitRemaining === 0 || str_contains($apiMessage, 'rate limit')));

		if (!$isRateLimited) {
			return [$statusCode, $body, $linkHeader];
		}

		$attempt++;
		if ($attempt > MAX_RATE_LIMIT_RETRIES) {
			throw new RuntimeException("GitHub API rate limit exceeded and retry budget exhausted.");
		}

		$waitSeconds = $retryAfter;
		if ($waitSeconds === null && $rateLimitReset !== null) {
			$waitSeconds = max(1, $rateLimitReset - time() + 1);
		}
		if ($waitSeconds === null) {
			$waitSeconds = 60;
		}

		fwrite(STDERR, "\rRate limited. Waiting {$waitSeconds}s before retry ({$attempt}/" . MAX_RATE_LIMIT_RETRIES . ")...");
		sleep($waitSeconds);
	}
}

function extractApiErrorMessage(string $body): string
{
	$decoded = json_decode($body, true);
	if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
		return $decoded['message'];
	}

	return 'Unknown API error';
}
