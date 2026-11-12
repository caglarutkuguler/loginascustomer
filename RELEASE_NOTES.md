# Login As Customer - One-Click Support Access — Release notes

_Only customer-relevant changes are listed. Internal refactoring, tests and housekeeping are omitted._

## 1.1.0

### New
- Multistore support. With several shops, the connect link is now built for the shop the customer belongs to (its domain, SSL setting and default language) instead of the shop selected in the back office, and the token is bound to that shop, so a link opened on another shop's domain is refused
- The configuration page shows a notice when you are in the All shops or shop group context

### Fixed
- The signing secret is now stored once for the whole installation. Before, it was saved for the shop selected at the time, so a link created in one shop context could fail on another shop. An existing per-shop secret is promoted, not replaced
- A link now logs in only a customer who may log in on the shop that opens it, by the same rule PrestaShop uses for customer logins (own shop, or the whole shop group when the group shares its customers). The link lifetime is read from the customer's shop
- Links created before this update stop working once multistore is on (15 minutes by default); create a new one. Nothing changes with a single shop

## 1.0.5

### New
- The configuration page links to the free MEG Venture Updates module, which installs updates for this module in one click. The line is hidden once that module is installed

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
