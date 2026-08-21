<?php
/**
 * Ratchet check for lodash (`_.`) usage in this repo's JS.
 *
 * CiviCRM core is migrating away from lodash (see CLAUDE.md / dev discussion) but has
 * hundreds of pre-existing call sites. This script prevents the total from growing:
 * a file may keep or reduce its recorded call count, but not exceed it, and a file with
 * no baseline entry may not introduce any lodash usage at all.
 *
 * Usage:
 *   php check-lodash-ratchet.php             Check current usage against the baseline.
 *   php check-lodash-ratchet.php --generate  Rewrite the baseline from current usage.
 */

$root = realpath(__DIR__ . '/../..');
$baselineFile = $root . '/tests/lodash-ratchet-baseline.json';

function countLodashCalls(string $path): int {
  $contents = file_get_contents($path);
  preg_match_all('/(^|[^a-zA-Z0-9_$.])_\.[a-zA-Z]+\(/', $contents, $matches);
  return count($matches[0]);
}

/**
 * Enumerate tracked *.js files via `git ls-files`, not a raw directory walk.
 * Several vendored JS bundles (e.g. `packages/backbone*`, `packages/kcfinder`) are
 * gitignored — they're third-party code we don't own and would never migrate — and
 * a plain filesystem scan can't tell them apart from files that are actually part
 * of this repo. Returns null (rather than failing) if git isn't usable here, since
 * we don't control what the CI environment running this test looks like.
 */
function gitLsFiles(string $root): ?array {
  $output = [];
  exec('git -C ' . escapeshellarg($root) . ' ls-files -- "*.js" 2>/dev/null', $output, $exit);
  return $exit === 0 ? $output : NULL;
}

function countUsage(string $root, array $relPaths): array {
  $usage = [];
  foreach ($relPaths as $relPath) {
    if (str_ends_with($relPath, '.min.js') || !is_file($root . '/' . $relPath)) {
      continue;
    }
    $count = countLodashCalls($root . '/' . $relPath);
    if ($count > 0) {
      $usage[$relPath] = $count;
    }
  }
  ksort($usage);
  return $usage;
}

if (in_array('--generate', $argv, TRUE)) {
  $files = gitLsFiles($root);
  if ($files === NULL) {
    fwrite(STDERR, "git ls-files isn't usable here — --generate needs a real git checkout.\n");
    exit(1);
  }
  $usage = countUsage($root, $files);
  file_put_contents($baselineFile, json_encode($usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
  $total = array_sum($usage);
  echo "Wrote baseline: " . count($usage) . " files, {$total} calls.\n";
  exit(0);
}

if (!file_exists($baselineFile)) {
  fwrite(STDERR, "Missing baseline file: {$baselineFile}\nRun with --generate to create it.\n");
  exit(1);
}

$baseline = json_decode(file_get_contents($baselineFile), TRUE);
if (!is_array($baseline)) {
  fwrite(STDERR, "Could not parse baseline file: {$baselineFile}\n");
  exit(1);
}

$files = gitLsFiles($root);
$degraded = $files === NULL;
// Fall back to only re-checking the files the baseline already knows about — this
// can't catch a brand-new file introducing lodash, but it never blocks CI outright
// just because this particular environment lacks a git checkout/binary.
$usage = countUsage($root, $files ?? array_keys($baseline));

$errors = [];
foreach ($usage as $file => $count) {
  if (!array_key_exists($file, $baseline)) {
    $errors[] = "New lodash usage in a file with no baseline entry: {$file} ({$count} call(s)). "
      . "Don't introduce new lodash usage (see CLAUDE.md) — use native JS instead.";
  }
  elseif ($count > $baseline[$file]) {
    $errors[] = "Lodash usage increased in {$file}: baseline allows {$baseline[$file]}, found {$count}.";
  }
}

if ($errors) {
  fwrite(STDERR, implode("\n", $errors) . "\n");
  exit(1);
}

$total = array_sum($usage);
$baselineTotal = array_sum($baseline);
echo "Lodash ratchet OK: {$total} calls across " . count($usage) . " files "
  . "(baseline allows {$baselineTotal} across " . count($baseline) . " files).\n";
if ($degraded) {
  echo "Note: git ls-files wasn't usable, so this run only re-checked files already in the baseline"
    . " and couldn't detect lodash usage in a brand-new file.\n";
}
if ($total < $baselineTotal) {
  echo "Baseline has slack — consider running --generate to tighten it after removing lodash usage.\n";
}
exit(0);
