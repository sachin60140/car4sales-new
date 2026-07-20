# Car4Sales — Public Website Plan

Same Laravel project; `routes/public.php` under the `web` middleware group with its own Inertia
layout (`resources/js/layouts/public/PublicLayout.vue`) and page namespace
(`resources/js/pages/public/`).

**Status (Phase 4 complete):** all pages, filters, forms, SEO and lead generation are implemented
and tested. SEO is delivered client-side via Inertia `<Head>` (title, meta description, canonical,
Open Graph, Twitter) + JSON-LD structured data (`AutoDealer`, `Car`+`Offer`, `FAQPage`) + dynamic
`sitemap.xml` and `robots.txt`. **SSR is deferred to production hardening** — enabling it requires
an SSR entry (`resources/js/ssr.ts`), `ssr` input in `vite.config.ts`, and a persistent
`php artisan inertia:start-ssr` process, which is impractical in the current XAMPP dev environment.
Modern crawlers execute the client render, and the metadata above is present in the initial HTML
`<head>`, so indexing works without SSR.

## Sitemap / routes

| URL | Page | Notes |
|---|---|---|
| `/` | Home | All §6 homepage sections |
| `/cars` | Available cars | Filters, grid/list, sort, pagination; SEO URL params |
| `/cars/{slug}` | Vehicle details | Slug = `maruti-swift-vxi-2019-stk000123`; JSON-LD `Vehicle`/`Product` + Offer |
| `/compare` | Vehicle comparison | Up to 3, session-backed |
| `/favourites` | Favourite vehicles | Session/localStorage-backed |
| `/sell-your-car` | Sell your car | Creates Purchase Lead (OTP-verified) |
| `/finance` | Finance assistance | EMI calculator + finance enquiry |
| `/about` | About us | |
| `/branches`, `/branches/{slug}` | Branch listing/detail | Map, hours, inventory link |
| `/contact` | Contact us | Form + WhatsApp CTA |
| `/reviews` | Customer reviews | Approved testimonials |
| `/faqs` | FAQ | FAQ JSON-LD |
| `/privacy-policy`, `/terms`, `/refund-policy`, `/disclaimer` | CMS pages | From `pages` table |
| `/sitemap.xml` | XML sitemap | Queued daily regeneration (static, vehicles, branches, pages) |
| `/robots.txt` | Robots | |

## Listing filters
Brand, model, variant, price range, manufacturing year, fuel, transmission, km driven, ownership,
body type, branch, colour, availability, finance availability — all server-side (Eloquent +
indexes), shareable URLs, mobile filter drawer.

## Lead generation
Unified `public_enquiries` intake (vehicle enquiry, test-drive request, finance enquiry, callback,
contact, booking-interest) + `sell_car_requests`:
- OTP verification (SMS provider adapter; log driver locally)
- Rate limiting per IP+mobile, honeypot + submit-time spam checks
- Duplicate detection (same mobile + type + vehicle within N days ⇒ appended, not re-created)
- Consent checkbox stored with timestamp + IP
- Source/campaign/UTM capture ⇒ stored JSON
- Branch assignment (chosen branch or vehicle's branch) ⇒ auto-creates Sales Lead or Purchase
  Lead and notifies the owning team.

## Data exposure rules
Public vehicle payload contains only: title, asking price, reg/mfg year, km, fuel, transmission,
ownership serial, registration state, colour, insurance status, description, features, inspection
highlights (curated), media, branch, finance estimate. **Never**: chassis/engine numbers, seller
KYC, purchase price, expenses, minimum selling price, internal remarks, approval history.

## SEO checklist
Dynamic titles/meta descriptions (per page + per vehicle), canonical URLs, Open Graph + Twitter
cards, XML sitemap, robots.txt, JSON-LD (`Organization`, `AutoDealer`, `Vehicle` + `Offer`,
`BreadcrumbList`, `FAQPage`), SEO-friendly slugs, image `alt` from vehicle title, responsive
WebP-optimised images with thumbnails, SSR for crawlable HTML.
