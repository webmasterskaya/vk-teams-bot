<?php

declare(strict_types=1);

$failed = false;

foreach (['examples', 'src', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $directory, FilesystemIterator::SKIP_DOTS),
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
