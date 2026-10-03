<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Fehler im Kartenzahlungsablauf. Die Meldung ist für das
 * Servicepersonal bestimmt und wird in der Kasse angezeigt.
 */
class CardPaymentException extends RuntimeException {}
