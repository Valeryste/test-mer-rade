<?php

declare(strict_types=1);

namespace src;

final class Logger
{
    private string $logDir;

    public function __construct(string $logDir = __DIR__ . '/logs')
    {
        $this->logDir = rtrim($logDir, '/\\') . '/';
    }

    public function error(string $message): void
    {
        if (! is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        file_put_contents(
            $this->logDir . $this->generateFileName(),
            sprintf("[%s] ERROR: %s\n", date('Y-m-d H:i:s'), $message),
            FILE_APPEND
        );
    }

    private function generateFileName(): string
    {
        return 'logs_' . date('Ymd') . '.log';
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }
}
