# Changelog

All notable changes to this module are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
