<?php

namespace App\Livewire\Admin\DailySummary;

use App\Models\DailyClosing;
use App\Services\DailyClosingService;
use App\Services\DailySummaryReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Index extends Component
{
    #[Url]
    public string $date = '';

    /**
     * Gerätefilter: '' = alle, Geräte-ID oder
     * DailySummaryReport::NO_DEVICE.
     */
    #[Url]
    public string $device = '';

    public ?DailyClosing $dailyClosing = null;

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = today()->format('Y-m-d');
        }

        $this->loadDailyClosing();
    }

    public function previousDay(): void
    {
        $this->date = $this->selectedDate()
            ->subDay()
            ->format('Y-m-d');

        $this->loadDailyClosing();
    }

    public function nextDay(): void
    {
        $this->date = $this->selectedDate()
            ->addDay()
            ->format('Y-m-d');

        $this->loadDailyClosing();
    }

    public function goToToday(): void
    {
        $this->date = today()->format('Y-m-d');
        $this->loadDailyClosing();

    }

    public function updatedDate(): void
    {
        /*
         * Ungültige Eingaben auf heute zurücksetzen.
         */
        try {
            $this->selectedDate();
        } catch (\Throwable) {
            $this->date = today()->format('Y-m-d');
        }

        $this->loadDailyClosing();
    }

    private function selectedDate(): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d',
            $this->date
        )->startOfDay();
    }

    /**
     * Pro Request nur einmal berechnen — Seite, Kennzahlen und
     * Listen greifen auf denselben Stand zu.
     *
     * @var array<string, mixed>|null
     */
    private ?array $report = null;

    private function report(): array
    {
        return $this->report ??= app(DailySummaryReport::class)->build(
            $this->selectedDate(),
            $this->device
        );
    }

    public function updatedDevice(): void
    {
        $this->report = null;
    }

    public function filterDevice(string $device): void
    {
        $this->device = $device;
        $this->report = null;
    }

    public function getSummaryProperty(): array
    {
        return $this->report()['summary'];
    }

    public function getOpenOrdersProperty()
    {
        return $this->report()['openOrders'];
    }

    public function getPaymentsProperty()
    {
        return $this->report()['payments'];
    }

    /**
     * Exportiert die aktuell angezeigte (ggf. gefilterte) Übersicht.
     */
    public function exportPdf(): StreamedResponse
    {
        $report = $this->report();
        $date = $this->selectedDate();

        $pdf = Pdf::loadView('admin.daily-summary.pdf', [
            ...$report,
            'selectedDate' => $date,
            'dailyClosing' => $this->dailyClosing,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $suffix = $report['deviceLabel'] !== null
            ? '-'.Str::slug($report['deviceLabel'])
            : '';

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'tagesuebersicht-'.$date->format('Y-m-d').$suffix.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function closeDay(
        DailyClosingService $dailyClosingService
    ): void {
        try {
            $closing = $dailyClosingService->close(
                businessDate: $this->selectedDate(),
                userId: auth()->id(),
            );

            $this->dailyClosing = $closing->load(
                'closedByUser'
            );

            session()->flash(
                'success',
                'Der Geschäftstag wurde erfolgreich abgeschlossen.'
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'dailyClosing' => $exception->getMessage(),
            ]);
        }
    }

    private function loadDailyClosing(): void
    {
        $this->dailyClosing = DailyClosing::query()
            ->with('closedByUser')
            ->whereDate(
                'business_date',
                $this->selectedDate()
            )
            ->first();
    }

    public function render()
    {
        return view(
            'livewire.admin.daily-summary.index',
            [
                'summary' => $this->summary,
                'openOrders' => $this->openOrders,
                'payments' => $this->payments,
                'devices' => $this->report()['devices'],
                'deviceLabel' => $this->report()['deviceLabel'],
                'selectedDate' => $this->selectedDate(),
            ]
        )->layout('components.layouts.app');
    }
}
