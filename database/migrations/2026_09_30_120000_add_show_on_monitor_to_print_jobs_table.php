<?php

use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * Ob ein Bon am Küchenmonitor erscheint, wird beim Anlegen
     * festgehalten. Ein späteres Umstellen des Druckers darf keine
     * alten, längst gedruckten Sofortdruck-Bons hochspülen.
     */
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->boolean('show_on_monitor')
                ->default(false)
                ->after('ready_to_print');

            $table->index(['show_on_monitor', 'production_completed_at']);
        });

        /*
         * Bestand: Produktionsbons ohne Drucker oder mit verzögertem
         * Druck gehören an den Monitor, Sofortdruck-Bons nicht.
         */
        DB::table('print_jobs')
            ->where('type', PrintJob::TYPE_PRODUCTION)
            ->where(fn ($query) => $query
                ->whereNull('printer_id')
                ->orWhereIn(
                    'printer_id',
                    DB::table('printers')
                        ->select('id')
                        ->where('print_trigger', '!=', Printer::PRINT_TRIGGER_IMMEDIATE)
                ))
            ->update(['show_on_monitor' => true]);
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropIndex(['show_on_monitor', 'production_completed_at']);
            $table->dropColumn('show_on_monitor');
        });
    }
};
