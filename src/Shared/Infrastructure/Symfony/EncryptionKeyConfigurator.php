<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony;

use App\Shared\Infrastructure\Doctrine\Type\EncryptedStringType;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class EncryptionKeyConfigurator implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(APP_ENCRYPTION_KEY)%')]
        private readonly string $encryptionKeyHex,
    ) {
        if (\strlen($this->encryptionKeyHex) !== 64) {
            throw new \RuntimeException(
                'APP_ENCRYPTION_KEY must be a 64-character hex string (32 bytes). '
                . 'Generate one with: php -r "echo sodium_bin2hex(random_bytes(32));"',
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['configure', 2048],
            ConsoleEvents::COMMAND => ['configure', 2048],
        ];
    }

    public function configure(): void
    {
        EncryptedStringType::configure(\hex2bin($this->encryptionKeyHex));
    }
}
