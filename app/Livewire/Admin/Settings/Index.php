<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use Livewire\Component;

class Index extends Component
{

    public bool $selfOrderingEnabled = false;
    public bool $automaticReceiptPrintingEnabled = true;

    public bool $receiptReprintingEnabled = true;

    public string $selfOrderingTitle = 'Direkt bestellen';

    public string $selfOrderingSubtitle = 'Scannen · Bestellen · Bezahlen';

    public function mount(): void
    {
        $this->automaticReceiptPrintingEnabled =
            Setting::automaticReceiptPrintingEnabled();

        $this->receiptReprintingEnabled =
            Setting::receiptReprintingEnabled();

        $this->selfOrderingEnabled =
            Setting::selfOrderingEnabled();

        $this->selfOrderingTitle =
            Setting::selfOrderingTitle();

        $this->selfOrderingSubtitle =
            Setting::selfOrderingSubtitle();

    }

    public function save(): void
    {
        Setting::putValue(
            Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED,
            $this->automaticReceiptPrintingEnabled
        );

        Setting::putValue(
            Setting::RECEIPT_REPRINTING_ENABLED,
            $this->receiptReprintingEnabled
        );

        session()->flash(
            'settingsSaved',
            'Die Einstellungen wurden gespeichert.'
        );

        Setting::putValue(
            Setting::SELF_ORDERING_ENABLED,
            $this->selfOrderingEnabled
        );

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
            Setting::SELF_ORDERING_TITLE,
            trim($this->selfOrderingTitle)
        );

        Setting::putValue(
            Setting::SELF_ORDERING_SUBTITLE,
            trim(
                $this->selfOrderingSubtitle
            )
        );
    }

    public function render()
    {
        return view(
            'livewire.admin.settings.index'
        )->layout('components.layouts.app');
    }
}
