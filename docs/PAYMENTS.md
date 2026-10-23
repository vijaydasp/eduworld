# Payment operations and provider integration

The shipped configuration records pending payment requests and explicitly tells customers that online checkout is unavailable. It never collects card details or simulates payment success. `PAYMENT_GATEWAY=disabled` is the default. Providing API credentials alone does not enable checkout: an approved provider adapter must be implemented, reviewed and registered first.

## Records and access

`payments` contains the payer details, customer-supplied reference, optional administrator-linked application, expected amount, cumulative received amount, channel, provider session reference, status and settlement dates. BHD is stored with three decimal places. Calculations convert strings to integer fils; floating-point values are not used for settlement decisions. The transaction reference derives from the database-generated payment ID inside a transaction.

Public references do not look up or automatically bind applications. The signed receipt link expires in 24 hours, has private/no-store headers, and shows no application documents. Browser query parameters cannot change settlement state. Staff use the authenticated, authorized payment page to link a payment after checking the payer, service and application details.

Administrators and super administrators may search and link payment records. Only super administrators may record funds received offline or record a completed full offline refund. These operations are labeled as offline, audited and never transfer money. The offline refund operation rejects online gateway transactions. Record verification details and the bank/cash receipt reference; do not put credentials, card details or security codes in notes.

Application payment status summarizes its linked payment records. It is not an invoice or general-ledger balance. Link records carefully and avoid representing the same receipt more than once.

## Integrating the approved provider

1. Add an adapter in `app/Services/Payments` implementing `PaymentGatewayInterface`. Use only the approved provider's official SDK/API and its documented verification process. Keep credentials in environment variables; do not store sensitive request/response bodies.
2. Register its class under `payments.gateways` in `config/payments.php`, add the provider's exact HTTPS checkout hostname(s) to `payments.checkout_hosts`, and set `PAYMENT_GATEWAY` to its key. The adapter's `name()` must match that key. `available()` must return false until all required configuration is present.
3. In `initiate`, create a hosted checkout for the stored BHD amount and currency. Use `transaction_reference` as the provider idempotency key. Return a `GatewayCheckout` containing the HTTPS URL and stable provider checkout/session reference. The provider must not depend on a browser return to confirm payment.
4. Configure the provider's POST webhook as `https://your-domain/payments/webhook/{gateway}`. This is the only payment route excluded from CSRF checks. `verifyWebhook()` must authenticate the exact raw request, validate signature time/replay protection and perform server-to-server lookup when required by the provider. Check merchant/account identity and use a stable event ID. Throw `InvalidPaymentWebhook` for anything unverified. Do not construct a verified event directly from untrusted request data.
5. Return `VerifiedPaymentEvent` with the stable event ID, our transaction reference, original checkout reference, original expected amount, cumulative received amount and `BHD`. Full payment requires received amount to equal expected amount. Partial settlement requires a positive received amount below expected amount. Failed and fully refunded events use zero received amount. This version handles full refunds of received funds; a provider offering partial refunds needs an explicit reviewed extension before enabling that feature.
6. Test sandbox success, decline, cancellation, partial settlement, refund, malformed/signature-expired callbacks, wrong merchant/session/amount/currency, concurrent duplicate delivery, duplicate initiation and out-of-order delivery. Confirm the provider retries transient webhook errors, including notifications delivered before the checkout reference is saved or before the settlement preceding a refund.
7. Run payment tests, apply the database migrations, configure HTTPS and a durable queue worker, verify actual email delivery, then perform an approved low-value live transaction and refund before public launch.

## Verification, replay and notifications

`PaymentManager::processWebhook` verifies the provider notification before reading or writing settlement data, then locks the payment inside a database transaction. It checks the provider session, currency, original amount and cumulative received amount. `payment_events` has a unique provider/event constraint and a fingerprint of normalized verified data. Exact replay is a no-op; changed data reusing an event ID is rejected. Stale callbacks cannot downgrade settled funds or reopen a refunded payment. The payment, linked application status, event and audit updates commit together.

Email confirmation is queued after commit only when a full payment is verified or a full offline receipt is explicitly recorded. The receipt values are snapshotted so a later refund does not alter an already queued acknowledgement. Offline emails describe an administrative offline receipt. Pending requests do not send payment-success notifications. Monitor failed jobs and provider webhook retries during operations.

Run the module tests with `php artisan test --compact tests/Feature/PaymentsTest.php`. The signed provider used by those tests exists only in the test file and is never registered in production configuration.
