# Media & Files Documentation

## Overview

Nemesis provides powerful tools for handling file uploads, image manipulation, PDF generation, and Excel/CSV exports.

---

## File Uploads

### Handling Uploads

```php
public function upload(Request $request) {
    if ($request->hasFile('avatar')) {
        $file = $request->file('avatar');
        
        // Validate
        if (!$file->isValid()) {
            return back()->withErrors(['avatar' => 'Invalid file']);
        }
        
        // Store
        $path = $file->store('avatars');
        
        // Return path
        return $path;
    }
}
```

### File Validation

```php
$request->validate([
    'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
    'document' => 'file|mimes:pdf|max:10000'
]);
```

---

## Image Manipulation

Nemesis includes an image processing library for resizing, cropping, and filtering images.

### Resize & Crop

```php
use Nemesis\Media\Image;

// Resize
Image::make($file)
    ->resize(300, 200)
    ->save('public/uploads/thumb.jpg');

// Crop
Image::make($file)
    ->crop(100, 100, 25, 25)
    ->save('public/uploads/avatar.jpg');

// Resize and keep aspect ratio
Image::make($file)
    ->resize(300, null, function ($constraint) {
        $constraint->aspectRatio();
    })
    ->save('public/uploads/responsive.jpg');
```

### Filters & Effects

```php
Image::make($file)
    ->greyscale()
    ->blur(15)
    ->brightness(10)
    ->save('public/uploads/effect.jpg');
```

---

## PDF Generation

Generate PDFs from HTML views.

### Basic Usage

```php
use Nemesis\Media\PDF;

// Download
public function invoice($id) {
    $order = Order::find($id);
    $pdf = PDF::loadView('invoices.view', ['order' => $order]);
    return $pdf->download('invoice.pdf');
}

// Stream
public function view($id) {
    // ...
    return $pdf->stream();
}

// Save to disk
PDF::loadView('report', $data)->save('storage/reports/report.pdf');
```

---

## Excel & CSV Export

Export data to spreadsheets.

### Exporting Data

```php
use Nemesis\Media\Excel;

// Export from Collection
public function export() {
    return Excel::download(new UsersExport, 'users.xlsx');
}

// UsersExport class
class UsersExport implements FromCollection {
    public function collection() {
        return User::all();
    }
}
```

### Importing Data

```php
Excel::import(new UsersImport, $request->file('spreadsheet'));
```

---

## Storage System

Abstract file system allowing checking, reading, and writing files.

```php
use Nemesis\Support\Facades\Storage;

// Check existence
if (Storage::exists('file.jpg')) {
    // ...
}

// Read
$contents = Storage::get('file.txt');

// Write
Storage::put('file.txt', 'Contents');

// Delete
Storage::delete('file.txt');

// Download
return Storage::download('file.txt');
```

---

## Best Practices

1. **Validate files** - Check mime types and size
2. **Use storage abstraction** - Allow switching between local/S3
3. **Optimize images** - Compress images on upload
4. **Queue heavy tasks** - Process large files in background
5. **Secure uploads** - Store sensitive files outside public directory
