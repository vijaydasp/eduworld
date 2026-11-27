# COPYandPAY audit and compliance report

Audit date: 27 November 2026  
Scope: HyperPay / OPPWA COPYandPAY in this Laravel 13 / PHP 8.4 application. This audit did not change or implement another payment gateway.

## Summary

The application already implements the main COPYandPAY flow: Laravel creates a checkout with `POST /v1/checkouts`, the customer pays through the official `paymentWidgets.js` script, and a browser return or webhook triggers a server-side status lookup. Only the verified result can settle a payment. The payment widget does not submit card fields to Laravel.

This change adds strict environment, currency, and payment-type checks; records the configured environment and checkout type; retains the provider result description and selected non-secret 3DS outcome metadata; adds payment-widget loading and error feedback; and adds regression coverage. The exact merchant host, credentials, enabled brands, and 3DS enrollment still require confirmation in the merchant account.

## Compliance table

| No. | COPYandPAY Requirement | Official Specification | Existing Implementation | Status | Correction |
|---|---|---|---|---|---|
| 1 | Use COPYandPAY only | Server creates a checkout; official payment widget collects card data. | AFS OPPWA adapter creates a checkout and renders `paymentWidgets.js`. No other payment product was added. | MATCH | None. |
| 2 | Create checkout server-side | HTTPS `POST /v1/checkouts`; Bearer token; form body with entity, amount, currency, and payment type. | `AfsGateway` uses `withToken()`, `asForm()`, and sends `entityId`, amount, BHD, `DB`, transaction ID, and shopper result URL. | MATCH | Environment values are configurable and invalid environment/host or non-`DB` setup disables checkout. |
| 3 | Treat checkout response as session only | A successful prepare response returns checkout `id`; it is not proof of payment. | Result code and checkout ID are checked; ID is attached to the pending payment. | MATCH | Environment and checkout payment type are retained with the payment metadata. |
| 4 | Load official hosted payment widget | Script URL includes `checkoutId`; form uses `class="paymentWidgets"`, `action=shopperResultUrl`, and configured brands. | Official script and widget form are rendered. Card details stay in the provider widget. | MATCH | Added visible loading state, `onError` feedback, script-load failure feedback, and submit-button duplicate protection. |
| 5 | Use widget integrity correctly | Integration guide shows the returned integrity value and `crossorigin="anonymous"`; API reference documents `integrity=true` to calculate and return the SRI hash. | When `AFS_WIDGET_INTEGRITY` is enabled, checkout requests send `integrity=true`; returned SRI is validated, stored, and rendered with `crossorigin="anonymous"`. | MATCH | None. |
| 6 | Verify shopper return server-side | `resourcePath` points to `/v1/checkouts/{checkoutId}/payment`; authenticate the status request and compare returned IDs, amount, currency, brand, and type. | Return path is restricted to the stored checkout ID and expected endpoint. The app sends Bearer auth and configured entity ID, then checks transaction reference, amount, currency, type, and brand. If OPPWA echoes an entity ID it is checked; the documented payment response schema does not require that field. | MATCH | Return query parameters alone cannot settle a payment. |
| 7 | Prevent arbitrary `resourcePath` access | Do not blindly request a browser-supplied path. | Strict path pattern and checkout-ID match are enforced; status URL is built from configured host. | MATCH | None. |
| 8 | Use safe result-code classification | Official result codes distinguish success, review, pending, decline, and technical outcomes. | `AfsResultCode` settles only documented success patterns; known declines fail; pending, review, technical, and unknown codes remain unsettled. | MATCH | Review/unknown codes remain pending for later reconciliation rather than being presented as successful. |
| 9 | Handle payment states | Payment outcome must distinguish completed, failed, pending, and ambiguous outcomes. | Application statuses are `pending`, `paid`, `failed`, `partially_paid`, and `refunded`. Unknown and review results remain `pending`; customer cancellation or a closed browser produces no verified event. | MISMATCH | There is no persisted `cancelled` or `unknown` state; those cases remain pending. A separate state migration and downstream workflow decision would be needed to distinguish them without changing existing payment reporting. |
| 10 | Prevent duplicate payment attempts | A checkout ID can be reused until success or its 30-minute limit; it may produce multiple transactions. | Checkout initiation locks the payment and reuses a recent checkout. Prior checkout references are retained for reconciliation; events are uniquely constrained and replay-checked. | MATCH | None. |
| 11 | Support 3D Secure through COPYandPAY | Widget carries out the issuer challenge when required; final payment status must be verified server-side. 3DS version and enrollment depend on account configuration. | No hand-built 3DS form or undocumented request parameters are used. Widget return is reconciled server-side. Selected status/version/ECI/transaction identifiers are retained; CAVV/AAV (`verificationId`) is not stored. | NOT VERIFIED | Code path is present, but no merchant enrollment evidence or real issuer challenge run is available. Confirm 3DS settings/version with AFS and run the merchant’s test scenario. |
| 12 | Record verified transaction data | Keep internal payment/order link, checkout ID, gateway transaction ID, amount, currency, type, status, result, and timestamps; never retain PAN/CVV. | Payment row holds internal reference, checkout ID, amount/currency/status, provider ID/type/brand/result code/time. Verification time, description, entity and 3DS metadata are held in the JSON response metadata. No card number/CVV fields exist. | MATCH | Metadata is retained in `gateway_response`; it is not split into dedicated columns. |
| 13 | Validate currency and amount | Reconcile provider amount/currency against the original stored payment. | Settlement rejects amount or currency mismatch and checks received amount against the expected amount. BHD gateway conversion refuses values that cannot be represented exactly to two decimals. | MATCH | Customers/admins should use amounts compatible with the merchant’s two-decimal OPPWA amount format; no silent rounding is performed. |
| 14 | Test/live environment selection | Use merchant-provided host and credentials; documented examples include `eu-test.oppwa.com` and `eu-prod.oppwa.com`. | New `PAYMENT_*` variables support environment, URL, token, entity, currency and default type. Only the matching documented EU test/live host is enabled, with HTTPS and host allowlisting. | NOT VERIFIED | Merchant account regional host and credential pair were not provided. Current configuration deliberately refuses other hosts until the host allowlist and environment mapping are reviewed for that account. |
| 15 | Payment brands match merchant entity | `data-brands` must list brands enabled for the merchant entity. | Widget brands come from `AFS_BRANDS`; successful transaction brand must be in that configured list. | NOT VERIFIED | `VISA MASTER` is only the current default/example. Confirm actual entity brands with AFS before enabling live payments. |
| 16 | HTTPS, CSRF, ownership, and safe logging | Use HTTPS; protect state-changing routes; never trust browser success fields; keep credentials server-side. | Gateway endpoints require HTTPS; Laravel web routes retain normal CSRF protection; signed payment pages and requests bind records; logs omit request credentials/card details; webhook signatures are decrypted/authenticated before status lookup. | MATCH | Merchant must serve its own site and webhook over public HTTPS in production. |
| 17 | Timeout and gateway outage handling | Ambiguous status is not success; reconcile before retrying. | Checkout errors leave the payment pending without settlement. Return/webhook status errors do not mark paid; retry reuses recent checkout. | MATCH | Automated background reconciliation for a shopper who never returns is not implemented here; provider webhook delivery is needed for that case. |
| 18 | Payment-page usability | Show amount/currency, loading, errors, pending, success only after verification, and safe retry. | Checkout displays BHD amount/reference and uses a signed receipt that shows success only after verified settlement. Existing pending/failure receipt is explicit. | MATCH | Widget load and widget error messages were added; checkout retry reuses the same recent session. |

