<?php

declare(strict_types=1);

$projectDirectory = dirname(__DIR__);
$environmentFile = $projectDirectory . '/.env';
$fileEnvironment = is_file($environmentFile)
    ? (parse_ini_file($environmentFile, false, INI_SCANNER_RAW) ?: [])
    : [];

$setting = static function (string $name, string $default = '') use ($fileEnvironment): string {
    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    return isset($fileEnvironment[$name]) ? (string) $fileEnvironment[$name] : $default;
};

$database = $setting('DB_DATABASE', 'scal_e_cdp');
$host = $setting('DB_HOST', 'db');
$port = $setting('DB_PORT', '3306');
$username = $setting('DB_USERNAME', 'scal_e');
$password = $setting('DB_PASSWORD', '');
$timezone = $setting('TZ', 'Africa/Nairobi');

$temporaryArchive = null;
$errorFile = null;
$gzip = null;
$process = null;
$pipes = [];
$exitCode = 0;

try {
    if ($database === '' || $username === '' || !ctype_digit($port)) {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $timestamp = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('YmdHis');
    $safeDatabaseName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $database) ?: 'database';
    $exportDirectory = $projectDirectory . '/exports';

    if (!is_dir($exportDirectory) && !mkdir($exportDirectory, 0750, true) && !is_dir($exportDirectory)) {
        throw new RuntimeException('Could not create the exports directory.');
    }

    $archivePath = sprintf('%s/%s_%s.sql.gz', $exportDirectory, $safeDatabaseName, $timestamp);
    $temporaryArchive = tempnam($exportDirectory, '.database-export-');
    $errorFile = tempnam(sys_get_temp_dir(), 'database-export-error-');

    if ($temporaryArchive === false || $errorFile === false) {
        throw new RuntimeException('Could not create temporary export files.');
    }

    $gzip = gzopen($temporaryArchive, 'wb9');
    if ($gzip === false) {
        throw new RuntimeException('Could not open the compressed archive for writing.');
    }

    $command = [
        'mysqldump',
        '--host=' . $host,
        '--port=' . $port,
        '--user=' . $username,
        '--single-transaction',
        '--no-tablespaces',
        '--databases',
        $database,
    ];

    $environment = getenv();
    $environment = is_array($environment) ? $environment : [];
    $environment['MYSQL_PWD'] = $password;

    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $errorFile, 'w'],
        ],
        $pipes,
        $projectDirectory,
        $environment
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start mysqldump.');
    }

    fclose($pipes[0]);

    $streamError = false;
    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 8192);
        if ($chunk === false) {
            $streamError = true;
            break;
        }

        if ($chunk !== '' && gzwrite($gzip, $chunk) !== strlen($chunk)) {
            $streamError = true;
            break;
        }
    }

    fclose($pipes[1]);
    unset($pipes[0], $pipes[1]);
    $processExitCode = proc_close($process);
    $process = null;
    $gzipClosed = gzclose($gzip);
    $gzip = null;

    $errorOutput = trim((string) file_get_contents($errorFile));
    if ($streamError || !$gzipClosed || $processExitCode !== 0) {
        throw new RuntimeException($errorOutput !== '' ? $errorOutput : 'Database export failed.');
    }

    if (!rename($temporaryArchive, $archivePath)) {
        throw new RuntimeException('Could not finalize the compressed archive.');
    }

    $temporaryArchive = null;
    printf("Database export created: %s\n", $archivePath);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database export failed: ' . $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }

    if (is_resource($gzip)) {
        gzclose($gzip);
    }

    if (is_string($temporaryArchive) && is_file($temporaryArchive)) {
        unlink($temporaryArchive);
    }

    if (is_string($errorFile) && is_file($errorFile)) {
        unlink($errorFile);
    }
}

exit($exitCode);