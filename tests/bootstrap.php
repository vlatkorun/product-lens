<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

$encryptionKeyHex = $_SERVER['APP_ENCRYPTION_KEY'] ?? '';
if (\strlen($encryptionKeyHex) === 64) {
    EncryptedStringType::configure(\hex2bin($encryptionKeyHex));
}
