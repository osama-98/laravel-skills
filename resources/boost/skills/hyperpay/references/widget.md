# COPYandPAY Widget

Sources:
- https://hyperpay.docs.oppwa.com/integrations/widget
- https://hyperpay.docs.oppwa.com/integrations/widget/api

## Integration

### 1. Prepare the checkout (server-to-server)

`POST {baseUrl}/v1/checkouts`, form-encoded, with at minimum `entityId`, `amount`, `currency`,
`paymentType`. Add `integrity=true` to receive the SRI hash.

Response: `{ "id": "…", "integrity": "…" }`. The `id` is the `checkoutId`.

### 2. Render the widget

```html
<script src="{baseUrl}/v1/paymentWidgets.js?checkoutId={checkoutId}"
        integrity="{integrity}"
        crossorigin="anonymous"></script>

<form action="{shopperResultUrl}" class="paymentWidgets" data-brands="VISA MASTER AMEX"></form>
```

- `data-brands` is a space-separated brand list; multiple forms can coexist for brand groupings.
- A checkout ID expires after **30 minutes** or on successful payment.

### 3. Read the status

The shopper is redirected to
`shopperResultUrl?resourcePath=/v1/checkouts/{checkoutId}/payment`.

`GET {baseUrl}{resourcePath}?entityId=…` with the bearer token and evaluate `result.code`.
Status GETs are throttled to **2 per checkout per minute** — treat webhooks as the source of truth.

## `wpwlOptions`

Declare `var wpwlOptions = {…}` **before** the widget script tag.

### Presentation

| Option | Effect |
|--------|--------|
| `locale` | Language/country, ISO 639-1 + ISO 3166-1 alpha-2 (e.g. `de-AT`) |
| `style` | `card`, `logos`, `none`, `plain` |
| `autofocus` | Field to focus on load |
| `inlineFlow` | Render alternative brands inline |
| `useSummaryPage` | Show a summary step before submitting |

### Form behavior

| Option | Effect |
|--------|--------|
| `requireCvv` | Show the CVV field (default `true`) |
| `allowEmptyCvv` | Permit blank CVV |
| `allowEmptyCardHolderName` | Permit blank holder |
| `disableCardExpiryDateValidation` | Skip expiry validation |
| `disableSubmitOnEnter` | Block Enter-key submit |
| `paymentTarget` | Where the card form submits and 3-D Secure opens — see [3-D Secure: where it opens](#3-d-secure-where-it-opens) |
| `shopperResultTarget` | Where the result redirect opens; only used with `paymentTarget` |
| `enableSAQACompliance` | Render holder and expiry in separate iframes (SAQ-A scope) |
| `brandDetection`, `brandDetectionType` | Enable brand detection; `"binlist"` enables BIN lookup |

### Callbacks

| Callback | Fires when |
|----------|-----------|
| `onReady(array)` | All payment forms loaded; receives container info |
| `onDetectBrand` | Brand detection runs; gives the brand list and the active brand |
| `onDetectBin` | BIN data available (requires `brandDetectionType: "binlist"`) |
| `onBeforeSubmitCard` | Before a card submit — return `false` to block |
| `onBeforeSubmitCardPromise` | Async variant; resolve to continue, reject to block |
| `onAfterSubmit` | After submission |
| `onError(error)` | `InvalidCheckoutIdError`, `PciIframeSubmitError`, `WidgetError` |
| `onBlurCardNumber`, `onBlurCardHolder`, `onBlurSecurityCode` | Iframe field blur |
| `onReadyIframeCommunication` | PCI iframe channel established |
| `onLoadThreeDIframe` | The widget's own 3-D Secure iframe has loaded and is shown; context (`this`) is that iframe |

```javascript
var wpwlOptions = {
  locale: "ar",
  style: "card",
  requireCvv: true,
  brandDetection: true,
  brandDetectionType: "binlist",
  onReady: function (array) {
    console.log('Forms ready: ' + array.length);
  },
  onBeforeSubmitCard: function (event) {
    return true;
  },
  onError: function (error) {
    if (error.name === "InvalidCheckoutIdError") {
      // checkout expired — prepare a new one
    }
  }
};
```

`InvalidCheckoutIdError` almost always means the 30-minute checkout window elapsed. Recover by
preparing a fresh checkout, never by retrying the same ID.

## 3-D Secure: where it opens

The docs describe `paymentTarget` only as "We submit the form to this target. In case of additional
shopper interaction, e.g. 3DSecure, we redirect the shopper within this target", and give no
default. The widget source (`{baseUrl}/v1/static/{cacheVersion}/js/static.min.js`, checked
2026-09-29) settles it: `paymentTarget` defaults to `undefined`, and the 3-D Secure redirect form
targets `paymentTarget ? paymentTarget : <the form container's id>`.

| `paymentTarget` | Where 3-D Secure opens |
|-----------------|------------------------|
| not set (default) | **On the same page**, in an iframe the widget places next to the card form. The widget hides the form, shows the iframe at `threeDIframeSize` and calls `onLoadThreeDIframe`. |
| `"_top"` | The **whole tab** navigates to the gateway, then the issuer's page, then `shopperResultUrl`. |
| a frame name (e.g. `"my3dIframe"`) | Inside **your own** `<iframe name="my3dIframe">`. Must not equal the page's own frame name, or the redirect fails. |

- "This only works for card payment brands" (HyperPay docs).
- `shopperResultTarget` works **only together with** `paymentTarget`. By default the shopper returns
  to `shopperResultUrl` through a self-submitting form with `target="_top"` — so after an in-page
  3-D Secure, the result page still opens in the whole tab. Set both to the same frame name only
  when the whole checkout runs inside an iframe.
- `threeDIframeSize` sizes the widget's own 3-D Secure iframe; default `{ width: '100%', height: '580px' }`.
- `browser.threeDChallengeWindow` sizes the 3-D Secure 2 challenge: `1` 250×400, `2` 390×400,
  `3` 500×600, `4` 600×400, `5` full screen. Default: picked from the card form's width.

**CSP:** with the default (in-page) target, the widget's 3-D Secure iframe is a frame of *your*
page, and every navigation inside it is checked against *your* `frame-src` — not just the gateway,
but each **card issuer's** 3-D Secure domain. A real-card payment (2026-09-29) loaded
`https://methodurl.vcas.visa.com/` and `https://authentication.cardinalcommerce.com/` in that frame;
a gateway-only `frame-src` blocked them and the payment stalled on "wait for 10 seconds". Issuer
domains cannot be listed, so in-page 3-D Secure needs `frame-src 'self' https:`. With `"_top"` the
issuer page is a top-level page, your CSP does not apply to it, and `frame-src` can stay gateway-only.

## Wallets: `applePay` and `googlePay`

Sources: `/integrations/widget/apple-pay`, `/integrations/widget/google-pay`, and the widget source
(`static.min.js`, cache version `70d802c5…`, checked 2026-10-01).

### `applePay.version`

The Apple Pay JS API version. **Default `1`.** The widget passes it straight to
`new ApplePaySession(version, request)` and removes the button when
`ApplePaySession.supportsVersion(version)` is false.

Set it whenever you use an option from a later version — the docs mark each one:
`supportedCountries` and `requiredShippingContactFields: ["phoneticName"]` need **3+**,
`supportsCouponCode`, `couponCode`, `shippingContactEditingMode` and
`shippingMethods[].dateComponentsRange` need **12+**. Use the lowest version that covers them.

```javascript
applePay: {
  countryCode: "SA",
  merchantCapabilities: ["supports3DS"],
  supportedNetworks: ["mada", "visa", "masterCard"],
  version: 3,
  supportedCountries: ["SA", "AE"]   // only honoured with version >= 3
}
```

### Re-checking the order before the shopper pays

The card form has `onBeforeSubmitCard`; wallets confirm in an OS sheet instead, so the guard
belongs in each wallet's `onPaymentAuthorized`. Both callbacks may return a thenable (native or
jQuery promise).