## Implementation details

### Backend checkout

`AfsGateway` sends a URL-encoded server-to-server checkout request using Bearer authentication. Payment type is `DB`; the amount comes from the stored payment record; entity ID and credentials are never sent to browser JavaScript. The checkout ID and optional validated SRI value are attached to the payment. Configuration requires `PAYMENT_ENVIRONMENT=test` with the EU test host or `PAYMENT_ENVIRONMENT=live` with the EU production host, plus BHD and `DB`.

### Frontend widget

The signed checkout view loads `https://{configured-host}/v1/paymentWidgets.js?checkoutId=...` and uses the returned integrity value when configured. The form posts to the application’s result URL and shows only `AFS_BRANDS`. Loading, widget errors, and script-load errors are communicated accessibly. No card-number or CVV input is implemented by the merchant.

### Status verification and settlement

The shopper return accepts only the OPPWA checkout payment resource path belonging to the stored checkout. The application uses the configured host and stored checkout ID to fetch status, sending the configured entity ID with Bearer authentication. OPPWA's documented payment response does not require an echoed entity ID; if the field is present it must match. Webhooks are authenticated/decrypted and their merchant entity is checked, then independently checked with OPPWA. Both paths validate merchant transaction reference, payment type, amount, currency, result code, and brand before settlement. The frontend cannot mark a payment paid. Event uniqueness, payment row locks, and stale-event handling protect duplicate and reordered updates.

