<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Setting;
use App\Services\OrderCancellationService;
use App\Services\PaymentReceiptService;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;
use Throwable;

class Show extends Component
{
    public Order $order;

    public ?int $reprintingPaymentId = null;

    public ?int $cancellationItemId = null;

    public int $cancellationQuantity = 1;

    public string $cancellationReason = '';

    public bool $cancellationPrintTicket = false;

    public function mount(
        Order $order
    ): void {
        $this->order = $order;

        $this->loadOrder();
    }

    public function loadOrder(): void
    {
        $this->order->load([
            'table',

            'items.product',

            'items.device',

            'items.user',

            'items.cancellations.cancelledByUser',

            /*
             * Zahlungsbelegjob und alle bisherigen Ausdrucke
             * für die Statusanzeige und Nachdruckzählung.
             */
            'payments.receiptPrintJob.outputs',

            'payments.device',

            'payments.user',
        ]);
    }

    public function reprintReceipt(
        int $paymentId,
        PaymentReceiptService $receiptService
    ): void {
        $this->resetErrorBag(
            'receiptReprint'
        );

        $payment = Payment::query()
            ->where(
                'order_id',
                $this->order->id
            )
            ->find($paymentId);

        if (! $payment) {
            $this->addError(
                'receiptReprint',
                'Die ausgewählte Zahlung gehört nicht '
                .'zu dieser Bestellung.'
            );

            return;
        }

        $this->reprintingPaymentId =
            $payment->id;

        try {
            $output = $receiptService->reprint(
                $payment
            );

            $isCopy = (bool) data_get(
                $output->payload,
                'is_copy',
                false
            );

            session()->flash(
                'receiptReprintSuccess',
                ($isCopy
                    ? 'Die Belegkopie'
                    : 'Der Zahlungsbeleg')
                .' wurde als Druckausgabe #'
                .$output->id
                .' an die Druckwarteschlange übergeben.'
            );

            $this->loadOrder();
        } catch (RuntimeException $exception) {
            $this->addError(
                'receiptReprint',
                $exception->getMessage()
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->addError(
                'receiptReprint',
                'Der Zahlungsbeleg konnte nicht '
                .'gedruckt werden.'
            );
        } finally {
            $this->reprintingPaymentId = null;
        }
    }

    /**
     * Nachträgliches Storno aus dem Admin, z. B. wenn die Bestellung
     * schon bezahlt und der Tisch an der Kassa nicht mehr offen ist.
     */
    public function openCancellation(int $itemId): void
    {
        $item = $this->order->items->firstWhere('id', $itemId);

        if (! $item || $item->open_quantity <= 0) {
            return;
        }

        $this->cancellationItemId = $item->id;
        $this->cancellationQuantity = $item->open_quantity;
        $this->cancellationReason = '';
        $this->cancellationPrintTicket = false;

        $this->resetValidation();
    }

    public function closeCancellation(): void
    {
        $this->cancellationItemId = null;

        $this->resetValidation();
    }

    public function getCancellationItemProperty(): ?OrderItem
    {
        if ($this->cancellationItemId === null) {
            return null;
        }

        return $this->order->items->firstWhere(
            'id',
            $this->cancellationItemId
        );
    }

    public function confirmCancellation(
        OrderCancellationService $cancellationService
    ): void {
        $item = $this->cancellationItem;

        if (! $item) {
            $this->closeCancellation();

            return;
        }

        $this->validate([
            'cancellationQuantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.$item->open_quantity,
            ],
            'cancellationReason' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'cancellationQuantity.max' =>
                'Es können höchstens '
                .$item->open_quantity
                .' Stück storniert werden.',

            'cancellationReason.required' =>
                'Bitte einen Stornogrund angeben.',
        ]);

        try {
            $cancellationService->cancel(
                item: $item,
                quantity: $this->cancellationQuantity,
                reason: $this->cancellationReason,
                userId: auth()->id(),
                printCancellationTicket: $this->cancellationPrintTicket,
            );
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException
                && ! $exception instanceof InvalidArgumentException) {
                report($exception);
            }

            $this->addError(
                'cancellationReason',
                $exception->getMessage()
            );

            return;
        }

        session()->flash(
            'cancellationSuccess',
            $this->cancellationQuantity.'× '
            .($item->product?->name ?? 'Position')
            .' wurde storniert.'
        );

        $this->closeCancellation();

        $this->order->refresh();

        $this->loadOrder();
    }

    public function getGrossAmountProperty(): float
    {
        return round(
            $this->order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * (int) $item->quantity
            ),
            2
        );
    }

    public function getCancelledAmountProperty(): float
    {
        return round(
            $this->order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * (int) $item->cancelled_quantity
            ),
            2
        );
    }

    public function getPayableAmountProperty(): float
    {
        return round(
            $this->grossAmount
            - $this->cancelledAmount,
            2
        );
    }

    public function getPaidAmountProperty(): float
    {
        return round(
            (float) $this->order
                ->payments
                ->sum('amount'),
            2
        );
    }

    public function getOpenAmountProperty(): float
    {
        return max(
            0,
            round(
                $this->payableAmount
                - $this->paidAmount,
                2
            )
        );
    }

    public function getPrintJobsProperty()
    {
        return PrintJob::query()
            ->with([
                'printer',
                'productionStation',
                'payment',
            ])
            ->where(
                'order_id',
                $this->order->id
            )
            ->latest('created_at')
            ->get();
    }

    public function getPrintOutputsProperty()
    {
        return PrintOutput::query()
            ->with([
                'printer',
                'orderItem.product',
                'printJob.payment',
            ])
            ->whereHas(
                'printJob',
                fn ($query) =>
                $query->where(
                    'order_id',
                    $this->order->id
                )
            )
            ->latest('created_at')
            ->get();
    }

    public function render()
    {
        return view(
            'livewire.admin.orders.show',
            [
                'printJobs' =>
                    $this->printJobs,

                'printOutputs' =>
                    $this->printOutputs,

                /*
                 * Steuert ausschließlich den Button für
                 * manuellen Druck. Der automatische Druck
                 * hat einen eigenen globalen Schalter.
                 */
                'receiptReprintingEnabled' =>
                    Setting::receiptReprintingEnabled(),
            ]
        )->layout(
            'components.layouts.app'
        );
    }
}
