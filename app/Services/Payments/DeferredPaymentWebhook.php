<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A notification was authenticated but does not represent a final settlement that can be
 * applied. The provider should receive a 2xx response so it does not retry invalid or
 * unsupported updates indefinitely.
 */
class DeferredPaymentWebhook extends RuntimeException {}
