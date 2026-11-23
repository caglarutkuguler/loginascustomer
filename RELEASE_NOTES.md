# Login As Customer - One-Click Support Access — Release notes

_Only customer-relevant changes are listed. Internal refactoring, tests and housekeeping are omitted._

## 1.1.2

### Fixed
- The "How it works" step no longer calls the button blue: the back-office theme decides the colour, and on PrestaShop 9 the button is black. Updated in all eight languages
- The module's own back-office blocks keep their frame on PrestaShop 9, which dropped the `.panel` styles the back office carried up to 8. The block on the customer and order pages, and the configuration page, no longer render without a border or padding

## 1.1.1

### Fixed
- With multistore, an employee can now only log in as customers of a shop they are allowed to manage. Before, an employee restricted to shop A could create a connect link for a customer of shop B. The customer's shop is now checked against the employee's shops (super administrators and employees with access to every shop are unaffected) when the link is created, and again when it is opened, so a link created before an employee lost access to that shop stops working. The page shows a notice instead of the button, and the refusal is written to the back-office log. Nothing changes with a single shop

## 1.1.0

### New
- Multistore support. With several shops, the connect link is now built for the shop the customer belongs to (its domain, SSL setting and default language) instead of the shop selected in the back office, and the token is bound to that shop, so a link opened on another shop's domain is refused
- The configuration page shows a notice when you are in the All shops or shop group context

### Fixed
- The signing secret is now stored once for the whole installation. Before, it was saved for the shop selected at the time, so a link created in one shop context could fail on another shop. An existing per-shop secret is promoted, not replaced
- A link now logs in only a customer who may log in on the shop that opens it, by the same rule PrestaShop uses for customer logins (own shop, or the whole shop group when the group shares its customers). The link lifetime is read from the customer's shop
- Links created before this update stop working once multistore is on (15 minutes by default); create a new one. Nothing changes with a single shop
