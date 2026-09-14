# CosmoShop reference data

`jvmoebel.de-references.json` is the reviewed snapshot of delivery times and
units from the CosmoShop audit database. It is required before importing a
CosmoShop DE product CSV; it lets a fresh Shopware installation resolve the
CSV's source `unit_id` and `delivery_time_id` values.

Load it once after deploying the backend (the command is idempotent):

```bash
bin/console jv:catalog:upsert-cosmoshop-references \
  data/import/cosmoshop/jvmoebel.de-references.json \
  --market=jvmoebel.de \
  --no-interaction
```

The source delivery-time ID `5` ("derzeit nicht lieferbar!") is deliberately
not in the snapshot. It is not a delivery-time range and Shopware must not
invent a `0–0` delivery time for it. A product CSV row using that ID is an
invalid record until the unavailable-product policy is implemented.

This snapshot has the German labels needed for `jvmoebel.de`. It must not be
used for another market: a market-specific snapshot with the required locale
labels is needed there. Product CSV exports are operational artifacts and are
not committed to this directory.
