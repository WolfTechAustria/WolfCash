<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Product;

class PrintService
{
    public function createProductionJobs(Order $order, array $cart): void
    {
        $jobsByPrinter = [];

        foreach ($cart as $item) {
            $product = Product::with('category.printer')
                ->findOrFail($item['id']);

            if ($product->print_mode === Product::PRINT_NONE) {
                continue;
            }

            $printer = $product->category?->printer;

            if (! $printer) {
                continue;
            }

            $printerId = $printer->id;

            $jobsByPrinter[$printerId]['printer_id'] = $printerId;

            if ($product->print_mode === Product::PRINT_SPLIT) {
                for ($i = 1; $i <= $item['quantity']; $i++) {
                    $jobsByPrinter[$printerId]['items'][] = [
                        'name' => $product->name,
                        'quantity' => 1,
                        'price' => $item['price'],
                        'note' => $item['note'] ?? null,
                        'print_mode' => $product->print_mode,
                    ];
                }
            } else {
                $jobsByPrinter[$printerId]['items'][] = [
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'note' => $item['note'] ?? null,
                    'print_mode' => $product->print_mode,
                ];
            }
        }

        foreach ($jobsByPrinter as $job) {
            PrintJob::create([
                'order_id' => $order->id,
                'printer_id' => $job['printer_id'],
                'type' => PrintJob::TYPE_PRODUCTION,
                'status' => PrintJob::STATUS_PENDING,
                'payload' => [
                    'order_id' => $order->id,
                    'table' => $order->table?->number,
                    'items' => $job['items'],
                ],
            ]);
        }
    }
}