| | Apple Pay `applePay.onPaymentAuthorized(payment)` | Google Pay `googlePay.onPaymentAuthorized(paymentData)` |
|---|---|---|
| Continue | return nothing, or `{ status: "SUCCESS" }` | resolve `{ transactionState: "SUCCESS" }` |
| Stop | `{ status: "ABORT" }` → the widget calls `session.abort()` | resolve `{ transactionState: "ERROR", error: { reason: "PAYMENT_DATA_INVALID", message, intent: "PAYMENT_AUTHORIZATION" } }` → no payment is sent |
| Show errors | `{ status: "FAILURE", errors: [...] }` | — |
| Rejected promise | `completePayment(FAILURE)` | no payment, and `onError` fires |
| Must return | anything | **a promise** — the widget calls `.then()` on the result |

```javascript
wpwlOptions.googlePay.onPaymentAuthorized = function (paymentData) {
  return isOrderStillPayable().then(function (ok) {
    return ok
      ? { transactionState: "SUCCESS" }
      : { transactionState: "ERROR",
          error: { reason: "PAYMENT_DATA_INVALID", message: "Order is no longer payable", intent: "PAYMENT_AUTHORIZATION" } };
  });
};

wpwlOptions.applePay.onPaymentAuthorized = function (payment) {
  return isOrderStillPayable().then(function (ok) {
    if (!ok) {
      return { status: "ABORT" };
    }
  });
};
```

### Errors that are not payment failures

- **`googlePay.onCancel(statusCode)`** — called when the shopper closes the Google Pay sheet
  (`CANCELED`). Without it the widget reports the close to `onError` as
  `WidgetError { brand: "GOOGLEPAY", event: "closed" }`, so define it (even empty) when `onError`
  alerts or logs. `DEVELOPER_ERROR` always goes to `onError`.
- **Samsung Pay `not_ready`** — when `isReadyToPay` is false (non-Samsung device, unsupported
  country) the widget removes the button and calls
  `onError(WidgetError { brand: "SAMSUNGPAY", event: "not_ready" })`. It is an availability check;
  return early for it in `onError`.

## `messageNamespace` — internal, do not set it

Not a documented option. The widget always adds a hidden iframe,
`{baseUrl}/v1/internalRequestIframe.html`, and that page sets `messageNamespace: "internalRequest"`
on **itself**: the flag makes a window the listening end of the widget's internal AJAX relay. In
that mode the window opens a channel to `window.parent` that accepts **any origin** (`"*"`),
forwards `send` messages to `jQuery.ajax`, and loads the Iovation script on request.

Set on a merchant page it changes nothing about 3-D Secure or the result redirect — those follow
`paymentTarget` and `shopperResultTarget`. It only turns that page into a relay for whatever frames
it: a parent page could then have it issue same-origin requests. Leave it unset; if it is already
there, `frame-ancestors 'self'` (or `'none'`) in the CSP keeps foreign parents out.

Registration-token variants of the widget (standalone tokenization, one-click) are in
`tokenization.md`.
