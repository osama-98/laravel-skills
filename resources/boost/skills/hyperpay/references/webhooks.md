# Webhooks

Sources:
- https://hyperpay.docs.oppwa.com/tutorials/webhooks
- https://hyperpay.docs.oppwa.com/support/webhooks

## Configuration

Webhooks are configured in the merchant portal under **Administration → Webhooks**, not via a
request parameter. The `notificationUrl` request parameter is deprecated.

| Setting | Values |
|---------|--------|
| URL | Public HTTPS endpoint |
| Types | `PAYMENTS`, `REGISTRATIONS`, `SCHEDULES`, `RISKS` |
| Fields | `ALL` or `NON_CUSTOMER_DATA` |
| Secret | 64-character hex string (256-bit key) |
| Wrapper | None (raw hex body) or JSON |
| Emails | Recipients of the daily failure summary |

### Entity scope

A webhook can sit at any level of the entity hierarchy and receives events for its own entity and
every descendant. The same notification goes to **every active webhook configured at or above** the
entity, so one payment can reach several URLs — another source of duplicates.

### Activation — "Click to Test"

New webhooks are **inactive** and receive nothing until "Click to Test" succeeds. It sends a dummy
notification and verifies that the URL is reachable, the firewall lets the traffic in, the server
answers 2xx, and the payload is received and decrypted. A failed test leaves the webhook inactive.
The dummy's content is not documented: answer it 2xx even when your validation rejects it.

### Certificates (UAT → Production)

The platform does not dynamically trust external CAs; Root and Intermediate CAs must be on its trust
store. A certificate from an already-trusted CA works at once. Otherwise:

1. Configure the URL in UAT and run Click to Test.
2. If the CA is untrusted, send the webhook URL to support; they validate it in UAT.
3. The same CA is promoted to Production, typically within one business day.
4. Run Click to Test again in Production.

Self-signed certificates are rejected. Validating in UAT first is mandatory, not optional.

## Incoming request

- Method: `POST`, HTTPS with TLS 1.2+
- Content-Type: `text/plain` (raw hex body) or `application/json` (`{"encryptedBody":"…"}`)
- Body: AES-256-GCM ciphertext, hex-encoded

| Aspect | Value |
|--------|-------|
| Algorithm | AES-256-GCM |
| Key | 64-char hex secret from the portal → 32 raw bytes |
| Padding | None |
| IV | HTTP header `X-Initialization-Vector` (hex) |
| Auth tag | HTTP header `X-Authentication-Tag` (hex) |

## Decryption

Both wrapper settings hit the same endpoint, so the ciphertext has to be extracted before anything
touches hex, and every hex string has to be validated before conversion.

```php
$body      = request()->getContent();
$json      = json_decode($body, true);
$hexCipher = is_array($json) && isset($json['encryptedBody']) ? $json['encryptedBody'] : $body;

$bin = static fn (string $hex): ?string =>
    $hex !== '' && strlen($hex) % 2 === 0 && ctype_xdigit($hex) ? hex2bin($hex) : null;

$cipher  = $bin($hexCipher);
$key     = $bin($webhookSecretKey);                                  // 64-char hex → 32 bytes
$iv      = $bin(request()->header('X-Initialization-Vector', ''));
$authTag = $bin(request()->header('X-Authentication-Tag', ''));

if ($cipher === null || $key === null || $iv === null || $authTag === null) {
    return response()->make('invalid hex');   // still 2xx — this delivery can never succeed
}

$decrypted = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $authTag);
$payload   = json_decode($decrypted, true);
```

**Never pass `hex2bin()` straight into `openssl_decrypt()`.** `hex2bin()` returns `false` on non-hex
input, and under `declare(strict_types=1)` — the default in Laravel apps — handing `false` to a
`string` parameter of `openssl_decrypt()` raises a `TypeError`. That escapes as an HTTP **500**,
which contradicts the always-2xx rule below and buys the full 30-day retry ladder for a delivery
that will never succeed. Malformed input is a 2xx with a diagnostic body, never a 500.

Two real inputs reach that path: a JSON-wrapped delivery (whose body is JSON, not hex) and any
truncated or malformed body or header.

`openssl_decrypt` validates the auth tag; a `false` return means tampering or a wrong key — log and
drop the request rather than retrying, and still answer 2xx.

The docs' own PHP example does pass `hex2bin()` straight into `openssl_decrypt()`. Do not copy it;
it is written for a sample with known-good input.

