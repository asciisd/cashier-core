# Upgrading from 2.x to 3.0

3.0 removes Paytiko support. Nothing else changes; if you do not use Paytiko,
bump the constraint to `^3.0` and you are done.

## Paytiko removed

- The `asciisd/cashier-paytiko` plugin is not supported on 3.x. Hosts that
  still process through Paytiko must stay on `^2.0`.
- The Paytiko-specific `PaymentLogger` methods are removed (full list in
  `CHANGELOG.md` under 3.0.0). Any code that calls them must log through its
  own channel instead.
- Transactions already stored with `provider = 'paytiko'` are untouched, but no
  driver is registered for that string, so they can no longer be synced or
  receive webhooks through the core.
