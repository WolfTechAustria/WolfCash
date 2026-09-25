<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Table;
use App\Services\PaymentReceiptService;
use App\Services\PaymentService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\On;
use Throwable;

class Checkout extends Component
{
    public Table $table;

    public ?Order $order = null;

    /**
     * OrderItem-ID => ausgewählte Menge
     *
     * @var array<int|string, int>
     */
    public array $selectedForPayment = [];

    public bool $paymentFinished = false;

    public ?Payment $lastPayment = null;

    public array $pendingCardSelection = [];

    public ?float $pendingCardAmount = null;

    public ?string $pendingCardMode = null;

    public string $invoiceRecipientName = '';

    public string $invoiceRecipientAddress = '';

    public string $invoiceRecipientVatId = '';

    public ?int $printingReceiptPaymentId = null;

    public function mount(Table $table): void
    {
        $this->table = $table;

        $this->loadOrder();
    }

    public function loadOrder(): void
    {
        $this->order = Order::query()
            ->with([
                'items.product',
                'payments',
            ])
            ->where('table_id', $this->table->id)
            ->where('status', Order::STATUS_OPEN)
            ->first();

        /*
         * Auswahl nach einem Reload bereinigen.
         */
        $this->sanitizeSelection();
    }

    public function getSelectedTotalProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->selectedForPayment as $itemId => $quantity) {
            $item = $this->order->items
                ->firstWhere('id', (int) $itemId);

            if (! $item || $item->paid_at) {
                continue;
            }

            $payableQuantity = min(
                max(0, (int) $quantity),
                $item->open_quantity
            );

            $total += (float) $item->price
                * $payableQuantity;
        }

        return round($total, 2);
    }

    public function getOpenItemsProperty(): Collection
    {
        if (! $this->order) {
            return collect();
        }

        return $this->order->items
            ->filter(
                fn ($item) =>
                    $item->paid_at === null
                    && $item->open_quantity > 0
            )
            ->values();
    }

    public function addToPayment(int $itemId): void
    {
        $item = $this->openItems
            ->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $current = (int) (
            $this->selectedForPayment[$itemId] ?? 0
        );

        /*
         * Stornierte Mengen dürfen nicht ausgewählt werden.
         */
        if ($current >= $item->open_quantity) {
            return;
        }

        $this->selectedForPayment[$itemId] =
            $current + 1;

        $this->resetErrorBag('payment');
    }

    public function removeFromPayment(int $itemId): void
    {
        if (! isset($this->selectedForPayment[$itemId])) {
            return;
        }

        $newQuantity =
            (int) $this->selectedForPayment[$itemId] - 1;

        if ($newQuantity <= 0) {
            unset($this->selectedForPayment[$itemId]);

            return;
        }

        $this->selectedForPayment[$itemId] =
            $newQuantity;
    }

    public function paySelected(
        string $method,
        PaymentService $paymentService
    ): void {
        if (! $this->order) {
            return;
        }

        $this->sanitizeSelection();

        if ($this->selectedForPayment === []) {
            $this->addError(
                'payment',
                'Bitte mindestens eine Position auswählen.'
            );

            return;
        }

        $payment = $paymentService->paySelection(
            $this->order,
            $this->selectedForPayment,
            $method,
            $this->invoiceRecipientPayload()
        );

        if (! $payment) {
            $this->addError(
                'payment',
                'Die Zahlung konnte nicht erstellt werden.'
            );

            return;
        }

        /*
         * Auch bei einer Teilzahlung merken wir uns sofort
         * die konkrete Zahlung.
         */
        $this->lastPayment = $payment;

        $this->selectedForPayment = [];

        $this->loadOrder();

        $this->closeOrderIfFullyPaid();
    }

    public function payOpen(
        string $method,
        PaymentService $paymentService
    ): void {
        if (! $this->order) {
            return;
        }

        $payment = $paymentService->payRemaining(
            $this->order,
            $method,
            $this->invoiceRecipientPayload()
        );

        if (! $payment) {
            $this->addError(
                'payment',
                'Es sind keine verrechenbaren Positionen mehr offen.'
            );

            return;
        }

        $this->lastPayment = $payment;

        $this->selectedForPayment = [];

        $this->loadOrder();

        $this->closeOrderIfFullyPaid();
    }

    /**
     * Druckt den Zahlungsbeleg für eine bereits erfasste Zahlung
     * sofort aus, unabhängig davon, ob der automatische Belegdruck
     * global deaktiviert ist. Damit muss dafür nicht erst die
     * Admin-Oberfläche geöffnet werden.
     */
    public function printReceipt(
        int $paymentId,
        PaymentReceiptService $receiptService
    ): void {
        $this->resetErrorBag('receiptPrint');

        $payment = $this->order?->payments
            ->firstWhere('id', $paymentId);

        if (! $payment) {
            $this->addError(
                'receiptPrint',
                'Die ausgewählte Zahlung gehört nicht zu dieser Bestellung.'
            );

            return;
        }

        $this->printingReceiptPaymentId = $payment->id;

        try {
            $output = $receiptService->reprint($payment);

            $isCopy = (bool) data_get($output->payload, 'is_copy', false);

            session()->flash(
                'success',
                ($isCopy ? 'Die Belegkopie' : 'Der Zahlungsbeleg')
                .' wurde an die Druckwarteschlange übergeben.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->addError(
                'receiptPrint',
                $exception->getMessage()
            );
        } finally {
            $this->printingReceiptPaymentId = null;
        }
    }

    public function getTotalAmountProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        /*
         * Ursprünglich verrechenbarer Gesamtbetrag:
         * stornierte Mengen werden nicht eingerechnet.
         */
        return round(
            $this->order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * $item->open_quantity
            ),
            2
        );
    }

    public function getPaidAmountProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        /*
         * Zahlungen sind die verlässlichste Quelle
         * für den bereits bezahlten Betrag.
         */
        return round(
            (float) $this->order->payments->sum('amount'),
            2
        );
    }

    public function getOpenAmountProperty(): float
    {
        return max(
            0.0,
            round(
                $this->totalAmount - $this->paidAmount,
                2
            )
        );
    }

    /**
     * @return array{name: ?string, address: ?string, vat_id: ?string}|null
     */
    private function invoiceRecipientPayload(): ?array
    {
        $name = trim($this->invoiceRecipientName) ?: null;
        $address = trim($this->invoiceRecipientAddress) ?: null;
        $vatId = trim($this->invoiceRecipientVatId) ?: null;

        if ($name === null && $address === null && $vatId === null) {
            return null;
        }

        return [
            'name' => $name,
            'address' => $address,
            'vat_id' => $vatId,
        ];
    }

    private function sanitizeSelection(): void
    {
        if (! $this->order) {
            $this->selectedForPayment = [];

            return;
        }

        $sanitized = [];

        foreach ($this->selectedForPayment as $itemId => $quantity) {
            $item = $this->order->items
                ->firstWhere('id', (int) $itemId);

            if (
                ! $item
                || $item->paid_at
                || $item->open_quantity <= 0
            ) {
                continue;
            }

            $quantity = min(
                max(0, (int) $quantity),
                $item->open_quantity
            );

            if ($quantity > 0) {
                $sanitized[(int) $itemId] = $quantity;
            }
        }

        $this->selectedForPayment = $sanitized;
    }

    public function getCardPaymentEnabledProperty(): bool
    {
        return Setting::cardPaymentEnabled();
    }

    private function ensureCardPaymentEnabled(): bool
    {
        if ($this->cardPaymentEnabled) {
            return true;
        }

        $this->addError(
            'payment',
            'Kartenzahlung ist derzeit deaktiviert.'
        );

        return false;
    }

    public function startSelectedCardPayment(): void
    {
        if (! $this->order || ! $this->ensureCardPaymentEnabled()) {
            return;
        }

        $this->sanitizeSelection();

        if ($this->selectedForPayment === []) {
            $this->addError(
                'payment',
                'Bitte mindestens eine Position auswählen.'
            );

            return;
        }

        $amount = $this->selectedTotal;

        if ($amount <= 0) {
            $this->addError(
                'payment',
                'Der Zahlungsbetrag ist ungültig.'
            );

            return;
        }

        $this->pendingCardSelection =
            $this->selectedForPayment;

        $this->pendingCardAmount =
            $amount;

        $this->pendingCardMode =
            'selected';

        $this->dispatch(
            'start-native-card-payment',
            orderId: $this->order->id,
            amount: $amount,
            currency: 'EUR',
        );
    }
    public function startRemainingCardPayment(): void
    {
        if (! $this->order || ! $this->ensureCardPaymentEnabled()) {
            return;
        }

        $amount = $this->openAmount;

        if ($amount <= 0) {
            $this->addError(
                'payment',
                'Es sind keine offenen Positionen vorhanden.'
            );

            return;
        }

        $this->pendingCardSelection = [];

        $this->pendingCardAmount =
            $amount;

        $this->pendingCardMode =
            'remaining';

        $this->dispatch(
            'start-native-card-payment',
            orderId: $this->order->id,
            amount: $amount,
            currency: 'EUR',
        );
    }

    #[On('native-card-payment-result')]
    public function handleNativeCardPaymentResult(
        bool $success,
        int $orderId,
        ?string $transactionId = null,
        ?string $error = null,
    ): void {
        if (! $this->order) {
            return;
        }

        if ($this->order->id !== $orderId) {
            $this->addError(
                'payment',
                'Die Kartenzahlung gehört zu einer anderen Bestellung.'
            );

            return;
        }

        if (! $success) {
            $this->addError(
                'payment',
                $error
                ?? 'Kartenzahlung fehlgeschlagen.'
            );

            $this->clearPendingCardPayment();

            return;
        }

        /*
         * Vorerst bewusst NICHT paySelection()
         * oder payRemaining() aufrufen.
         *
         * Hier kommt danach die echte
         * Stripe-Verifikation hin.
         */
    }

    private function clearPendingCardPayment(): void
    {
        $this->pendingCardSelection = [];
        $this->pendingCardAmount = null;
        $this->pendingCardMode = null;
    }

    private function closeOrderIfFullyPaid(): void
    {
        if (! $this->order) {
            return;
        }

        /*
         * Nicht anhand der Anzahl der Datensätze prüfen.
         * Vollständig stornierte, unbezahlte Datensätze dürfen
         * das Schließen der Bestellung nicht verhindern.
         */
        if ($this->openAmount > 0.009) {
            return;
        }

        $this->order->update([
            'status' => Order::STATUS_PAID,
        ]);

        $this->table->update([
            'status' => 'free',
        ]);


        $this->paymentFinished = true;
    }

    public function backToPos()
    {
        return redirect($this->backUrl);
    }

    /**
     * Rücksprung zur richtigen Kasse: stationäre Kassen zurück auf
     * ihr eigenes Produktraster, Kellner zur Tischauswahl.
     */
    public function getBackUrlProperty(): string
    {
        return $this->table->is_stationary
            ? route('pos.stationary', $this->table, false)
            : route('pos.index', [], false);
    }

    public function render()
    {
        return view('livewire.pos.checkout')
            ->layout('components.layouts.app');
    }
}