The COPYandPAY return uses `GET /v1/checkouts/{checkoutId}/payment`. Webhook reconciliation uses the provider payment ID endpoint (`GET /v1/payments/{paymentId}`) after resolving it to the stored checkout. Both calls are server-to-server OPPWA status requests; neither trusts the notification/redirect body as settlement proof.

### 3D Secure

The official COPYandPAY widget owns its challenge flow; the application does not inject manual 3DS parameters. A successful status response can preserve non-secret 3DS outcome metadata. Verification IDs such as CAVV/AAV are excluded. Whether challenges run, and the active 3DS version, cannot be confirmed from repository code and must be checked in the merchant account.

### Database and security

The existing `payments` and `payment_events` tables are reused. The checkout ID is `gateway_reference`; provider transaction ID/type/brand/result code/timestamp use existing columns; environment, checkout type, integrity, verification timestamp, result description, merchant entity and selected 3DS metadata use `gateway_response`. Event IDs are unique per gateway. No PAN, CVV, or 3DS verification secret is persisted.

## Files inspected

- `app/Services/Payments/AfsGateway.php`, `AfsResultCode.php`, `PaymentManager.php`, `VerifiedPaymentEvent.php`, `BhdAmount.php`, `PaymentRequestManager.php`, `RendersCheckoutWidget.php`, `VerifiesCheckoutReturn.php`, and webhook decoder/interfaces.
- `app/Http/Controllers/Public/PaymentController.php`, payment request controllers, and payment/order models and requests.
- `routes/payments.php`, `config/payments.php`, payment migrations, checkout and receipt Blade views.
- `tests/Feature/AfsPaymentTest.php`, `PaymentsTest.php`, `PaymentRequestTest.php`, `StandalonePaymentRequestTest.php`, existing `docs/PAYMENTS.md`, `.gitignore`, and `AGENTS.md`.

## Files modified

- `config/payments.php`
- `app/Services/Payments/AfsGateway.php`
- `app/Services/Payments/PaymentManager.php`
- `app/Services/Payments/VerifiedPaymentEvent.php`
- `resources/views/payments/checkout.blade.php`
- `tests/Feature/AfsPaymentTest.php`
- `.env.example` (new, contains no credentials)
- `docs/COPYANDPAY_COMPLIANCE_REPORT.md` (this report)

## Tests

Direct project PHPUnit results:

