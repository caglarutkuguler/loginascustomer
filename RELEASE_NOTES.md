# Login As Customer - One-Click Support Access — Release notes

_Only customer-relevant changes are listed. Internal refactoring, tests and housekeeping are omitted._

## 1.0.4

### Improved
- Token shape is now `expiry.jti.signature`. Pre-1.0.3 two-part links stop working after upgrade; generate a fresh link from BO

### Fixed
- Connect links are now single-use. Each minted URL carries a `jti` that is recorded on first successful login; a replay of the same URL (Referer leak, shared screen recording, browser history) no longer impersonates the customer
- Module-scoped HMAC secret (`LOGINASCUSTOMER_SECRET`) instead of relying only on `_COOKIE_KEY_` material (MEG-85 hybrid A path for this module)
- Employee must still be active when the link is redeemed; a deactivated staff account cannot keep using outstanding links
- Default TTL reduced from 60 to 15 minutes (configurable 5–1440)
- Audit log includes `jti` on success and on rejected replay, so merchants can correlate duplicate connect attempts in Advanced Parameters → Logs (disclosure audit sentence)
- Missing `loginascustomer_used` table fails loud in BO (error banner + status line + repair button) and refuses to mint connect links; FO redeem stays fail-closed

## 1.0.2

### Fixed
- The back-office icons turned into empty boxes on PrestaShop 9. The 10 icons the module draws for itself came from FontAwesome 4, which the back office shipped up to PrestaShop 8. PrestaShop 9 replaced it with Material Symbols Outlined, and FontAwesome now reaches the page only through `themes/default/public/theme.css` — a leftover of the old theme rather than anything the new back office asks for
- PrestaShop 1.5 and 1.6 are the other way round — they carry FontAwesome and no Material icons at all — and this module still supports them, so the templates now pick the set the running core actually has instead of assuming one

## 1.0.1 — 2026-08-28

### Fixed
- All back-office hooks now degrade gracefully (`catch (Throwable)`) so a module error can never white-screen a customer or order page
