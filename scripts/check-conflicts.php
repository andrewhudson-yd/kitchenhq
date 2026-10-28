<?php

// Fail even when conflict markers have already been committed to Git.
$root = dirname(__DIR__);
$roots = ['app', 'bootstrap', 'config', 'database', 'deploy', 'public', 'resources', 'routes', 'scripts', 'tests', '.github'];
$extensions = ['php', 'css', 'js', 'json', 'yml', 'yaml', 'md', 'html', 'sh', 'txt', 'svg'];
$failures = [];
foreach ($roots as $folder) {
    $directory = $root.DIRECTORY_SEPARATOR.$folder;
    if (! is_dir($directory)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, 'bootstrap/cache/')) {
            continue;
        }
        foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $index => $line) {
            if (preg_match('/^(?:<{7}(?: .*)?|={7}|>{7}(?: .*)?|\|{7}(?: .*)?)$/', rtrim($line, "\r"))) {
                $failures[] = $relative.':'.($index + 1);
            }
        }
    }
}
if ($failures) {
    fwrite(STDERR, "Unresolved merge markers found:\n".implode("\n", $failures)."\n");
    exit(1);
}
echo "No unresolved merge markers found.\n";
