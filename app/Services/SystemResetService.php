<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Löscht Veranstaltungsdaten kategorienweise, damit WolfCash für eine
 * neue Veranstaltung (ggf. eines anderen Vereins) oder beim Verleihen
 * des Systems ohne manuellen DB-Zugriff zurückgesetzt werden kann.
 *
 * Wegen der Fremdschlüssel-Kaskaden im Schema erzwingen "tables",
 * "products" und "printing" technisch immer auch "sales" (siehe
 * resolveCategories()) — sonst blieben verwaiste/inkonsistente
 * Bestelldaten zurück oder die Löschung würde an einem restrict-FK
 * scheitern (self_orders.table_id, print_outputs.printer_id).
 */
class SystemResetService
{
    public const CATEGORY_SALES = 'sales';

    public const CATEGORY_TABLES = 'tables';

    public const CATEGORY_PRODUCTS = 'products';

    public const CATEGORY_PRINTING = 'printing';

    public const CATEGORY_DEVICES = 'devices';

    public const CATEGORY_SETTINGS = 'settings';

    /**
     * Kategorien, deren Auswahl zwingend auch "sales" mit einbezieht.
     */
    private const REQUIRES_SALES = [
        self::CATEGORY_TABLES,
        self::CATEGORY_PRODUCTS,
        self::CATEGORY_PRINTING,
    ];

    /**
     * @param array<int, string> $selected
     * @return array<int, string>
     */
    public function resolveCategories(array $selected): array
    {
        $categories = $selected;

        foreach (self::REQUIRES_SALES as $category) {
            if (
                in_array($category, $selected, true)
                && ! in_array(self::CATEGORY_SALES, $categories, true)
            ) {
                $categories[] = self::CATEGORY_SALES;
            }
        }

        return array_values(array_unique($categories));
    }

    /**
     * @param array<int, string> $categories
     * @return array<string, array<string, int>>
     */
    public function counts(array $categories): array
    {
        $categories = $this->resolveCategories($categories);

        $counts = [];

        if (in_array(self::CATEGORY_SALES, $categories, true)) {
            $counts[self::CATEGORY_SALES] = [
                'Bestellungen' => DB::table('orders')->count(),
                'Zahlungen' => DB::table('payments')->count(),
                'Self-Order-Bestellungen' => DB::table('self_orders')->count(),
                'Tagesabschlüsse' => DB::table('daily_closings')->count(),
            ];
        }

        if (in_array(self::CATEGORY_TABLES, $categories, true)) {
            $counts[self::CATEGORY_TABLES] = [
                'Tische' => DB::table('tables')->count(),
            ];
        }

        if (in_array(self::CATEGORY_PRODUCTS, $categories, true)) {
            $counts[self::CATEGORY_PRODUCTS] = [
                'Produkte' => DB::table('products')->count(),
                'Kategorien' => DB::table('product_categories')->count(),
                'Produktgruppen' => DB::table('product_groups')->count(),
            ];
        }

        if (in_array(self::CATEGORY_PRINTING, $categories, true)) {
            $counts[self::CATEGORY_PRINTING] = [
                'Drucker' => DB::table('printers')->count(),
                'Arbeitsplätze' => DB::table('production_stations')->count(),
            ];
        }

        if (in_array(self::CATEGORY_DEVICES, $categories, true)) {
            $counts[self::CATEGORY_DEVICES] = [
                'Geräte' => DB::table('devices')->count(),
            ];
        }

        if (in_array(self::CATEGORY_SETTINGS, $categories, true)) {
            $counts[self::CATEGORY_SETTINGS] = [
                'Gespeicherte Einstellungen' => DB::table('settings')->count(),
            ];
        }

        return $counts;
    }

