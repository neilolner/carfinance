# Scenario 2: API Performance (`GET /api/orders`)

## The problem

The endpoint had an N+1 query problem. `Order::all()` loaded only the orders, then `$order->customer` and `$order->items` lazy-loaded inside the loop: **1 + 2N queries**. On the seeded data (1,000 orders, 5,000 items) I measured **2,001 queries, about 3.5 s and 635 KB** before changing anything.

The foreign key indexes already existed, so the schema wasn't the cause. The endpoint also had an unbounded result set, `select *` and no ordering. Money fields came back as strings on MySQL but floats on SQLite.

## What I changed

1. **Eager loading with column lists:** 3 queries however much data there is. The foreign keys must stay in the column lists, or the eager-load mapping silently breaks.
2. **`orderBy('id')`** for deterministic results, which cursor pagination also needs.
3. **API Resources** (Order, Customer, OrderItem): the response format lives in one reusable place, and the controller just builds the query.
4. **`decimal:2` casts**, so money is always a string like `"12.50"`.
5. **Cursor pagination:** `per_page` defaults to 50 (`$perPage` on the model) with a maximum of 100. Invalid values return a 422.

| | Before | N+1 fixed | Paginated |
|---|---|---|---|
| Queries | 2,001 | 3 | 3 on every page |
| Response | 635 KB | 635 KB | 32 KB |
| Time | ~3.5 s | ~0.7 s | 0.04–0.13 s |

## Testing

I wrote the tests first and confirmed they failed before changing the code. The existing tests each created a single order, so they couldn't detect an N+1. The new tests cover:

- query count (`expectsDatabaseQueryCount`)
- no lazy loading (`preventLazyLoading`, inside that test only)
- each order getting its own customer and items
- empty items
- ordering
- exact values and types
- pagination: cursor walk with no gaps or overlap, `per_page`, the 422, and the query count on later pages

**Incidental fix (affects every scenario):** the tests were running against the dev MySQL database and wiping it. Docker's `env_file` puts `DB_*` into `$_SERVER`, which Laravel reads before `$_ENV`, and PHPUnit's `<env>` never sets `$_SERVER`. I added `<server>` entries to `phpunit.xml`, and the tests now use in-memory SQLite.

## Tradeoffs

- **Pagination breaks the response contract.** The response goes from a bare array to `{data, links, meta}`. I accepted that because the unbounded list was the remaining scaling problem. In production I'd version the endpoint or coordinate with API consumers.
- **Cursor rather than offset pagination.** It stays fast at any depth, with no `OFFSET` and no `COUNT(*)`: page 10 is as fast as page 1. The cost is no page numbers or totals. A malformed cursor quietly returns page 1, which is Laravel's default, and I left it.
- **No caching**, as the brief asked. The problem was the queries, so I fixed it there.
- **No global `preventLazyLoading()`.** It would catch future N+1s across the app, but it risked breaking the other scenarios' tests.
- **Explicit column lists** shrink the queries, but a new response field also needs adding to the query. The exact-values test catches it if someone forgets.
- **Out of scope:** the seeder is slow because every row insert commits separately. Wrapping it in a transaction would fix that.
