# Munafa – Roadmap

COD Profit & Return Tracker for WooCommerce.
Target: Bangladesh / South Asia COD sellers.

Total: ~12 weeks (part-time hole 16–18 weeks dhore rakhun)

---

## Phase 0 – Setup (Week 1)

**Goal:** Local e WordPress + WooCommerce cholbe, dummy order thakbe.

- [x] Local site: Laravel Herd e `codora` (WP 7.1, WooCommerce 11.1)
- [ ] WooCommerce basic setup (currency BDT, COD enable)
- [ ] [WooCommerce Smooth Generator](https://github.com/woocommerce/wc-smooth-generator) diye 50 product + 500 order (plugin installed, activate kore generate kora baki)
- [ ] Node.js LTS install (Herd er nvm diye `nvm install lts`), tarpor `npm install`
- [x] Composer (Herd er shathe ache), `composer install` done
- [ ] Git PATH e add (ekhon shudhu GitHub Desktop er bhitore ache)
- [ ] VS Code + Claude Code extension
- [x] Plugin folder + git repo (`rlorakib/Munafa`)
- [x] `CLAUDE.md` ei folder e rakhun
- [x] Plugin skeleton: `munafa.php`, `src/Plugin.php`, `src/Compatibility.php` (HPOS + Blocks), composer.json, phpcs.xml.dist, package.json

**Claude ke bolben:**
> Read CLAUDE.md. Set up the plugin skeleton: main plugin file, composer.json with PSR-4 autoload for `Munafa\` → `src/`, package.json with @wordpress/scripts, PHPCS with WordPress Coding Standards. Don't build features yet.

**Done when:** Plugin activate hoy, kono error/notice nai, `npm run build` ar `composer phpcs` chole.

---

## Phase 1 – Core profit engine (Week 2–3)

**Goal:** Protiti order er profit hishab hobe ar save hobe.

- [x] HPOS + Cart/Checkout Blocks compatibility declare
- [x] Activation e custom table `{prefix}munafa_order_profit` create (dbDelta)
- [x] Product cost: WooCommerce native COGS field use, na thakle nijer `_munafa_cost` meta field (simple + variable product)
- [x] Settings: default courier charge (inside Dhaka / outside Dhaka), COD charge %, gateway fee % (WooCommerce → Settings → Munafa)
- [x] Order create/save/refund hook e profit calculate → table e save, trash/delete e row muche jay
- [x] Order edit page e chhoto "Munafa" meta box: revenue, cost, courier, profit
- [x] Purono order er jonno "Recalculate all" tool (Action Scheduler diye batch e), install er por nije-i chole
- [x] Calculator unit test (PHPUnit, `composer test`)
- [ ] Browser e nije check: order page er meta box, product page er cost field, Munafa settings tab

**Claude ke bolben:**
> Plan first (use plan mode). Implement Phase 1 from ROADMAP.md. Start with the DB table and the profit calculator class with unit tests, then hooks, then the meta box.

**Done when:** 500 dummy order er profit table e ache, meta box e thik number dekhay.

---

## Phase 2 – Returns & courier cost (Week 4–5)

**Goal:** Return er ashol loss dhora porbe.

- [x] Custom order status `wc-returned` ("Returned")
- [x] Return hole loss = jawa + asha courier charge (return charge % setting) + gateway fee; revenue ar product cost 0, stock ferot ashe
- [x] Packaging cost setting, sob order e (delivered + returned)
- [x] Order theke district/area save (shipping state, na thakle billing state)
- [x] Per-order courier charge manually edit korar option (order page er Munafa box e)
- [x] Return reason dropdown (customer refused, wrong address, phone off, not available, other)
- [x] "Change status to returned" bulk action
- [ ] Browser e nije check: Returned status, bulk action, Munafa box er notun field

**Done when:** Returned order e profit negative dekhay, reason save hoy.

---

## Phase 3 – React dashboard (Week 6–7)

**Goal:** WooCommerce → Munafa menu te sundor dashboard.

- [x] REST API: `munafa/v1/summary`, `/trend`, `/products`, `/returns` (capability: `manage_woocommerce`)
- [x] KPI card: total revenue, net profit, margin %, return rate, return loss (ager period er tulona soho)
- [x] Profit trend chart (daily/weekly), Recharts diye
- [x] Top profitable + loss making products table (plain table, DataViews lage nai)
- [x] Return by district + by reason
- [x] Date range filter (today, 7d, 30d, 90d, this month, custom)
- [x] Product-wise data er jonno `munafa_order_items` table
- [x] Settings page React e (WooCommerce → Munafa Settings, niche "South Asia ready" dekhun)
- [x] Browser e nije check: dashboard desktop (full width fix)
- [ ] Browser e nije check: dashboard mobile, 1 second e load

---

## Phase 3.5 – South Asia ready settings

**Goal:** Code e kono desh er hardcode na, seller nije sob set korbe.

- [x] Courier zones: seller er nijer zone (naam, area = je kono desh er state ba puro desh, delivery charge, return %), prothom milano zone, baki "Everywhere else"
- [x] Payment method onujayi "COD kina" ar fee %
- [x] Return reason seller nije edit kore (rename korle purono order e-o thik naam)
- [x] Report e kon status gona hobe, setting e
- [x] `setup_done` na hole dashboard/order page e warning (purono Dhaka/outside migration bad, project live chhilo na; DB ar settings version abar 1 theke)
- [x] Report e "District" → "Area" (desh soho naam), RTL CSS (Urdu)
- [ ] Browser e nije check: Settings page (zone add/move/remove, save, recalculate)

**Done when:** 500 order er data 1 second er moddhe load hoy, mobile e-o thik dekhay.

---

## Phase 3.6 – Module system + Simple checkout (free)

**Goal:** Ek plugin, alada plugin na. Notun feature module hishebe on/off, shob setting Munafa Settings e.

- [x] Module system: `Modules\Module` + `Modules\Registry`, add-on er jonno `munafa_modules` filter
- [x] Munafa Settings tab e bhag: Profit, (on thaka module er tab), Modules, Advanced
- [x] Simple checkout module: last name, company, address line 2, city, postcode → default / required / optional / hidden (Checkout block + classic duitatei)
- [x] Phone number check: seller er pattern (`01#########`), Bangla/Hindi digit o space/dash thik kore save
- [ ] Browser e nije check: Modules tab e on, Checkout tab, block checkout e field lukano ar bhul phone e error

---

## Phase 4 – Polish & compliance (Week 8)

- [ ] Sob output escape, input sanitize, nonce check
- [ ] i18n: `.pot` generate + **Bangla (bn_BD) translation**
- [ ] `uninstall.php` (setting e "delete data on uninstall" option) – code done, "delete on" path browser/local e test baki
- [ ] [Plugin Check (PCP)](https://wordpress.org/plugins/plugin-check/) e zero error
- [ ] PHP 7.4 / 8.1 / 8.3 + latest 2 WooCommerce version e test
- [ ] `readme.txt`, banner, icon, 5 ta screenshot

**Claude ke bolben:**
> Do a security review of the whole plugin against WordPress.org guidelines. List issues first, then fix them.

---

## Phase 5 – Launch free version (Week 9)

- [ ] WordPress.org e submit (review 1–4 week)
- [ ] Review cholar shomoy: `munafa.app` landing page, docs, privacy policy
- [ ] 10–20 jon beta tester seller (Facebook group theke)
- [ ] Bangla setup video (YouTube)

---

## Phase 6 – Pro version (Week 10–12)

- [ ] Freemius SDK integrate (license, update, payment)
- [ ] Facebook ad spend import (Marketing API) → daily ad cost, order prti CAC
- [ ] Courier settlement reconciliation: Steadfast/Pathao payment CSV import → koto pelen, koto baki
- [ ] CSV/PDF export, monthly email report
- [ ] Pricing page live

---

## Pore (v2+)

- WhatsApp/SMS COD confirmation
- Courier API diye auto courier charge
- **Munafa Orders**: Messenger → WooCommerce order (F-commerce Order Desk)

---

## Claude Code diye kaj korar niyom

1. Protiti phase shuru te **plan mode** use korun, plan check kore tarpor code
2. Ek bar e ekta chhoto task din, pura phase ekshathe na
3. Protiti kaj shesh hole **git commit** (bhul hole fire jaoa jabe)
4. Kaj shesh hole browser e nije test korun, tarpor ROADMAP e `[x]` din
5. Notun niyom/decision hole CLAUDE.md update korte bolun
