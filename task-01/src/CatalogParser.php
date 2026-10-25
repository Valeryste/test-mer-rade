<?php

declare(strict_types=1);

namespace src;

use RuntimeException;

final class CatalogParser
{
    private Logger $logger;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
    }

    public function parse(string $filePath): array
    {
        $catalog = [];

        $handle = fopen($filePath, 'r');

        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 6) {
                $this->logger->error("Некорректная строка номенклатуры: " . implode(';', $row));
                continue;
            }

            $sku = strtoupper(trim($row[1]));
            $name = $row[2];
            $price = $this->normalizeNumber($row[4]);
            $stock = $this->normalizeNumber($row[5]);

            if (isset($catalog[$sku])) {
                $catalog[$sku]['stock'] += $stock;
                continue;
            }

            $catalog[$sku] = [
                'name'  => $name,
                'price' => $price,
                'stock' => $stock,
            ];
        }

        fclose($handle);
        return $catalog;
    }

    private function normalizeNumber(string $numberStr): float
    {
        $clean = str_replace([' ', ','], ['', '.'], $numberStr);
        return (float) $clean;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }
}