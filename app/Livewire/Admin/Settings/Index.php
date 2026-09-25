<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use Livewire\Component;

class Index extends Component
{

    public bool $selfOrderingEnabled = false;
    public bool $automaticReceiptPrintingEnabled = true;

    public bool $receiptReprintingEnabled = true;

    public bool $cardPaymentEnabled = true;

    public string $selfOrderingTitle = 'Direkt bestellen';

    public string $selfOrderingSubtitle = 'Scannen · Bestellen · Bezahlen';

    public function mount(): void
    {
        $this->automaticReceiptPrintingEnabled =
            Setting::automaticReceiptPrintingEnabled();

        $this->receiptReprintingEnabled =
            Setting::receiptReprintingEnabled();

        $this->cardPaymentEnabled =
            Setting::cardPaymentEnabled();

        $this->selfOrderingEnabled =
            Setting::selfOrderingEnabled();

        $this->selfOrderingTitle =
            Setting::selfOrderingTitle();

        $this->selfOrderingSubtitle =
            Setting::selfOrderingSubtitle();

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

        session()->flash(
            'settingsSaved',
            'Die Einstellungen wurden gespeichert.'
        );
    }

    public function render()
    {
        return view(
            'livewire.admin.settings.index'
        )->layout('components.layouts.app');
    }
}
