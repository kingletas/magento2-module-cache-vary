# Commerce_CacheVary

Decide what the full-page-cache key is made of, and report how many copies of every page it permits.

---

## The problem

Magento hashes the `X-Magento-Vary` cookie from every value in the HTTP context that differs from its declared default, and Varnish keys on that cookie. **Every extra value in the context multiplies the number of copies the edge holds of every cacheable page**, and nothing in a stock installation reports how many that's.

Customer segments are the usual cause on Adobe Commerce. `Magento\CustomerSegment` puts the shopper's whole segment set into the context on every request, so the cache splits by segment *combination* — n active segments permit up to 2ⁿ copies of every product, category and CMS page, on top of the guest copy.

That cost is worth paying only where a segment changes what a cached page renders: a dynamic block, a segment-scoped target rule, a catalog price rule. It buys nothing where segments drive **cart** price rules, which collect on the quote — on the cart and checkout, URLs Varnish never caches.

There's a second effect. Segments recompute in real time, so membership can change mid-session; `Magento\PageCache\Model\App\Response\HttpPlugin` answers a request whose vary cookie no longer matches with `Cache-Control: no-store`, and the edge then marks the URL hit-for-pass. A shopper crossing a segment threshold takes their own pages out of the cache.

This module lets an operator declare which values may reach the key, and fails a report when the declaration is wrong in either direction.

---

## Installation

```bash
composer require commerce/module-cache-vary
bin/magento module:enable Commerce_CacheVary
bin/magento setup:upgrade
```

**It does nothing until you switch it on.** Turning the policy on changes the cache key, which makes every page already in the cache unreachable — plan it like a cache flush, not like a setting.

---

## The report

```bash
bin/magento commerce:cache-vary:policy
```

```text
Policy: in force   Budget: 8 variant(s)
Cache: narrowing the Varnish cache key
+------------------+---------------------------------------+----------+
| Context key      | Rule                                  | Variants |
+------------------+---------------------------------------+----------+
| customer_segment | allowlist empty — the key is dropped  | 1        |
+------------------+---------------------------------------+----------+
1 cache variant(s) permitted, within a budget of 8.
No customer segment drives content on a cacheable page.
```

| Option | |
|---|---|
| `--store`, `-s` | read settings for one store id, and check only that store's website |
| `--budget`, `-b` | override the configured budget |
| `--require-enabled` | treat a policy that isn't in force as a failure |

| Exit | |
|---|---:|
| within budget and every segment covered | `0` |
| over budget, a segment that changes a cached page isn't allowlisted, or a rule governs a protected key | `1` |
| `--store` names a store that doesn't exist | `2` |

**The budget is a convention, not a measurement.** Pick the number of full catalogue copies your edge cache can hold, and treat a breach as a prompt to look rather than as an incident.

**`--store` scopes both halves.** Settings are read at that store, and the segment check narrows to that store's **website** — segments are scoped by website, settings by store, and the two have to agree or a multi-website store reports a segment that's only relevant somewhere else. A segment scoped to no website in particular is checked everywhere. Without `--store` every website is checked.

---

## Configuration

**Stores → Configuration → Advanced → Cache Vary**, or `commerce_cachevary/policy/*`.

| Setting | Default | |
|---|---|---|
| Apply The Policy | `0` | Off. Nothing is filtered until this is on. |
| Customer Segments That Change Cached Pages | *empty* | Comma-separated segment ids. |
| Cache Variants Allowed | `8` | What the report fails above. |

### Deciding what goes on the allowlist

**Leave it empty unless a segment actually changes cacheable HTML.** It does when the segment drives a dynamic block, a segment-scoped target rule, or a catalog price rule. It doesn't when the segment only drives cart price rules.

Every segment left off the list stops splitting the cache. **n segments listed permit up to 2ⁿ variants**, which is why the budget exists and why the report multiplies rather than adds.

**You don't have to work this out by hand — the report checks it.** It fails when a segment that drives cacheable content isn't allowlisted, and names both ends:

