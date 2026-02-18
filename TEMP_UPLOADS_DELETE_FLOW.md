# Temp Uploads Delete Flow

## What to delete
- Temporary user uploads stored under each user’s temp uploads folder.
- These are safe to remove; they’re only staging files for uploads.

## How the delete works (logic)
1. The system lists temp files by scanning the temp uploads folders.
2. A date range filter limits the list using each file’s last modified time.
3. The user selects files and confirms deletion.
4. The server validates the selection and deletes only files inside the allowed temp folder.
5. The page reloads to refresh the list after deletion.

## UI flow (summary)
- The UI shows a table of temp files with checkboxes per row.
- “Select all” toggles all items in the table.
- A date range filter sits beside the section title to quickly filter files.
- The Size column displays the total size of the listed files.
- Clicking “Delete Selected” opens a confirmation dialog; confirming runs the delete.

## Code snippets (core ideas)

**Client-side confirm + submit**
```js
$(document).on('click', '#lwOpenDeleteTempUploadsConfirm', function (e) {
  e.preventDefault();
  if ($('.lw-temp-upload-checkbox:checked').length < 1) {
    showWarnMessage('Please select at least one file.');
    return;
  }
  showConfirmation('#lwDeleteTempUploads-template', function () {
    $('#lwTempUploadsForm').trigger('submit');
  });
});
```

**Server-side delete with path safety**
```php
foreach ($request->input('temp_uploads', []) as $item) {
    $fileName = basename($item); // prevent path traversal
    $filePath = $tempUploadPath . DIRECTORY_SEPARATOR . $fileName;
    if (File::exists($filePath) && File::isFile($filePath)) {
        File::delete($filePath);
    }
}
```

**Date range filter (file modified time)**
```php
if ($fromTimestamp && $modifiedAt < $fromTimestamp) { continue; }
if ($toTimestamp && $modifiedAt > $toTimestamp) { continue; }
```

## Notes
- Deletion only touches files under the temp uploads directory.
- The UI shows total size for the current filtered list, not all files.
- After successful deletion, the page reloads to re-scan and refresh the list.
