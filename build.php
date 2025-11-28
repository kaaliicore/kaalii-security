<?php

$pharFile = 'core.phar';
if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, $pharFile);
$phar->startBuffering();

// Include all files from src/
$phar->buildFromDirectory(__DIR__ . '/src', '/\.php$/');

// Add composer.json for autoloading
$phar->addFile(__DIR__ . '/composer.json', 'composer.json');

// Optional: compress to reduce file size
$phar->compressFiles(Phar::GZ);

$phar->setStub(<<<'STUB'
<?php
Phar::mapPhar('core.phar');
require 'phar://core.phar/composer.json';
__HALT_COMPILER();
STUB
);

$phar->stopBuffering();

echo "core.phar built successfully\n";