    /**
     * @param array<int, string> $categories
     * @return array<string, mixed>
     */
    public function exportData(array $categories): array
    {
        $categories = $this->resolveCategories($categories);

        $data = [];

        if (in_array(self::CATEGORY_SALES, $categories, true)) {
            $data['orders'] = DB::table('orders')->get();
            $data['order_items'] = DB::table('order_items')->get();
            $data['order_item_cancellations'] = DB::table('order_item_cancellations')->get();
            $data['payments'] = DB::table('payments')->get();
            $data['print_jobs'] = DB::table('print_jobs')->get();
            $data['print_outputs'] = DB::table('print_outputs')->get();
            $data['self_orders'] = DB::table('self_orders')->get();
            $data['self_order_items'] = DB::table('self_order_items')->get();
            $data['table_order_sessions'] = DB::table('table_order_sessions')->get();
            $data['daily_closings'] = DB::table('daily_closings')->get();
        }

        if (in_array(self::CATEGORY_TABLES, $categories, true)) {
            $data['tables'] = DB::table('tables')->get();
        }

        if (in_array(self::CATEGORY_PRODUCTS, $categories, true)) {
            $data['products'] = DB::table('products')->get();
            $data['product_categories'] = DB::table('product_categories')->get();
            $data['product_groups'] = DB::table('product_groups')->get();
        }

        if (in_array(self::CATEGORY_PRINTING, $categories, true)) {
            $data['printers'] = DB::table('printers')->get();
            $data['production_stations'] = DB::table('production_stations')->get();
        }

        if (in_array(self::CATEGORY_DEVICES, $categories, true)) {
            $data['devices'] = DB::table('devices')->get();
        }

        if (in_array(self::CATEGORY_SETTINGS, $categories, true)) {
            $data['settings'] = DB::table('settings')->get();
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'categories' => $categories,
            'data' => $data,
        ];
    }

    /**
     * @param array<int, string> $categories
     */
    public function reset(array $categories): void
    {
        /*
         * Abhängigkeiten werden hier nochmal aufgelöst, statt uns
         * darauf zu verlassen, dass der Aufrufer das schon gemacht
         * hat — sonst könnte ein Aufruf mit nur "tables" an der
         * self_orders.table_id-Restriktion scheitern.
         */
        $categories = $this->resolveCategories($categories);

        DB::transaction(function () use ($categories): void {
            /*
             * Reihenfolge ist wichtig: self_orders muss vor
             * table_order_sessions/tables weg (restrictOnDelete),
             * printing muss nach sales laufen (print_outputs.printer_id
             * ist restrictOnDelete).
             */
            if (in_array(self::CATEGORY_SALES, $categories, true)) {
                DB::table('self_orders')->delete();
                DB::table('table_order_sessions')->delete();
                DB::table('orders')->delete();
                DB::table('daily_closings')->delete();
            }

            if (in_array(self::CATEGORY_PRODUCTS, $categories, true)) {
                DB::table('products')->delete();
                DB::table('product_categories')->delete();
                DB::table('product_groups')->delete();
            }

            if (in_array(self::CATEGORY_TABLES, $categories, true)) {
                DB::table('tables')->delete();
            }

            if (in_array(self::CATEGORY_PRINTING, $categories, true)) {
                DB::table('printers')->delete();
                DB::table('production_stations')->delete();
            }

            if (in_array(self::CATEGORY_DEVICES, $categories, true)) {
                try {
                    DB::table('devices')->delete();
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        'Geräte konnten nicht gelöscht werden, da noch Zahlungen '
                        .'einem Gerät zugeordnet sind. Bitte zusätzlich "Umsätze '
                        .'& Bestellungen" zurücksetzen.',
                        previous: $exception
                    );
                }
            }

            if (in_array(self::CATEGORY_SETTINGS, $categories, true)) {
                DB::table('settings')->delete();

                foreach ([
                    Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED,
                    Setting::RECEIPT_REPRINTING_ENABLED,
                    Setting::SELF_ORDERING_ENABLED,
                    Setting::SELF_ORDERING_TITLE,
                    Setting::SELF_ORDERING_SUBTITLE,
                ] as $key) {
                    Cache::forget('setting:'.$key);
                }
            }
        });
    }
}
