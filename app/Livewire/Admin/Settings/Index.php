<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Printer;
use App\Models\Setting;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{

    public bool $selfOrderingEnabled = false;
    public bool $automaticReceiptPrintingEnabled = true;

    public bool $receiptReprintingEnabled = true;

    public bool $cardPaymentEnabled = true;

    public bool $voucherPaymentEnabled = true;

    public string $selfOrderingTitle = 'Direkt bestellen';

    public string $selfOrderingSubtitle = 'Scannen · Bestellen · Bezahlen';

    public string $receiptTitle = '';

    public string $receiptIntro = '';

    public ?int $receiptPrinterId = null;

    public ?int $stationaryPrinterId = null;

    public function mount(): void
    {
        $this->automaticReceiptPrintingEnabled =
            Setting::automaticReceiptPrintingEnabled();

        $this->receiptReprintingEnabled =
            Setting::receiptReprintingEnabled();

        $this->cardPaymentEnabled =
            Setting::cardPaymentEnabled();

        $this->voucherPaymentEnabled =
            Setting::voucherPaymentEnabled();

        $this->selfOrderingEnabled =
            Setting::selfOrderingEnabled();

        $this->selfOrderingTitle =
            Setting::selfOrderingTitle();

        $this->selfOrderingSubtitle =
            Setting::selfOrderingSubtitle();

        $this->receiptTitle = Setting::receiptTitle();

        $this->receiptIntro = Setting::receiptIntro();

        // Veraltete IDs (z. B. aus der .env) nicht vorauswählen, sonst blockiert die Validierung das Speichern.
        $this->receiptPrinterId = Printer::query()
            ->whereKey(Setting::receiptPrinterId())
            ->value('id');

        $this->stationaryPrinterId = Printer::query()
            ->whereKey(Setting::stationaryPrinterId())
            ->value('id');
    }

    public function save(): void
    {
        /*
         * Erst validieren, dann speichern: sonst werden die
         * vorherigen Einstellungen bereits übernommen, obwohl
         * das Formular als Ganzes fehlschlägt.
         */
        $this->validate([
            'selfOrderingTitle' => [
                'required',
                'string',
                'max:80',
            ],
            'selfOrderingSubtitle' => [
                'required',
                'string',
                'max:120',
            ],
            'receiptTitle' => [
                'nullable',
                'string',
                'max:32',
            ],
            'receiptIntro' => [
                'nullable',
                'string',
                'max:300',
            ],
            'receiptPrinterId' => [
                'nullable',
                'integer',
                Rule::exists('printers', 'id'),
            ],
            'stationaryPrinterId' => [
                'nullable',
                'integer',
                Rule::exists('printers', 'id'),
            ],
        ]);

        Setting::putValue(
            Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED,
            $this->automaticReceiptPrintingEnabled
        );

        Setting::putValue(
            Setting::RECEIPT_REPRINTING_ENABLED,
            $this->receiptReprintingEnabled
        );

        Setting::putValue(
            Setting::CARD_PAYMENT_ENABLED,
            $this->cardPaymentEnabled
        );

        Setting::putValue(
            Setting::VOUCHER_PAYMENT_ENABLED,
            $this->voucherPaymentEnabled
        );

        Setting::putValue(
            Setting::SELF_ORDERING_ENABLED,
            $this->selfOrderingEnabled
        );

        Setting::putValue(
            Setting::SELF_ORDERING_TITLE,
            trim($this->selfOrderingTitle)
        );

        Setting::putValue(
            Setting::SELF_ORDERING_SUBTITLE,
            trim(
                $this->selfOrderingSubtitle
            )
        );

        Setting::putValue(
            Setting::RECEIPT_TITLE,
            trim($this->receiptTitle)
        );

        Setting::putValue(
            Setting::RECEIPT_INTRO,
            trim($this->receiptIntro)
        );

        Setting::putValue(
            Setting::RECEIPT_PRINTER_ID,
            $this->receiptPrinterId
        );

        Setting::putValue(
            Setting::STATIONARY_PRINTER_ID,
            $this->stationaryPrinterId
        );

        session()->flash(
            'settingsSaved',
            'Die Einstellungen wurden gespeichert.'
        );
    }

    public function render()
    {
        return view(
            'livewire.admin.settings.index',
            [
                'printers' => Printer::query()
                    ->orderBy('name')
                    ->get(),
            ]
        )->layout('components.layouts.app');
    }
}
