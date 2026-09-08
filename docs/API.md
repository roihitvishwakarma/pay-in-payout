# API Documentation

The Pay-in & Payout Module exposes two JSON HTTP endpoints for initiating
transactions. Both endpoints accept a JSON request body, validate it with a
Laravel Form Request before any business logic runs, and create a transaction
in the `pending` state. On success they return HTTP `201`; on invalid input
they return HTTP `422` with a Laravel validation error map.

- [Conventions](#conventions)
- [POST /api/pay-in](#post-apipay-in)
- [POST /api/payout](#post-apipayout)
- [Error responses (422)](#error-responses-422)

## Conventions

All requests and responses use `application/json`. Send the following headers:

```
Content-Type: application/json
Accept: application/json
```

The `Accept: application/json` header is required to guarantee that validation
failures are returned as a JSON `422` response (rather than an HTML redirect).

### Request parameters (both endpoints)

Both endpoints accept the same body. Both parameters are **required**; there are
no optional parameters.

| Parameter     | Type              | Required | Constraints |
|---------------|-------------------|----------|-------------|
| `merchant_id` | integer           | Yes      | Must reference an existing merchant (`merchants.id`). |
| `amount`      | decimal string    | Yes      | Numeric; greater than `0`; less than or equal to `999999999999.99`; at most 2 decimal places. Send as a string to avoid floating-point rounding. |

### Success response fields (both endpoints)

| Field            | Type   | Description |
|------------------|--------|-------------|
| `transaction_id` | string | System-generated identifier, unique across all transactions. Format: `TXN-PI-YYYYMMDD-XXXXXXXXXX` for pay-ins and `TXN-PO-YYYYMMDD-XXXXXXXXXX` for payouts, where `XXXXXXXXXX` is 10 uppercase hex characters. |
| `status`         | string | Always `pending` on creation. |

---

## POST /api/pay-in

Initiates a pay-in transaction and creates a `PENDING` pay-in record.

### Sample request

```json
{
  "merchant_id": 1,
  "amount": "150.00"
}
```

```bash
curl -X POST https://your-host/api/pay-in \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 1, "amount": "150.00"}'
```

### Sample success response (201 Created)

```json
{
  "transaction_id": "TXN-PI-20240612-9F3A2B7C4D",
  "status": "pending"
}
```

---

## POST /api/payout

Initiates a payout transaction and creates a `PENDING` payout record.

### Sample request

```json
{
  "merchant_id": 1,
  "amount": "75.50"
}
```

```bash
curl -X POST https://your-host/api/payout \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 1, "amount": "75.50"}'
```

### Sample success response (201 Created)

```json
{
  "transaction_id": "TXN-PO-20240612-6ADF4C7356",
  "status": "pending"
}
```

---

## Error responses (422)

When validation fails, the endpoint returns HTTP `422 Unprocessable Entity`
without creating a transaction, adjusting any wallet balance, or writing a
transaction log entry. The body follows Laravel's standard validation shape:

```json
{
  "message": "<summary message, typically the first error>",
  "errors": {
    "<field>": ["<error message>", "..."]
  }
}
```

Both `/api/pay-in` and `/api/payout` return identical validation behavior and
messages. Each documented error condition is listed below with its triggering
condition and the corresponding error indication returned to the caller.

### Unknown / non-existent merchant

- **Trigger:** `merchant_id` does not reference an existing merchant.
- **Field / message:** `merchant_id` -> `The merchant identifier is not recognized.`

```json
{
  "message": "The merchant identifier is not recognized.",
  "errors": {
    "merchant_id": ["The merchant identifier is not recognized."]
  }
}
```

Example trigger:

```bash
curl -X POST https://your-host/api/pay-in \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 999999, "amount": "150.00"}'
```

### Missing or non-numeric amount

- **Trigger:** `amount` is absent or not a numeric value.
- **Field / message:** `amount` -> `The amount is invalid; it must be numeric.`

```json
{
  "message": "The amount is invalid; it must be numeric.",
  "errors": {
    "amount": ["The amount is invalid; it must be numeric."]
  }
}
```

Example trigger:

```bash
curl -X POST https://your-host/api/pay-in \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 1, "amount": "abc"}'
```

### Amount with more than 2 decimal places

- **Trigger:** `amount` has more than 2 digits after the decimal point (for example `10.123`).
- **Field / message:** `amount` -> `The amount is invalid; at most 2 decimal places are allowed.`

```json
{
  "message": "The amount is invalid; at most 2 decimal places are allowed.",
  "errors": {
    "amount": ["The amount is invalid; at most 2 decimal places are allowed."]
  }
}
```

Example trigger:

```bash
curl -X POST https://your-host/api/pay-in \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 1, "amount": "10.123"}'
```

### Amount less than or equal to 0

- **Trigger:** `amount` is `0` or negative.
- **Field / message:** `amount` -> `The amount is out of range; it must be greater than 0.`

```json
{
  "message": "The amount is out of range; it must be greater than 0.",
  "errors": {
    "amount": ["The amount is out of range; it must be greater than 0."]
  }
}
```

Example trigger:

```bash
curl -X POST https://your-host/api/pay-in \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 1, "amount": "0"}'
```

### Amount greater than the maximum allowed value

- **Trigger:** `amount` exceeds `999999999999.99`.
- **Field / message:** `amount` -> `The amount is out of range; it exceeds the maximum allowed value.`

```json
{
  "message": "The amount is out of range; it exceeds the maximum allowed value.",
  "errors": {
    "amount": ["The amount is out of range; it exceeds the maximum allowed value."]
  }
}
```

Example trigger:

```bash
curl -X POST https://your-host/api/pay-in \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"merchant_id": 1, "amount": "1000000000000.00"}'
```

> Note: When more than one rule fails for the same request, `errors` may contain
> multiple entries and `message` reflects the first failing rule. The samples
> above isolate each condition for clarity.
