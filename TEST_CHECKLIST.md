# loginascustomer — manual test checklist

PrestaShop **1.6 / 1.7 / 8 / 9**. Module **1.0.3**.

## Security fixes in 1.0.3

### Single-use token (LAC-1)

- [ ] Generate a connect link from a customer page; open it once → lands logged in as that customer
- [ ] Open the **same** URL again (or in another browser) → redirected to homepage, **not** logged in
- [ ] A second fresh link for the same customer still works once

### Employee gate (LAC-2)

- [ ] Link minted by employee A fails if `id_employee` in the URL is changed to another id (signature)
- [ ] Deactivate the minting employee in BO, then open a still-unused link minted earlier → rejected

### Transport / TTL (LAC-3)

- [ ] On an SSL shop, the connect `href` is `https://…`
- [ ] Fresh install default link lifetime is **15** minutes (configure page)
- [ ] Upgrade from 1.0.2 with TTL=60 → configure shows 15

### Crypto shape

- [ ] Token query param has three segments (`expiry.jti.sig`), not two
- [ ] `php tests/TokenTest.php` passes inside the review toolchain container

### Audit log jti (MEG-136 / disclosure audit sentence)

- [ ] Successful connect writes a PrestaShopLogger line containing `jti=<32 hex>`
- [ ] Replaying the same URL writes a second log line: `rejected replay of jti <same value>`
- [ ] Searching Advanced Parameters → Logs for that jti returns both lines (success + reject)

### Missing ledger table (MEG-136)

- [ ] Drop `ps_loginascustomer_used` (or rename it); open Modules → Login As Customer configure → red error banner + Status shows table missing
- [ ] Customer/order pages no longer show a connect button / href while the table is missing
- [ ] Click **Recreate single-use token table** → confirmation; Status turns green; a fresh connect link works once
- [ ] Opening a still-unused pre-drop link after the table is gone → FO redirect + severity-3 log `loginascustomer_used table missing`

## Regression

- [ ] Customer page "Log in as…" button still renders
- [ ] Order page panel + toolbar button (PS 1.7.7+) still work
- [ ] PrestaShopLogger / Advanced Parameters → Logs shows the impersonation line
- [ ] Landing setting (my-account vs homepage) still respected
- [ ] New-tab setting still respected
