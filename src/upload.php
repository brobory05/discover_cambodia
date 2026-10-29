<?php
/**
 * Validate + store an uploaded file. Returns the path to save in the DB, or null if nothing was uploaded.
 * public  => public/uploads/<dir>/<random>.<ext>   (images only)
 * private => storage/<dir>/<random>.<ext>          (ID cards etc., outside the web root; stored as "private:<dir>/<file>")
 */
function save_upload(string $field, string $dir, bool $private = false): ?string { return store_upload($_FILES[$field] ?? [], $dir, $private); }
function supabase_put(string $tmp, string $dir, string $name, string $mime): ?string {
    $u = parse_url(trim(env('SUPABASE_URL')));
    $base = isset($u['host']) ? ($u['scheme'] ?? 'https') . '://' . $u['host'] : '';
    $key = env('SUPABASE_SERVICE_KEY');
    $bucket = env('SUPABASE_BUCKET', 'uploads');
    if ($base === '' || $key === '') return null;
    $path = $dir . '/' . $name;
    $ch = curl_init($base . '/storage/v1/object/' . $bucket . '/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => file_get_contents($tmp),
        CURLOPT_HTTPHEADER => array_merge(str_starts_with($key, 'eyJ') ? ['Authorization: Bearer ' . $key] : [], ['apikey: ' . $key, 'Content-Type: ' . $mime, 'x-upsert: true']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) throw new RuntimeException('Cloud upload failed (HTTP ' . $code . '). Check SUPABASE_URL, SUPABASE_SERVICE_KEY and the bucket name.');
    return $base . '/storage/v1/object/public/' . $bucket . '/' . $path;
}
/** $f = one entry like $_FILES['x'] (name, tmp_name, error, size) */
function store_upload(array $f, string $dir, bool $private = false): ?string {
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Upload failed or the file is over 5 MB.');
    $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($private) $mimes['application/pdf'] = 'pdf';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($mimes[$mime])) throw new RuntimeException($private ? 'Only JPG, PNG, WEBP or PDF files are allowed.' : 'Only JPG, PNG or WEBP images are allowed.');
    $name = bin2hex(random_bytes(12)) . '.' . $mimes[$mime];
    if (!$private) { $url = supabase_put($f['tmp_name'], $dir, $name, $mime); if ($url) return $url; }
    $folder = $private ? ROOT . "/storage/$dir" : ROOT . "/public/uploads/$dir";
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) throw new RuntimeException('Could not create the upload folder.');
    if (!move_uploaded_file($f['tmp_name'], "$folder/$name")) throw new RuntimeException('Could not save the uploaded file.');
    return $private ? "private:$dir/$name" : "uploads/$dir/$name";
}