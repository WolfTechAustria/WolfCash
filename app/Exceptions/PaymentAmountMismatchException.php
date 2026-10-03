<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Der zu verbuchende Betrag weicht vom erwarteten ab, z. B. weil
 * Positionen während einer Kartenzahlung anderweitig bezahlt oder
 * storniert wurden. Die Buchung wird dann vollständig zurückgerollt.
 */
class PaymentAmountMismatchException extends RuntimeException
{
    public function __construct(
        public readonly float $expected,
        public readonly float $actual,
    ) {
        parent::__construct(sprintf(
            'Zahlungsbetrag %.2f weicht vom erwarteten Betrag %.2f ab.',
            $actual,
            $expected
        ));
    }
}
