<?php

/** Reapply CASAWP's narrowly scoped compatibility backports after Composer. */

$root = dirname(__DIR__, 4);
$patches = json_decode(file_get_contents(__DIR__ . '/vendor-patches.json'), true, 512, JSON_THROW_ON_ERROR);
$pending = [];

// Check every edit before writing anything. A changed upstream file needs review.
foreach ($patches as $relative => $edits) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        throw new RuntimeException('Missing compatibility patch target: ' . $relative);
    }
    $contents = file_get_contents($path);
    foreach ($edits as $edit) {
        $before = substr_count($contents, $edit['from']);
        $after = substr_count($contents, $edit['to']);
        if ($before === $edit['count'] && $after === 0) {
            $contents = str_replace($edit['from'], $edit['to'], $contents);
        } elseif ($before !== 0 || $after !== $edit['count']) {
            throw new RuntimeException('Compatibility patch no longer matches: ' . $relative);
        }
    }
    if ($contents !== file_get_contents($path)) {
        $pending[$path] = $contents;
    }
}

// Composer retains installed metadata when only a package's requirements change.
// Synchronize these local overrides without reinstalling the tracked vendor tree.
$installedPath = $root . '/vendor/composer/installed.json';
$installed = json_decode(file_get_contents($installedPath), true, 512, JSON_THROW_ON_ERROR);
$repository = json_decode(file_get_contents(__DIR__ . '/packages.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($installed['packages'] as &$package) {
    $versions = $repository['packages'][$package['name']] ?? [];
    if (!$versions) {
        continue;
    }
    $override = $versions[$package['version']] ?? null;
    if (!$override || ($override['source'] ?? null) !== ($package['source'] ?? null)) {
        throw new RuntimeException('Review compatibility overrides for ' . $package['name']);
    }
    $package['require'] = $override['require'];
    $package['extra'] = $override['extra'];
}
unset($package);
$installedJson = json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
if ($installedJson !== file_get_contents($installedPath)) {
    $pending[$installedPath] = $installedJson;
}

foreach ($pending as $path => $contents) {
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Could not write compatibility patch target: ' . $path);
    }
}

echo 'CASAWP compatibility patches verified (' . count($pending) . " files updated).\n";
