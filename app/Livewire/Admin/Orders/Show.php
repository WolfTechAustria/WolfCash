<?php

namespace App\Livewire\Admin\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Setting;
use App\Services\PaymentReceiptService;
use Livewire\Component;
use RuntimeException;
use Throwable;

class Show extends Component
{
    public Order $order;

    public ?int $reprintingPaymentId = null;

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

            'items.cancellations.cancelledByUser',

            /*
             * Zahlungsbelegjob und alle bisherigen Ausdrucke
             * für die Statusanzeige und Nachdruckzählung.
             */
            'payments.receiptPrintJob.outputs',
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