- `AfsPaymentTest.php`: **20 passed, 121 assertions** (latest recorded run).
- `PaymentRequestTest.php`: **16 passed, 109 assertions**.
- `StandalonePaymentRequestTest.php`: **18 passed, 164 assertions**.
- `PaymentsTest.php`: **11 passed, 5 failed**. The failing cases use the generic signed test adapter and amounts such as `12.345`; the current worktree’s `BhdAmount::gatewayAmount()` rejects values not exactly representable to two decimals. This is outside the COPYandPAY adapter change and was not changed here.
- `vendor/bin/pint --dirty --format agent`: completed; `AfsGateway.php` was formatted.

Coverage includes checkout request shape, widget rendering, environment mismatch, spoofed browser success fields, wrong checkout path, verified success/decline/pending/technical outcomes, duplicate webhook delivery, amount mismatch, webhook authentication, refund match, and safe 3DS metadata retention. No real gateway charge, issuer 3DS challenge, browser-close simulation against AFS, or live webhook delivery was performed.

## Merchant configuration still required

1. Confirm the exact account host and access token/entity ID pair for each environment. If the account uses another regional host, add it only after confirming it with AFS and updating the explicit environment-to-host check and allowlist.
2. Confirm `AFS_BRANDS` against the enabled entity brands.
3. Confirm the merchant’s checkout response returns the expected SRI value when requested with `integrity=true`.
4. Confirm webhook setup and encryption key with AFS, then test on a reachable HTTPS endpoint.
5. Confirm 3DS enrollment/version and run the AFS test-card challenge, frictionless, declined-authentication, and pending cases.
6. Confirm that payment amounts are authored in two-decimal increments for this OPPWA merchant. The app rejects unsupported precision rather than altering the amount.

## Official references

