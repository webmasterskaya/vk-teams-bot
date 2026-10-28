<?php

declare(strict_types=1);

$failed = false;
$projectRoot = dirname(__DIR__);

foreach (['bin', 'examples', 'src', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot . '/' . $directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'php') {
            continue;
        }

        passthru(PHP_BINARY . ' -n -l ' . escapeshellarg($file->getPathname()), $code);
        $failed = $failed || $code !== 0;
    }
}

exit($failed ? 1 : 0);