### Known-answer vector

From the Decryption tab of the docs — use it to prove a decryptor before trusting it with real
deliveries (verified with PHP 8.4 `openssl_decrypt`):

| Input | Value |
|-------|-------|
| Key | `000102030405060708090a0b0c0d0e0f000102030405060708090a0b0c0d0e0f` |
| `X-Initialization-Vector` | `000000000000000000000000` |
| `X-Authentication-Tag` | `CE573FB7A41AB78E743180DC83FF09BD` |
| Body | `0A3471C72D9BE49A8520F79C66BBD9A12FF9` |
| Plaintext | `{"type":"PAYMENT"}` |

Libraries that take the tag appended to the ciphertext (BouncyCastle, libsodium's
`crypto_aead_aes256gcm_decrypt`) need `body . tag` as one input; `openssl_decrypt` takes the tag as
its own argument. Convert strings to bytes as UTF-8.

## Payload structure

```json
{
  "type": "PAYMENT",
  "payload": {
    "id": "{transactionId}",
    "ndc": "{checkoutId}.{node}",
    "paymentBrand": "VISA",
    "paymentType": "DB",
    "amount": "10.00",
    "currency": "USD",
    "result": { "code": "000.000.000", "description": "Transaction succeeded" },
    "authentication": { "entityId": "…" },
    "card": { "bin": "…", "last4Digits": "…", "holder": "…", "expiryMonth": "…", "expiryYear": "…" },
    "registrationId": "…",
    "standingInstruction": { "initialTransactionId": "…" },
    "customParameters": { "…": "…" },
    "timestamp": "2026-04-17 08:45:03+0000",
    "channelName": "…",
    "source": "SYSTEM",
    "paymentMethod": "CC",
    "shortId": "5420.6916.5424"
  }
}
```

| Field | Meaning |
|-------|---------|
| `type` | `PAYMENT`, `REGISTRATION`, `SCHEDULE` or `RISK` |
| `action` | **Only on `REGISTRATION`**: `CREATED`, `UPDATED` or `DELETED`. Absent on every other type |
| `payload` | Mirrors the response of the matching API (payment, registration, risk…), trimmed by the `Fields` setting |

HyperPay adds fields without notice — the docs' samples even carry a
`"randomField…": "Please allow for new unexpected fields to be added"` key. Never reject an unknown
key, and never validate the payload against a closed schema.

### Shape per type

Fields that set each type apart, from the docs' Format tab:

| `type` | `payload.paymentType` | `payload.source` | Distinguishing fields |
|--------|-----------------------|------------------|-----------------------|
| `PAYMENT` | `PA`, `DB`, `CP`, `RF`, … | `SYSTEM` | `amount`, `currency`, `result`, `card`, `customer`, `risk.score` |
| `REGISTRATION` | — | `SYSTEM` | `id` is the **registration** (token) ID; `action` present; `card` carries no expiry in the docs sample |
| `SCHEDULE` | `SD` | `SCHEDULER` | `registrationId`, `presentationAmount` / `presentationCurrency`, `resultDetails.ConnectorTxID1` |
| `RISK` | `RI` | `SYSTEM` | `referencedId` (the payment the decision is about), `paymentMethod: RM` |

Route on `type` first, and ignore types you did not subscribe to rather than failing on them.

## Delivery guarantees

| Behaviour | Detail |
|-----------|--------|
| Timeout | No response within **30 seconds** → failed. Queue the work and answer immediately |
| Failure | Any non-2xx → failed |
| Retry ladder | `1 min → 2 min → 4 min → 8 min → 15 min → 30 min → 1 hour → daily`, up to 30 days |
| Retry pause | If every message fails at a given interval, retries pause and resume once a delivery succeeds |
| Retention | Failed messages are purged after 30 days |
| Failure email | Daily, listing up to 100 failed notifications per endpoint |
| Latency | Usually seconds; up to 15 minutes during releases, data-centre switchovers or restarts |
| Load | Bursts of 30+ notifications per second |

- **Order is not guaranteed.** Never infer state from arrival order.
- **Several final statuses can arrive** for one transaction — a success *and* a failure. Deduplicate
  on transaction ID plus status, and never let a later failure undo a success.
- Duplicates also come from several webhooks at or above the same entity (see Entity scope).

### Webhook vs status query

| Use case | Use |
|----------|-----|
| Decision while the shopper is present (capture, fulfillment) | Transaction Status query API |
| Reporting, reconciliation, non-critical automation | Webhook |
| High-frequency polling | Neither — use webhooks, or the SFTP transaction export |

