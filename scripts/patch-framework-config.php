<?php

$root = dirname(__DIR__);
$target = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'laravel'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'database.php';

if (! file_exists($target)) {
    fwrite(STDOUT, 'patch-framework-config: vendor config not found.'.PHP_EOL);

    return;
}

$contents = file_get_contents($target);
if ($contents === false) {
    fwrite(STDOUT, 'patch-framework-config: unable to read vendor config.'.PHP_EOL);

    return;
}

if (strpos($contents, '$sslCaOption') !== false) {
    fwrite(STDOUT, 'patch-framework-config: already patched.'.PHP_EOL);

    return;
}

$eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
$sslCaBlockLines = [
    '$sslCaOption = null;',
    'if (class_exists(\Pdo\Mysql::class)) {',
    '    $sslCaOption = \Pdo\Mysql::ATTR_SSL_CA;',
    '} elseif (defined(\'PDO::MYSQL_ATTR_SSL_CA\')) {',
    '    $sslCaOption = \PDO::MYSQL_ATTR_SSL_CA;',
    '}',
    '',
];
$sslCaBlock = implode($eol, $sslCaBlockLines).$eol;

$marker = 'use Illuminate\Support\Str;'.$eol.$eol;
if (strpos($contents, $marker) === false) {
    fwrite(STDOUT, 'patch-framework-config: marker not found.'.PHP_EOL);

    return;
}

$contents = str_replace($marker, $marker.$sslCaBlock, $contents, $inserted);

$needleLines = [
    '            \'options\' => extension_loaded(\'pdo_mysql\') ? array_filter([',
    '                PDO::MYSQL_ATTR_SSL_CA => env(\'MYSQL_ATTR_SSL_CA\'),',
    '            ]) : [],',
];
$needle = implode($eol, $needleLines);
$replacementLines = [
    '            \'options\' => extension_loaded(\'pdo_mysql\') ? array_filter(',
    '                $sslCaOption ? [$sslCaOption => env(\'MYSQL_ATTR_SSL_CA\')] : []',
    '            ) : [],',
];
$replacement = implode($eol, $replacementLines);

$contents = str_replace($needle, $replacement, $contents, $replaced);

if ($inserted === 0 || $replaced === 0) {
    fwrite(STDOUT, 'patch-framework-config: patch did not apply cleanly.'.PHP_EOL);

    return;
}

$result = file_put_contents($target, $contents);
if ($result === false) {
    fwrite(STDOUT, 'patch-framework-config: unable to write vendor config.'.PHP_EOL);

    return;
}

fwrite(STDOUT, 'patch-framework-config: updated vendor config.'.PHP_EOL);
