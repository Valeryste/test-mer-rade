<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use src\CatalogParser;
use src\ConsoleReportRenderer;
use src\Logger;
use src\OrderProcessor;



$ordersPath = $argv[1] ?? __DIR__ . '/data/orders.json';
$catalogPath = $argv[2] ?? __DIR__ . '/data/nomenclature_1c.csv';

if (!file_exists($ordersPath) || !file_exists($catalogPath)) {
    echo "Ошибка: Файлы данных не найдены.\n";
    echo "Положите их в data/ или укажите пути вручную:\n";
    echo "Использование: php index.php [путь_к_orders.json] [путь_к_nomenclature_1c.csv]\n";
    exit(1);
}

try {
    $logger = new Logger(__DIR__ . '/logs');
    $parser = new CatalogParser($logger);

    $catalog = $parser->parse($catalogPath);
    $processor = new OrderProcessor($catalog, $logger);

    ConsoleReportRenderer::render($processor->processFromJson($ordersPath));

} catch (Exception $e) {
    echo "Критический сбой приложения: " . $e->getMessage() . "\n";
    exit(1);
}
