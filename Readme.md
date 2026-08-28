# Login As Customer — One-Click Support Access

A free MEG Venture module for PrestaShop **1.6, 1.7, 8 and 9**.

Adds a **Log in as customer** button to the back office. From any customer page
or any order, one click opens the storefront in a session signed in as that
customer, so you can see exactly what they see — their prices, vouchers, group
access, cart and account — and resolve support questions in seconds instead of
asking the customer to screenshot everything.

## Features

- **One-click impersonation** from the Customers page and from any order.
- On PrestaShop 1.7.7+ the button also appears in the order toolbar; on older
  versions it appears as a panel on the order and customer pages.
- **Secure by design.** Each button builds a fresh link that is signed, bound to
  that single customer, and expires by itself (default 60 minutes). See
  *Security* below.
- **Audit trail.** Every connection is written to the back-office logs — which
  employee logged in as which customer, and when.
- **Configurable:** where the storefront lands after connecting (customer
  account or homepage), the link lifetime, whether the button shows on customer
  and/or order pages, and whether it opens in a new tab.
- No storefront footprint, no database tables, no theme edits, no external calls
  from the shop.

## Quick start

1. Install the module (Modules → Module Manager → Upload / Install).
2. Open **Customers** and pick a customer, or open **Orders** and pick an order.
3. Click **Log in as customer**. A new tab opens signed in as that customer.
4. When you are done, close the tab and log the storefront out as usual.

There is nothing to configure to get started; the *Configure* page only exposes
optional preferences and a status panel.

## Settings

| Setting | Default | What it does |
|---|---|---|
| Land on | Customer account page | Where the storefront opens after you connect. |
| Link lifetime (minutes) | 60 | How long a generated connect link stays valid (5–1440). Shorter is safer. |
| Show on customer pages | Yes | Show the button on Customers pages. |
| Show on order pages | Yes | Show the button on order pages. |
| Open storefront in a new tab | Yes | Open the impersonation session in a new browser tab. |

## Security

The connect link is an HMAC-SHA256 token over the customer id, the employee id
and an expiry timestamp, keyed on the shop's own secret (`_COOKIE_KEY_`), which
is never printed anywhere. A link therefore:

- opens **only** the one customer it was generated for (the id is inside the
  signature — changing it in the URL invalidates the link);
- **expires** after the configured window;
- **cannot be forged or guessed** without the shop secret.

Turn on **Enable SSL** in *Shop Parameters → General* so these links always
travel encrypted; the Configure page warns you if SSL is off.

> This replaces the fixed, shop-lifetime token used by the original module,
> which was the same value on every page and let anyone who saw it log in as any
> customer by id. If you are migrating from that module, review your access logs.

## Uninstall

Uninstalling removes the module's settings and hook registrations. It creates no
database tables and leaves no data behind.

## Credits

Published by **MEG Venture** — https://megventure.com

Originally derived from an open-source "connect as customer" tool; fully
rewritten and re-secured for the MEG Venture free module range.
