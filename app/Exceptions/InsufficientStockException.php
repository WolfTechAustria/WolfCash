<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Der Warenkorb enthält mehr, als noch auf Lager ist.
 */
class InsufficientStockException extends RuntimeException
{
}
