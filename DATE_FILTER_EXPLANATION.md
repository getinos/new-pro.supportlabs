# Date Filter (Temp Uploads)

## How to select dates
Use the **From Date** and **To Date** fields. They are native HTML date inputs, so the browser shows the calendar popup automatically. You can type a date or pick one from the calendar UI.

```html
<form method="get">
  <label>From Date</label>
  <input type="date" name="from_date" value="2026-02-01" />

  <label>To Date</label>
  <input type="date" name="to_date" value="2026-02-11" />

  <button type="submit">Apply Filter</button>
</form>
```

## How the calendar popup works
`<input type="date">` is a native browser control. The popup UI comes from the browser/OS, not custom JavaScript. When a date is chosen, the input value is set in `YYYY-MM-DD` format.

```js
// The browser sets this automatically after picking a date.
const from = document.querySelector('[name="from_date"]').value; // "2026-02-01"
const to = document.querySelector('[name="to_date"]').value;     // "2026-02-11"
```

## How the filter logic works
On submit, the page reloads with `from_date` and `to_date` as query params. The server parses them into timestamps (start-of-day and end-of-day) and filters files by last modified time.

```php
// Parse the date range
$fromTimestamp = $fromDate ? strtotime($fromDate.' 00:00:00') : null;
$toTimestamp   = $toDate ? strtotime($toDate.' 23:59:59') : null;

// Filter each file by last-modified time
$modifiedAt = $file->getMTime();
if ($fromTimestamp && $modifiedAt < $fromTimestamp) { continue; }
if ($toTimestamp && $modifiedAt > $toTimestamp) { continue; }
```

## Summary
- The calendar popup is the browser’s built-in date picker.
- Selected dates are sent as `from_date`/`to_date`.
- The server filters files by their last modified time within that range.
