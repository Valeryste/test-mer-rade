<?php

declare(strict_types=1);

namespace src;

use Generator;
use InvalidArgumentException;

final class OrderProcessor
{
    private array $catalog;
    private Logger $logger;
    private array $processedOrderIds = [];

    public function __construct(array $catalog, Logger $logger)
    {
        $this->catalog = $catalog;
        $this->logger = $logger;
    }

    //Тут как раз нужно разрулить, чтобы можно было бы получить данные по HTTP
    //Сделать универсальный метод который может получать данные с раных источников
    public function processFromJson(string $filePath): Generator
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Файл заказов не найден: {$filePath}");
        }

        $jsonData = json_decode(file_get_contents($filePath), true);
        if (!$jsonData || !isset($jsonData['data']['orders'])) {
            $this->logger->error("Невалидный формат JSON или пустая структура в файле: {$filePath}");
            return;
        }

        foreach ($jsonData['data']['orders'] as $orderData) {
            $row = $this->processSingleOrder($orderData);

            if ($row !== null) {
                yield $row;
            }
        }
    }

    private function processSingleOrder(array $order): ?array
    {
        $orderId = $order['id'] ?? 'UNKNOWN';
        $status = $order['status'] ?? 'new';

        if ($orderId === 'UNKNOWN') {
            $this->logger->error("Критическая ошибка: Входящий заказ не имеет ID. Данные: " . json_encode($order, JSON_UNESCAPED_UNICODE));
            return null;
        }

        if ($status === 'cancelled') {
            return $this->makeReportRow($orderId, $status, "ИГНОР", "Заказ отменен покупателем");
        }

        if (isset($this->processedOrderIds[$orderId])) {
            return $this->makeReportRow($orderId, $status, "ОТКЛОНЕН", "Дубликат заказа (уже обработан ранее)");
        }
        $this->processedOrderIds[$orderId] = true;

        if (empty($order['items'])) {
            return $this->makeReportRow($orderId, $status, "ОТКЛОНЕН", "В заказе нет товаров (пустой список позиций)");
        }

        $orderOk = true;
        $comments = [];

        foreach ($order['items'] as $item) {
            if (!isset($item['sku'], $item['price'])) {
                $missingField = !isset($item['sku']) ? 'sku' : 'price';
                $this->logger->error("Заказ [{$orderId}]: Пропущено обязательное поле '{$missingField}' в позиции. Данные: " . json_encode($item, JSON_UNESCAPED_UNICODE));
                $orderOk = false;
                $comments[] = "Ошибка данных (нет SKU/Цены)";
                continue;
            }

            $sku = strtoupper(trim($item['sku']));
            $orderPrice = (float) $item['price'];
            $qty = (int) ($item['quantity'] ?? 1);

            if (!isset($this->catalog[$sku])) {
                $orderOk = false;
                $comments[] = "Артикул [{$sku}] не найден в базе 1С";
                continue;
            }

            $catalogItem = $this->catalog[$sku];

            if (abs($orderPrice - $catalogItem['price']) > 0.001) {
                $orderOk = false;
                $comments[] = "Разница цены [{$sku}]: маркетплейс = {$orderPrice}, 1С = {$catalogItem['price']}";
            }

            if ($catalogItem['stock'] < $qty) {
                $orderOk = false;
                $comments[] = "Дефицит [{$sku}]: нужно {$qty} шт, на складе осталось {$catalogItem['stock']} шт";
            } elseif ($orderOk) {
                $this->catalog[$sku]['stock'] -= $qty;
            }
        }

        $decision = $orderOk ? "В 1С" : "СТОП";
        $finalComment = implode('; ', $comments) ?: "Все проверки пройдены успешно";

        return $this->makeReportRow($orderId, $status, $decision, $finalComment);
    }


    private function makeReportRow(string $id, string $status, string $decision, string $comment): array
    {
        return compact('id', 'status', 'decision', 'comment');
    }
}
