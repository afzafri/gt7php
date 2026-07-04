# Car names

Telemetry only carries a numeric car id. To show real names, the scraper builds
a lookup file from the GT7 website. This page covers how it works and how to run
it.

## Running the scraper

```bash
php tools/scrape-cars.php
```

Run it the first time you set up gt7php, and again whenever GT7 adds new cars.
It writes `data/cars.json`, keyed by numeric car id. Without it the cockpit
shows `Car #<id>`.

`data/cars.json` is gitignored, so each user builds their own copy.

## How the scraper works

The GT7 site ships its car database as a content hashed JavaScript asset, so the
filenames change on every rebuild and cannot be hardcoded. The scraper discovers
the current one by walking a chain:

```text
carlist page  ->  index-*.js  ->  cars.us-*.js  ->  car data
```

1. Fetch the car list page and find the `index-*.js` reference.
2. Fetch that file and find the `cars.us-*.js` reference.
3. Fetch the car data module.
4. Convert its JavaScript object into JSON and reshape it.

## Parsing the data

The data is a minified JavaScript object, not JSON. Keys are unquoted, and
string values use three different quote styles: double, single, and backtick.
The site uses single or backtick quotes when a name contains double quotes, such
as `LEXUS LF-LC GT "Vision Gran Turismo"`. A single pass tokenizer in the
scraper normalizes all of this into valid JSON.

## Using the lookup

`lib/cars.php` loads the JSON once and maps a car id to a name. The cockpit calls
it in the receive loop and falls back to the raw id when a name is not found.

```php
$name = gt7_car_name($state['car_id']);
if ($name !== null) {
    $state['car_name'] = $name;
}
```

## Next Steps

- [Getting started](01-getting-started.md)
- [Scripts](02-scripts.md)
