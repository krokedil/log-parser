# Log fixtures

Copied from log-parser-viewer `tests/fixtures/logs`. Keep the two copies in sync.

Real WooCommerce log excerpts (`wp-content/uploads/wc-logs`) from Krokedil plugins, used to test that the parser and the results UI handle each plugin's log format.

`manifest.json` lists the facts for each file: entry count, physical line count, levels, message formats and context formats. Tests can assert against it.

## Files

| Fixture | Plugin | What it covers |
|---|---|---|
| `kustom-checkout/kco-order-flow.log` | Kustom Checkout (formerly Klarna Checkout) | Full checkout for one order: API requests, plain-string messages, `Frontend JS` objects (`title`/`message`/`id`/`source`), an OM response whose `response.body.body` is a JSON-encoded string, and a 404. |
| `qliro-one/order-flow.log` | Qliro One | API requests, string messages that embed JSON (`Checkout Callback received: {...}`), and callback scheduling. |
| `swedbank-pay/order-flow.log` | Swedbank Pay | Full order flow for `#KRO-MICH-174`. Mixes JSON and plain-text messages, has entries with and without CONTEXT, and has one multi-line entry. |
| `dintero/order-flow.log` | Dintero Checkout | API requests without escaped slashes (`https://` rather than `https:\/\/`) and `Frontend JS : <session> \| ...` string messages. |
| `avarda/checkout-flow.log` | Avarda Checkout | INFO and DEBUG, JSON and string messages, and requests logged without a response code. |
| `avarda/recurring-errors.log` | Avarda Checkout | ERROR entry with a 400 response. |
| `kroconnect/fraktjakt.log` | Kroconnect.WooCommerce (Fraktjakt) | No CONTEXT suffix, `stack` as a list of objects, WARNING level. |
| `kroconnect/nshift.log` | Kroconnect.WooCommerce (nShift) | No CONTEXT suffix, `arguments` key, large response bodies. |

## Line formats

Every entry starts with `<ISO 8601 timestamp> <LEVEL> `. The rest of the entry takes one of these forms:

1. **JSON object + legacy context:** `{...} CONTEXT: {"_legacy":true}`. Used by Kustom, Qliro, Dintero and Avarda.
2. **JSON-encoded string + legacy context:** `"Some message" CONTEXT: {"_legacy":true}`. Used by Kustom, Qliro, Dintero and Avarda.
3. **JSON object, no context:** `{...}`. Used by Kroconnect and by Swedbank's "Initiate embedded purchase".
4. **Plain text + context:** `[TAG]: text CONTEXT: [{...}]` or `CONTEXT: {...}`. Swedbank only. The context can be a JSON array.
5. **Plain text, no context:** Swedbank's `[IPN]: Incoming Callback. Post data: {...}`.
6. **Multi-line:** Swedbank's WARNING entry continues on the next physical lines, and its ` CONTEXT: [...]` is on a line of its own.

Swedbank also writes **invalid JSON in CONTEXT**. Backslashes are stripped (`"Krokedil\Swedbank\Pay\..."`), and nested JSON strings are left unescaped (`"webhook_data":"{"paymentOrder":...}"`). This is how the source logs look. Keep it as it is.

## Known gaps (at the time these fixtures were added)

- The log-parser-viewer UI regex in `src/js/app.js` requires ` CONTEXT: `, so it fails on every Kroconnect entry, on two Swedbank JSON entries and on Swedbank's IPN entry.
- `LogParser` matches line by line, so a search returns only the first line of Swedbank's multi-line WARNING entry. The continuation lines and the CONTEXT line are lost.

## Redaction

The excerpts are copied from the source logs with these changes, applied to the raw text so that each plugin's escaping stays the same:

- JWTs, `Authorization` Basic/Bearer values, and `token`/`access_token`/`clientSecret`/`client_secret`/`password`/`secret` values are replaced with `[REDACTED]`.
- Email addresses are replaced with `customer@example.com`.
- The dev site host is replaced with `shop.example.test`.
- The customer IP is replaced with `203.0.113.10`.
- A developer's name, address and phone numbers are replaced with `Test Testsson`, `Testgatan 1` and `+4670000000x`.

The payment providers' own sandbox test personas are left unchanged.

If you add a fixture, redact it the same way and update `manifest.json`.
