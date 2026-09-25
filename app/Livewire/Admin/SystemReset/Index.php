<?php

namespace App\Livewire\Admin\SystemReset;

use App\Services\SystemResetService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Index extends Component
{
    private const CONFIRMATION_PHRASE = 'LÖSCHEN';

    public bool $resetSales = false;

    public bool $resetTables = false;

    public bool $resetProducts = false;

    public bool $resetPrinting = false;

    public bool $resetDevices = false;

    public bool $resetSettings = false;

    public string $confirmationText = '';

    /**
     * Tische/Produkte/Drucker zwingen technisch immer "Umsätze" mit
     * (siehe SystemResetService) — das wird hier sofort sichtbar in
     * der Checkbox nachgezogen, statt nur als Hinweistext daneben zu
     * stehen, damit UI-Zustand und tatsächliche Auswirkung übereinstimmen.
     */
    public function updatedResetTables(bool $value): void
    {
        if ($value) {
            $this->resetSales = true;
        }
    }

    public function updatedResetProducts(bool $value): void
    {
        if ($value) {
            $this->resetSales = true;
        }
    }

    public function updatedResetPrinting(bool $value): void
    {
        if ($value) {
            $this->resetSales = true;
        }
    }

    public function getSalesForcedProperty(): bool
    {
        return $this->resetTables || $this->resetProducts || $this->resetPrinting;
    }

    /**
     * @return array<int, string>
     */
    public function getSelectedCategoriesProperty(): array
    {
        return array_keys(array_filter([
            SystemResetService::CATEGORY_SALES => $this->resetSales,
            SystemResetService::CATEGORY_TABLES => $this->resetTables,
            SystemResetService::CATEGORY_PRODUCTS => $this->resetProducts,
            SystemResetService::CATEGORY_PRINTING => $this->resetPrinting,
            SystemResetService::CATEGORY_DEVICES => $this->resetDevices,
            SystemResetService::CATEGORY_SETTINGS => $this->resetSettings,
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function getEffectiveCategoriesProperty(): array
    {
        return app(SystemResetService::class)
            ->resolveCategories($this->selectedCategories);
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getPreviewCountsProperty(): array
    {
        if ($this->effectiveCategories === []) {
            return [];
        }

        return app(SystemResetService::class)
            ->counts($this->effectiveCategories);
    }

    public function getConfirmationValidProperty(): bool
    {
        return $this->confirmationText === self::CONFIRMATION_PHRASE
            && $this->selectedCategories !== [];
    }

    public function selectAll(): void
    {
        $this->resetSales = true;
        $this->resetTables = true;
        $this->resetProducts = true;
        $this->resetPrinting = true;
        $this->resetDevices = true;
        $this->resetSettings = true;
    }

    public function deselectAll(): void
    {
        $this->resetSales = false;
        $this->resetTables = false;
        $this->resetProducts = false;
        $this->resetPrinting = false;
        $this->resetDevices = false;
        $this->resetSettings = false;
        $this->confirmationText = '';
    }

    public function exportData(SystemResetService $resetService): ?StreamedResponse
    {
        if ($this->effectiveCategories === []) {
            $this->addError(
                'reset',
                'Bitte mindestens einen Bereich auswählen, bevor Daten exportiert werden.'
            );

            return null;
        }

        $export = $resetService->exportData($this->effectiveCategories);

        $filename = 'wolfcash-export-'.now()->format('Y-m-d_H-i-s').'.json';

        return response()->streamDownload(
            function () use ($export): void {
                echo json_encode(
                    $export,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                );
            },
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function confirmReset(SystemResetService $resetService): void
    {
        $this->resetErrorBag('reset');

        if ($this->selectedCategories === []) {
            $this->addError(
                'reset',
                'Bitte mindestens einen Bereich auswählen.'
            );

            return;
        }

        if (! $this->confirmationValid) {
            $this->addError(
                'reset',
                'Bitte "'.self::CONFIRMATION_PHRASE.'" zur Bestätigung eintippen.'
            );

            return;
        }

        $categories = $this->effectiveCategories;
        $counts = $resetService->counts($categories);

        try {
            $resetService->reset($categories);

            /*
             * Erst nach erfolgreichem Reset loggen — sonst würde ein
             * fehlgeschlagener (zurückgerollter) Versuch fälschlich
             * als "durchgeführt" protokolliert.
             */
            Log::warning('System-Reset durchgeführt', [
                'user_id' => auth()->id(),
                'categories' => $categories,
                'counts' => $counts,
            ]);

            session()->flash(
                'success',
                'Die ausgewählten Bereiche wurden erfolgreich zurückgesetzt.'
            );

            $this->deselectAll();
        } catch (Throwable $exception) {
            report($exception);

            $this->addError('reset', $exception->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.system-reset.index')
            ->layout('components.layouts.app');
    }
}
