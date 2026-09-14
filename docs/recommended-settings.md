# Recommended settings

What to set on a production store, and why. Three settings, and none of them does anything until Varnish is the full-page cache.

## Contents

- [The prerequisite](#the-prerequisite)
- [The short version](#the-short-version)
- [The allowlist derives itself](#the-allowlist-derives-itself)
- [Why the budget should be tight rather than generous](#why-the-budget-should-be-tight-rather-than-generous)
- [On Open Source](#on-open-source)
- [On Fastly](#on-fastly)
- [Where the settings can be set](#where-the-settings-can-be-set)
- [Wiring it into a deploy](#wiring-it-into-a-deploy)

## The prerequisite

```bash
bin/magento config:show system/full_page_cache/caching_application
```

A `2` means Varnish. A `1` means Magento's built-in cache, which is the shipped default, and this module refuses to rewrite the key in front of it.

**On the built-in cache, leave the policy switched off.** Turning it on is harmless, because the guard refuses and the report says so. But a setting that reads *yes* while nothing happens is exactly the kind of thing the next person trusts.

## The short version

| Setting | Production value | Why |
|---|---|---|
| `policy/enabled` | `1` on Varnish, `0` otherwise | Changing the key makes every cached page unreachable once |
| `policy/cacheable_customer_segments` | empty, then exactly what the report demands | Every segment listed doubles the copies the edge holds of every page |
| `policy/bucket_budget` | 2 to the power of the settled allowlist size | Tighter than the shipped `8`, and it turns the budget into a drift detector |

```bash
bin/magento kingletas:cache-vary:policy
```

```bash
bin/magento config:set kingletas_cachevary/policy/bucket_budget 1
```

```bash
bin/magento config:set kingletas_cachevary/policy/enabled 1
```

```bash
bin/magento cache:flush
```

**The flush is not optional.** Turning the policy on changes the cache key, so everything already in the full-page cache becomes unreachable and is fetched again once. Plan it like a flush, not like a setting.

## The allowlist derives itself

**Do not work this out by hand.** Start empty, run the report, and it either passes or names the segment you are missing:

```text
Not allowlisted: segment 4 "Trade" drives dynamic block "Trade Pricing Notice".
1 segment usage(s) change a cached page and are being collapsed.
```

The check reads every dynamic block, segment-scoped target rule and catalog price rule, and fails while a segment that changes a cached page is off the list. **Add exactly the segments it names and nothing else.**

A segment that only drives cart price rules buys you nothing on the list. Those rules collect on the quote, on the cart and checkout, which are never cached in the first place.

On a multi-website store the allowlist is website-scoped and so is the check, so run it per store:

```bash
bin/magento kingletas:cache-vary:policy --store 1
```

## Why the budget should be tight rather than generous

The report's ceiling is the product, over every governed key, of 2 to the power of that key's allowlist size. An empty allowlist is exactly `1`. Three segments allowlisted is `8`, which is where the shipped default came from.

Setting the budget to that same number, rather than leaving headroom, means the report **fails the first time somebody adds a segment to the allowlist without raising the budget on purpose.**

| Allowlisted segments | Ceiling | Budget to set |
|---:|---:|---:|
| 0 | 1 | `1` |
| 1 | 2 | `2` |
| 2 | 4 | `4` |
| 3 | 8 | `8` |

**The trade-off is real and it is yours to take.** A tight budget means a legitimate allowlist addition needs a second config change. A loose one means the cache can quietly fragment eightfold before anything complains.

On a store where segments are added by a marketing team rather than by a deploy, tight is the right side of that.

## On Open Source

Customer segments are an Adobe Commerce feature. The module still installs and runs here, the `customer_segment` key never exists, and the coverage check has nothing to read:

```text
Customer segments are not installed here, so nothing was checked against cacheable content.
```

That is not the same claim as *nothing found*, and the module deliberately refuses to print the second.

**So the production configuration on Open Source is the simplest one there is:** enabled, empty allowlist, budget `1`.

## On Fastly

Fastly is Varnish underneath and uses the same cookie, but it registers as caching application `42`, which the guard does not accept out of the box. Adding it is a deliberate edit in your own module:

```xml
<type name="Kingletas\CacheVary\Model\Vary\PolicyGuard">
    <arguments>
        <argument name="cachingApplications" xsi:type="array">
            <item name="varnish" xsi:type="number">2</item>
            <item name="fastly" xsi:type="number">42</item>
        </argument>
    </arguments>
</type>
```

## Where the settings can be set

| Setting | Default | Website | Store view |
|---|---|---|---|
| `policy/enabled` | yes | yes | no |
| `policy/cacheable_customer_segments` | yes | yes | no |
| `policy/bucket_budget` | yes | no | no |

Values are read at store scope, so a website value falls through to every store view under it. The budget is global, which is fine: it is a number about your edge cache rather than about a website.

## Wiring it into a deploy

`--require-enabled` is what turns the report from a listing into a gate. Without it, a policy that is no longer in force is printed and the command still exits `0`.

```bash
bin/magento kingletas:cache-vary:policy --require-enabled
```

It exits non-zero when the ceiling is over budget, when a segment that changes a cached page is not allowlisted, when a rule governs a protected key, or when the policy is not in force.

Then confirm the cache is doing what you think, with two requests to the same page:

```bash
curl -s -o /dev/null -D - https://example.test/gear/bags.html | grep X-Magento-Cache-Debug
```

`MISS` the first time, `HIT` the second.

## Where to go next

- [README](../README.md)
