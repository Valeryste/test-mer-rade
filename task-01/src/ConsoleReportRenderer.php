<?php

declare(strict_types=1);

namespace src;

final class ConsoleReportRenderer
{
    public static function render(iterable $reportData): void
    {
        printf("%-10s | %-12s | %-10s | %-50s\n", "ЗАКАЗ", "СТАТУС", "РЕШЕНИЕ", "КОММЕНТАРИЙ");

        foreach ($reportData as $row) {
            printf(
                "%-10s | %-12s | %-10s | %-50s\n",
                $row['id'],
                $row['status'],
                $row['decision'],
                $row['comment']
            );
        }
    }
}