- [HyperPay Integration Guide](https://www.hyperpay.com/integration-guide/)
- [OPPWA COPYandPAY Integration Guide](https://hyperpay.docs.oppwa.com/tutorials/integration-guide) (current portal path: `/integrations/widget`)
- [COPYandPAY API](https://hyperpay.docs.oppwa.com/integrations/widget/api)
- [OPPWA API Reference](https://hyperpay.docs.oppwa.com/reference/parameters)
- [COPYandPAY API Reference](https://hyperpay.docs.oppwa.com/reference/copyandpay) (the portal currently exposes the widget API separately)
- [Server-to-Server Guide](https://hyperpay.docs.oppwa.com/tutorials/server-to-server)
- [Payment Management](https://hyperpay.docs.oppwa.com/tutorials/manage-payments)
- [OPPWA Result Codes](https://hyperpay.docs.oppwa.com/reference/resultCodes)

## 3DS sandbox audit addendum — 2026-11-27

### Audit scope and conclusion

This addendum audits the observed sandbox behavior: the hosted form accepted a test-card submission, no visible 3DS challenge appeared, the shopper returned to the local receipt, and the payment remained pending. I repeated the supplied challenge-card flow in both INTERNAL and EXTERNAL test modes. I also captured a further INTERNAL run without saving screenshots or card data. The local records created by these attempts remain pending; no payment was marked paid, removed, or reset.

No malformed application parameter or incorrect status endpoint was established. The evidence is insufficient to name the ultimate cause of the absent challenge or the gateway's generic parameter error. No payment behavior was changed on the basis of that ambiguous result. The attached `oppwa(1).pdf` was not present in the supplied attachments or repository, so its page-specific text could not be checked directly. The task's note that page 40 deprecates the legacy COPYandPAY frontend was therefore not independently verified here; the implementation uses the current hosted widget path.

### Parameter and component audit

| Parameter / component | Current implementation | Official expected implementation | Status | Required correction |
|---|---|---|---|---|
| API host and environment | `PAYMENT_ENVIRONMENT=test` maps to `eu-test.oppwa.com`; live maps to `eu-prod.oppwa.com`. Gateway availability rejects a mismatched host/environment. | Use the merchant-assigned test or live host; do not mix credentials or sessions between environments. | CORRECT | Confirm the assigned AFS host and matching test/live entity-token pair in the merchant portal. |
| Checkout request | HTTPS `POST /v1/checkouts`, form body, Bearer token, entity ID, BHD amount, `DB`, and merchant reference. | COPYandPAY prepare-checkout request with required entity, amount, currency and payment type; POST fields go in the body. | CORRECT | None identified. |
| `entityId` | Configured server-side entity ID is sent on checkout and status requests; never rendered to the browser. | Merchant entity ID is required and must match the environment/account. | CORRECT | Confirm that the token and entity ID belong to the same AFS test account. |
| `amount` / `currency` | Uses the stored payment amount formatted to two minor-unit digits and BHD. | Amount and ISO currency are required; amount is a decimal string. | CORRECT | Confirm BHD and two-decimal transaction increments are enabled for this entity. |
| `paymentType` | Uses configured `DB`; checkout is disabled if another type is configured. | `DB` is the debit/purchase type for ordinary immediate payment. | CORRECT | None identified. |
| Merchant references | Sends unique internal `merchantTransactionId` and `merchantInvoiceId`. | `merchantTransactionId` is conditional/required by some receivers; invoice ID is optional. | CORRECT | None identified. |
| Customer/billing data | Sends name, optional email and phone, language, and `billing.country=BH`. Does not send customer IP or full billing address. | 3DS guidance lists browser, IP, contact, and address data as required or recommended depending on field, market, and integration. COPYandPAY's hosted widget supplies card data and browser behavior. | NEEDS MERCHANT CONFIRMATION | Ask AFS which additional risk fields are required for this entity and Bahrain flow. Do not invent shopper data or trust unvalidated proxy IP headers. |
| Widget script and checkout ID | Loads official `{base}/v1/paymentWidgets.js?checkoutId={id}` using the returned checkout ID. | Use the official widget script with the checkout ID. | CORRECT | None identified. |
| Widget integrity | Requests `integrity=true`, validates/stores returned SRI, and renders it with `crossorigin="anonymous"`. | API can return script SRI when integrity is requested; integration guide renders integrity and crossorigin. | CORRECT | None identified. |
| Widget form and brands | Uses `class="paymentWidgets"`, the result URL, and configured brands. | Form action is `shopperResultUrl`; brands must be enabled for the entity. | CORRECT / NEEDS MERCHANT CONFIRMATION | Confirm the merchant's enabled brand list; current default is VISA and MASTER. |
| Widget-side 3DS options | `wpwlOptions` configures style, locale, loading/error callbacks, and duplicate-submit prevention. It does not disable 3DS or set a challenge preference. | COPYandPAY handles its 3DS flow. `threeDSecure.challengeIndicator` is optional and supported for 3DS 2.2+; it expresses a preference, not a guarantee. | CORRECT | For a deterministic simulator test, request `04` only after AFS confirms the entity's 3DS 2.2 support. Do not add legacy `AUTHENTICATION.TYPE` fields. |
| `testMode` | Explicit `INTERNAL`/`EXTERNAL` is sent only on the test host; omitted mode relies on OPPWA's documented INTERNAL default. Both modes were exercised. | INTERNAL uses OPPWA simulators; EXTERNAL forwards to processor test systems; `testMode` is not allowed on production. | CORRECT | Use INTERNAL for the OPPWA 3DS simulator test unless AFS directs otherwise. Confirm external acquirer test support separately. |
| `shopperResultUrl` | Absolute application route from Laravel; local test config currently uses `http://127.0.0.1:8000`. | Absolute shopper return URL; use the merchant's public HTTPS origin for deployed tests/live. | NEEDS MERCHANT CONFIRMATION | Keep loopback only for local browser tests. Set the public HTTPS `APP_URL` before testing from another device or deploying. |
| Browser return path | Allows only `/v1/checkouts/{storedCheckoutId}/payment`; rejects arbitrary paths and mismatched checkout IDs. | Follow the returned resource path using the configured base URL and authentication. | CORRECT | None identified. |
| Checkout status lookup | GET to `baseUrl + resourcePath` (`/v1/checkouts/{checkoutId}/payment`) with Bearer authentication and `entityId` query parameter. | COPYandPAY's documented payment-status operation is `GET /v1/checkouts/{checkoutId}/payment`, with authentication parameters. | CORRECT | AFS must diagnose the provider response described below; no request-shape defect was proven in code. |
| API result vs HTTP status | A 200 HTTP response is parsed by its body result code. Unknown/technical codes stay pending; only verified success settles. | HTTP success alone is not transaction success; inspect the result code and transaction fields. | CORRECT | None identified. |
| `200.300.404` | Logged from the HTTP-200 JSON body returned by the checkout-status lookup. The official result-code table defines it as “invalid or missing parameter.” | Payment status must contain a transaction result; API-level validation errors are not payment success. | NOT VERIFIABLE | AFS must provide the request-level diagnostic identifying which parameter/session condition caused this code. The code itself does not identify the missing field. |
| 3DS result and state updates | Only verified provider status can settle. Selected non-secret 3DS response metadata is retained; authentication secrets are excluded. | Frictionless and challenge results return to the widget flow; merchant verifies final payment status server-side. | CORRECT | Need a successful AFS simulator challenge response to validate end-to-end behavior. |
| Notifications and duplicates | Webhook payload is authenticated/decrypted and entity-checked, then independently verified against AFS. Event uniqueness, locks, amount/currency checks, and stale-event handling are present. | Process authenticated asynchronous updates idempotently and verify provider status. | CORRECT | Confirm live webhook registration and delivery only after the merchant endpoint is on public HTTPS. |
| Legacy frontend | Current code loads the hosted COPYandPAY widget. No direct card POST frontend or manual legacy 3DS form is implemented. | Current COPYandPAY is the JavaScript-hosted widget; the older PAY.ON frontend is deprecated per task-provided note. | CORRECT | The referenced legacy PDF was unavailable for direct page-level comparison. |
| Content Security Policy | Checkout CSP allows the OPPWA test origin for frames, but not an explicit issuer ACS origin. No ACS challenge frame appeared during the captured run, so no blocked ACS request was observed. | 3DS challenge uses browser communication with the issuer ACS; AFS does not publish a static ACS allowlist in the widget guide reviewed. | NOT VERIFIABLE | Do not broaden `frame-src` speculatively. If AFS confirms a challenge frame origin is required, add only the documented/test-observed origin and verify browser CSP logs. |

### Status lookup diagnosis

The observed `200.300.404` came from the return reconciliation path: `PaymentController::gatewayReturn()` calls `PaymentManager::reconcileCheckout()`, which validates the browser-supplied resource path against the stored checkout, then `AfsGateway::status()` sends a GET to the configured AFS test host. Webhook verification uses a separate `GET /v1/payments/{paymentId}` operation and was not the source of the reported shopper-return result.

The request shape in code is the documented checkout-status path, Bearer authorization, and `entityId` query parameter. Checkout creation succeeded on the same test host/entity pair, and the hosted widget loaded the returned checkout ID. The backend received HTTP 200 with a result body containing `200.300.404`; the app correctly left the local payment pending. This response does not prove whether AFS rejected a parameter, could not find a finalized payment session, or encountered account/test-routing behavior. The body-level result is an API error description, not a final decline and not proof of successful 3DS.

### 3DS findings and root-cause assessment

The supplied Mastercard challenge cards match the official OPPWA 3DS Testing Guide. The guide says its enrolled test cards use the 3DS simulator; it also documents `threeDSecure.challengeIndicator=04` as a way to request a challenge. The API reference says the challenge indicator is optional and available with 3DS 2.2 or newer, and the request expresses a preference. The app does not set it, so it leaves challenge selection to the simulator/issuer. That is not itself a COPYandPAY defect.

Observed facts:

- The widget JavaScript, hosted card fields, and widget request loaded from the OPPWA test host.
- The browser submitted to the OPPWA checkout payment endpoint, which responded HTTP 200 with HTML; the browser returned to the application receipt.
- No issuer challenge frame or visible challenge was observed in the browser trace.
- The server's subsequent checkout-status lookup returned a body-level `200.300.404`, so no verified transaction was available to settle.
- The local receipt remained pending and no payment was marked successful.

The exact cause is **not established**. Application-side possibilities still worth checking are whether the entity has 3DS enabled and whether a challenge ACS frame would be blocked by the checkout CSP; the trace did not prove either. The code does not show a wrong status URL, method, entity parameter name, or auth mechanism. The official result-code description is generic and does not reveal the missing parameter. AFS/OPPWA must check entity enrollment and test routing before attributing the issue to the merchant account.

### AFS/OPPWA support questions

Provide AFS the checkout ID, environment, timestamp, and merchant reference through their secure support channel; do not send PAN/CVV in a support ticket. Ask them to confirm:

1. Whether the test entity is enabled for card 3DS, which protocol versions are active, and which connector/MPI handles it.
2. Whether the supplied challenge-card numbers are active on this entity's INTERNAL simulator; whether this card requires `threeDSecure.challengeIndicator=04` or another documented test setup.
3. The exact gateway-side request/response trace for the widget's checkout payment operation and the subsequent status GET, including why the status result is `200.300.404`.
4. Whether EXTERNAL test mode is supported for this entity/acquirer and whether its test cards route through the OPPWA 3DS simulator.
5. Whether the checkout CSP must allow a documented ACS origin for the challenge frame; if so, the exact hostnames for this test environment.
6. Whether `customer.ip`, browser, email, phone, or additional billing data are required for the Bahrain merchant entity's 3DS product.

### Corrected request examples

Sensitive values are redacted. Normal test checkout fields remain:

```text
POST https://eu-test.oppwa.com/v1/checkouts
Authorization: Bearer [test access token]
Content-Type: application/x-www-form-urlencoded

entityId=[test entity]
amount=1.00
currency=BHD
paymentType=DB
merchantTransactionId=[unique merchant reference]
shopperResultUrl=https://[public merchant host]/pay/return/[reference]
integrity=true
testMode=INTERNAL
```

For a diagnostic challenge request only, and only if the test entity supports 3DS 2.2+, AFS documents this optional preference:

```text
threeDSecure.challengeIndicator=04
```

This parameter is not proof that 3DS is enabled and does not itself guarantee a visible challenge. Do not send it to production as a blanket setting without the merchant's SCA policy and AFS confirmation.

### Test plan and acceptance evidence

| Test | Expected result | Evidence required for pass |
|---|---|---|
| Checkout creation | New pending payment receives a usable checkout ID. | Successful OPPWA prepare response; request uses expected entity/environment, BHD amount, and `DB`; no credentials in browser. |
| Widget initialization | Official hosted form renders the stored checkout ID and configured brands. | Browser trace shows widget assets/hosted fields from the correct environment; no card fields posted to Laravel. |
| INTERNAL simulator | OPPWA simulator processes the payment. | `testMode=INTERNAL` (or documented default), matching test entity, and provider result/resource path. |
| EXTERNAL simulator/acquirer | Processor test system handles the flow if enabled. | AFS confirms entity/acquirer external test support and supplies a matching test card/result scenario. |
| Frictionless 3DS | Authentication completes without an issuer challenge. | Final status response contains payment ID, `DB`, expected amount/currency/reference and successful result; 3DS result fields where returned. |
| Challenge 3DS success | Issuer ACS challenge is displayed and completed. | Browser shows ACS challenge frame/navigation; final server-side status is successful and correlated to the stored checkout/order. |
| Challenge 3DS failure | Failed authentication does not settle the order. | Provider final failure result; payment is recorded failed or otherwise non-success per documented result class. |
| Challenge cancellation | Cancelled/abandoned challenge does not settle. | No verified success status; payment stays pending until a final provider status or reconciliation resolves it. |
| Pending/asynchronous result | Payment stays pending until final provider status/webhook. | Pending result code and later authenticated webhook/status reconcile without premature paid state. |
| Status lookup success | Return resource path is queried with correct host, Bearer token, and entity ID. | Authenticated status body matches stored checkout, reference, amount, currency, type and brand. |
| Invalid checkout/payment ID | No unrelated payment is changed. | Provider error is logged safely; local state remains unchanged/pending. |
| Invalid entity/token | Gateway rejects request; no local success. | Expected 401/403 or provider auth error; secrets absent from logs. |
| Duplicate callback | Repeated event is idempotent. | One event record/settlement effect; repeat does not duplicate notifications or payment updates. |
| Browser closed before return | Browser close does not mark paid. | Payment remains pending until independently verified webhook/reconciliation. |

### Files inspected for this addendum

In addition to the prior report's inventory, this read-only audit traced `routes/payments.php`, public payment and payment-request controllers, `AfsGateway.php`, `PaymentManager.php`, `AfsResultCode.php`, `AfsWebhookDecoder.php`, `VerifiedPaymentEvent.php`, payment models and migrations, checkout/receipt/request views, payment configuration, and the AFS/payment feature tests. No payment PHP, Blade, route, migration, credential, or environment file was changed for this addendum; only this report was updated.

### References checked

- [COPYandPAY Integration Guide](https://hyperpay.docs.oppwa.com/tutorials/integration-guide) — checkout preparation, widget script/form, resource path and status lookup.
- [OPPWA API Reference](https://hyperpay.docs.oppwa.com/reference/parameters) — hosts, authentication, test modes, SRI, challenge indicator and parameter semantics.
- [3DS Testing Guide](https://hyperpay.docs.oppwa.com/tutorials/threeDSecure/TestingGuide) — enrolled simulator test cards and documented challenge test behavior.
- [3DS Request Parameters](https://hyperpay.docs.oppwa.com/tutorials/threeDSecure/Parameters) — 3DS risk/browser data, challenge preference and challenge indicator semantics.
- [OPPWA Result Codes](https://hyperpay.docs.oppwa.com/reference/resultCodes) — `200.300.404` means “invalid or missing parameter.”
- The specified `oppwa(1).pdf` was not available among the provided files, so its page-specific text remains unverified.

## UAT sample-card run requested on 2026-11-27

The UAT configuration was checked before this run: the selected gateway was AFS, environment was `test`, host was `eu-test.oppwa.com`, and omitted `testMode` uses OPPWA's documented INTERNAL default. No live host or live credential was used.

I submitted the remaining three supplied Mastercard simulator cards and one official non-3DS Mastercard control card. Each checkout and hosted widget loaded; each widget payment request returned HTTP 200 with an HTML response and navigated back to the local receipt. The app recorded each payment as pending. The three supplied cards did not show a 3DS challenge. The non-3DS control also did not yield a verified transaction, so the issue is not isolated to the challenge screen.

For the recent test records, the local merchant references are `EWP-2026-0000015` through `EWP-2026-0000018`; each row remains pending. The gateway checkout references are stored as checkout IDs. There is **no provider payment transaction ID to save**: provider transaction ID, result code, type, and brand remain unset because no transaction status was returned. OPPWA status reconciliation returned body result code `200.300.404`; read-only v1 and v3 transaction-report queries by merchant reference also returned HTTP 400 with the same body code. I did not substitute the checkout ID or merchant reference for a PSP transaction ID.

No payment was marked paid, deleted, or reset. Temporary browser screenshots containing test card input were removed after the run, and the local PHP server used for the browser test was stopped.
