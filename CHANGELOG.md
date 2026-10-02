# Changelog

All notable changes to this module are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.3

### Fixed

- **Other MEG Venture modules failed to upgrade while this one was
  installed** ("Could not perform action upgrade for module undefined"). The
  review-request helper was declared as `MegVentureReviewNudge`, a class name
  every MEG Venture module with the same feature also uses for its own copy,
  and this module loads its copy at the top of its main file. So whenever
  another module's install or upgrade script loaded its own copy in the same
  request, PHP stopped with "Cannot declare class". The class is now
  `LoginAsCustomerReviewNudge`; the file name and the translations are
  unchanged.

## 1.0.2

### Fixed

- **The back-office icons turned into empty boxes on PrestaShop 9.** The 10
  icons the module draws for itself came from FontAwesome 4, which the back
  office shipped up to PrestaShop 8. PrestaShop 9 replaced it with Material
  Symbols Outlined, and FontAwesome now reaches the page only through
  `themes/default/public/theme.css` — a leftover of the old theme rather than
  anything the new back office asks for.

  An icon-font class does not name a picture; it selects a private-use code
  point that means nothing without that exact font file. So the moment the
  font is not there the browser has nothing to fall back to and draws a
  placeholder box. That makes the failure abrupt rather than gradual, and it
  shows up first on a page load with a freshly cleared asset cache.

  The icons now come from the set the core loads for its own interface, where
  the icon name is the element’s text rather than a class. They are sized
  down from its 24px default and set back to inheriting the surrounding text
  colour, so they sit exactly where the FontAwesome ones did. Nothing in the
  interface moves or changes name.

  PrestaShop 1.5 and 1.6 are the other way round — they carry FontAwesome
  and no Material icons at all — and this module still supports them, so
  the templates now pick the set the running core actually has instead of
  assuming one.

## [1.0.1] - 2026-08-28

### Fixed
- **Order page crash on PrestaShop 8.1+ / 9** (`ClassNotFoundError` for
  `ActionsBarButton`). The order-toolbar button class was moved from
  `PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton` (1.7.7–8.0) to
  `PrestaShop\PrestaShop\Core\Action\ActionsBarButton` (8.1+/9); the module now
  detects whichever the running core ships and adds no toolbar button if neither
  exists.
- All back-office hooks now degrade gracefully (`catch (Throwable)`) so a module
  error can never white-screen a customer or order page.

## [1.0.0] - 2026-08-28

First MEG Venture release. Rebranded and re-secured from the open-source
"connect as customer" tool.

### Added
- **Log in as customer** button on the Customers page, on order pages, and in
  the order toolbar (PrestaShop 1.7.7+).
- Signed, time-limited, customer-bound connect token (HMAC-SHA256 keyed on the
  shop secret) — see *Changed/Security*.
- Configurable landing page, link lifetime, per-page button visibility and
  new-tab behaviour.
- Audit logging of every connection (employee → customer) to the back-office
  logs.
- Configure page with a how-it-works panel and live status/SSL checks.
- MEG Venture review-request line and "more free modules" promo strip on the
  configure page.
- Plain-PHP test suites for the token (`tests/TokenTest.php`) and the
  review-request line (`tests/ReviewNudgeTest.php`).
- Available in 8 languages: English, Spanish, French, German, Italian, Dutch,
  Polish and Turkish.

### Changed / Security
- **Replaced the fixed authorization token.** The original module authorised its
  storefront login controller with `Tools::encrypt('everpscustomerconnect/everlogin')`,
  a value that was constant for the whole life of the shop and was printed into
  every back-office order and customer page — meaning anyone who ever saw it
  could silently log in as *any* customer by changing the id in the URL. Tokens
  are now per-request, signed, customer-bound and short-lived.
- Login now uses PrestaShop's own `Context::updateCustomer()` on every supported
  version instead of hand-setting cookie fields, which fixes session handling on
  the customer-session cores (1.7.6.5+).

### Removed
- The outbound "check for updates" cURL call to an external upgrade server on
  every configure-page load.
- The third-party cross-promotion and the PayPal donation form.
- All previous-vendor branding, logo and license headers.