```text
Not allowlisted: segment 4 "Trade" drives dynamic block "Trade Pricing Notice".
1 segment usage(s) change a cached page and are being collapsed.
```

That's the check for the *other* failure. The budget catches a cache that's too fragmented; this catches a page that's wrongly **shared** — a shopper seeing a banner, a related-product set or a catalog price built for a segment they aren't in.

A catalog price rule whose conditions can't be decoded is reported rather than skipped, because an unreadable rule can't be proved covered by any allowlist.

Coverage is only checked while the policy is in force. With it switched off, or on a caching application the guard refuses, nothing is collapsed and there's nothing to be wrong about.

On a multi-website store, run it per store, because the allowlist is website-scoped too:

```bash
bin/magento commerce:cache-vary:policy --store 1
bin/magento commerce:cache-vary:policy --store 7
```

---

## Where it won't act

### It only acts in front of Varnish

**The policy is inert unless `system/full_page_cache/caching_application` is Varnish.**

Varnish keys on the `X-Magento-Vary` cookie in `vcl_hash`. The built-in cache keys through `Magento\Framework\App\PageCache\Identifier`, which folds in the scheme and URI and **prefers the cookie the browser sent over the value Magento just computed** — a different mechanism, and the one Magento ships as the default (`caching_application` is `1` in `Magento_PageCache`'s own `config.xml`). Narrowing a key there carries the same correctness risk and buys less, because a Redis-backed cache has no fixed in-memory store to exhaust.

The guard fails closed: unknown caching application, cache switched off, or policy switched off, and nothing is rewritten. **A store that turns the policy on without Varnish gets a report saying so, not silence.**

Fastly is Varnish underneath and uses the same cookie, but registers as caching application `42`. Adding it's a deliberate edit:

```xml
<type name="Commerce\CacheVary\Model\Vary\PolicyGuard">
    <arguments>
        <argument name="cachingApplications" xsi:type="array">
            <item name="varnish" xsi:type="number">2</item>
            <item name="fastly" xsi:type="number">42</item>
        </argument>
    </arguments>
</type>
```

### It won't collapse who the shopper is

**Rules are declared in `di.xml`, so a bad rule can reach a running system.** A rule on `customer_group` isn't fragmentation — it's the wrong price, shown to a shopper in another tier.

Two keys therefore ship **protected**, and a rule naming either takes the *whole* policy out of force rather than being quietly dropped:

| Protected key | What collapsing it would do |
|---|---|
| `customer_group` | serve one pricing tier's page to another |
| `customer_logged_in` | serve a signed-in page to a guest, or the reverse |

```text
Policy: misconfigured
Cache: a rule governs customer_group, and collapsing that would serve one shopper the page built for another
Fix the rules argument in di.xml.
```

That's the one case the report fails on **without** `--require-enabled`, because nobody chooses it — it's a mistake, not a setting. The cache key stays exactly as Magento made it in the meantime, so the site is correct while the wiring is wrong.

---

## Adobe Commerce and Open Source

Customer segments are an Adobe Commerce feature. **This module installs and runs on Open Source**, where the coverage check has nothing to read:

```text
Customer segments are not installed here, so nothing was checked against cacheable content.
```

*Not checked* isn't the same claim as *nothing found*, and reporting the second would be a green tick over an unanswered question.

`SchemaSegmentSource` reads the segment, banner, target-rule and catalog-rule tables **through the schema, naming no Adobe Commerce class**. An absent table means unavailable, not empty. `Test/Unit/OpenSourceCompatibilityTest.php` enforces it: it strips comments from every source file and fails if executable code names a Commerce-only namespace, or if the manifest requires a Commerce-only package.

To switch the check off entirely, point the preference at `NoSegmentSource`.

---

## How it works

```text
Http\Context::getVaryString()
        │
        ├─ guard refuses ───────────────▶ the framework's own hash, untouched
        │   (misconfigured, policy off, cache off, or not Varnish)
        │
        └─ guard allows
             ├─ snapshot the context (a copy — the live context is never changed)
             ├─ apply each rule to its own key
             ├─ nothing changed? ───────▶ the framework's own hash, untouched
             └─ something changed ──────▶ hash the filtered copy, same salt, same algorithm
```

The context itself is left alone, because other code reads it — `Magento\TargetRule\Model\ResourceModel\Index` reads `customer_segment` to key its own lookups, and a module that quietly blanked it would break related-product rules to fix a cache.

Two rules ship:

| Rule | What it does | Variants |
|---|---|---|
| `AllowlistedValues` | keeps only the configured values, sorted and deduplicated; drops the key when nothing survives | 2ⁿ |
| `ExcludedKey` | removes the key unconditionally | 1 |

**Sorting isn't cosmetic.** `CustomerSegment`'s own query has no `ORDER BY`, so the same set of segments arriving in a different order hashes differently and doubles the buckets on its own.

### Governing another key

Rules are `di.xml`, so a new one is configuration rather than a code change:

```xml
<virtualType name="Vendor\Module\WeeeRegionRule"
             type="Commerce\CacheVary\Model\Vary\Rule\ExcludedKey">
    <arguments>
        <argument name="key" xsi:type="string">weee_tax_region</argument>
    </arguments>
</virtualType>

<type name="Commerce\CacheVary\Model\Vary\VaryPolicy">
    <arguments>
        <argument name="rules" xsi:type="array">
            <item name="weee_tax_region" xsi:type="object">Vendor\Module\WeeeRegionRule</item>
        </argument>
    </arguments>
</type>
```

Core plugins that write to the context, each of which can fragment a cache:

| Key | Written by | Watch it when |
|---|---|---|
| `customer_segment` | `Magento\CustomerSegment` | any segment exists — this is the one that ships governed |
| `tax_rates` | `Magento\Tax` | catalog prices display **including** tax, which makes the key effectively per-address |
| `weee_tax_region` | `Magento\Weee` | FPT is on and tax is based on shipping or billing address |
| `product_list_order` `_dir` `_mode` `_limit` | `Magento\Catalog` | `catalog/frontend/remember_pagination` is on, which carries a shopper's sort choice site-wide |
| `current_currency`, `store` | `Magento\Store` | more than one currency or store view — legitimate, and worth counting |

### Where the plugin is declared

The interceptor is declared in **`etc/frontend/di.xml` and `etc/graphql/di.xml`**, not in the module's global `di.xml`.

`Magento\Framework\App\Http\Context` is built in the primary scope during bootstrap, and a plugin declared on it in a module's *global* `di.xml` never reaches the area plugin list — it binds to nothing, silently. Every core plugin on that type is declared per area for the same reason; core's global entries for it carry `<arguments>` only.

The storefront is the area that matters: `Response\Http::sendVary()` writes the cookie Varnish hashes. The `graphql` declaration is there for completeness rather than effect — GraphQL under Varnish keys on `X-Magento-Cache-Id`, which `CacheIdCalculator` builds from tax rate, logged-in state, customer group, store and currency, and **never from customer segments**. The one path that would use it belongs to the built-in cache, which the guard refuses by default.

`Test/Unit/PluginScopeTest.php` asserts the scope, because nothing else notices: valid XML, a green wiring suite and a passing unit suite all survive the mistake intact.

---

## What this doesn't change

**Guests and signed-in shoppers still never share a cache object.** A guest's context holds nothing but defaults, so `getVaryString()` returns null and no cookie is sent; a signed-in shopper always contributes `customer_group` and `customer_logged_in`. That's Magento's design and it's correct — the header differs.

It also doesn't touch invalidation, purging or TTLs.

---

## Checks

```bash
make check
```

The coding standard and all four suites — 126 tests, no database and no Magento bootstrap. The behaviour suite drives a wide set of segment combinations through the real rules and asserts they collapse; the performance suite asserts the filter costs two config reads whatever the size of the context.

Narrow it to one suite with `SUITE`:

```bash
make test SUITE=behaviour
```