## `ndc` vs `id` — critical distinction

| Field | Example | Meaning |
|-------|---------|---------|
| `payload.ndc` | `A1B2C3D4E5F6A7B8C9D0E1F2A3B4C5D6.example-node` | Checkout-scoped identifier — matches the `checkoutId` you created |
| `payload.id` | `8ac7a4a1000000010000000200000003` | HyperPay's internal transaction ID |

Look up your local record by `ndc`, not `id`. Both stay constant across the PENDING and SUCCESS
webhooks for the same transaction, which is what makes idempotent handling work.

The docs' Format samples show `ndc` as `{entityId}_{hash}` (e.g.
`8a8294174b7ecb28014b9699220015ca_66b12f65…`). The `{checkoutId}.{node}` shape above comes from a
real capture and is what to expect; treat the docs' samples as dated.

## Observed payloads

Decrypted payloads from a real checkout with a standing instruction (INITIAL / CIT), captured in
test mode with identifiers replaced by synthetic ones.

### 1. PENDING (`000.200.000`) — arrives right after the 3DS redirect

```json
{
  "type": "PAYMENT",
  "payload": {
    "id": "8ac7a4a1000000010000000200000003",
    "registrationId": "8ac7a49f000000010000000200000004",
    "paymentType": "DB",
    "paymentBrand": "VISA",
    "amount": "287.88",
    "currency": "SAR",
    "merchantTransactionId": "00001234",
    "recurringType": "INITIAL",
    "result": { "code": "000.200.000", "description": "transaction pending" },
    "card": {
      "bin": "411111", "last4Digits": "1111", "holder": "Jane Jones",
      "expiryMonth": "01", "expiryYear": "2039", "type": "DEBIT", "country": "PL"
    },
    "customParameters": {
      "recurringPaymentAgreement": "aB3dE6gH9jK2",
      "StoredCredentialType": "CIT"
    },
    "standingInstruction": {
      "source": "CIT", "type": "RECURRING", "mode": "INITIAL",
      "expiry": "2029-04-17", "frequency": "9999",
      "numberOfInstallments": "1", "recurringType": "STANDING_ORDER"
    },
    "ndc": "A1B2C3D4E5F6A7B8C9D0E1F2A3B4C5D6.example-node",
    "timestamp": "2026-04-17 08:44:56+0000"
  }
}
```

No `standingInstruction.initialTransactionId` yet — do not persist a card from a PENDING webhook.

### 2. SUCCESS (`000.100.112`) — ~7 seconds later, after 3DS completes

```json
{
  "type": "PAYMENT",
  "payload": {
    "id": "8ac7a4a1000000010000000200000003",
    "registrationId": "8ac7a49f000000010000000200000004",
    "paymentType": "DB",
    "paymentBrand": "VISA",
    "amount": "287.88",
    "currency": "SAR",
    "merchantTransactionId": "00001234",
    "recurringType": "INITIAL",
    "result": {
      "code": "000.100.112",
      "description": "Request successfully processed in 'Merchant in Connector Test Mode'"
    },
    "resultDetails": {
      "ConnectorTxID1": "8ac7a4a1000000010000000200000003",
      "ConnectorTxID2": "1000.2000.3000",
      "CardholderInitiatedTransactionID": "123456789012345",
      "3ds.acsEci": "05"
    },
    "card": {
      "bin": "411111", "last4Digits": "1111", "holder": "Jane Jones",
      "expiryMonth": "01", "expiryYear": "2039", "type": "DEBIT", "country": "PL"
    },
    "customParameters": {
      "recurringPaymentAgreement": "aB3dE6gH9jK2",
      "StoredCredentialType": "CIT"
    },
    "standingInstruction": {
      "source": "CIT", "type": "RECURRING", "mode": "INITIAL",
      "initialTransactionId": "123456789012345",
      "expiry": "2029-04-17", "frequency": "9999",
      "numberOfInstallments": "1", "recurringType": "STANDING_ORDER"
    },
    "ndc": "A1B2C3D4E5F6A7B8C9D0E1F2A3B4C5D6.example-node",
    "timestamp": "2026-04-17 08:45:03+0000"
  }
}
```

`standingInstruction.initialTransactionId` is present on success and is the value to store for
future MIT charges. The same value also appears as
`resultDetails.CardholderInitiatedTransactionID`.
