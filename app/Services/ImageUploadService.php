<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    /**
     * Upload an image file to the specified path
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  string  $path  The storage path (e.g., 'uploads/products')
     * @param  string  $disk  The storage disk to use (default: 'public')
     * @return string|false The stored file path or false on failure
     */
    public function upload(UploadedFile $file, string $path, string $disk = 'public'): string|false
    {
        return $file->store($path, $disk);
    }

    /**
     * Upload an image file with a custom filename
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  string  $path  The storage path (e.g., 'uploads/products')
     * @param  string  $filename  The custom filename
     * @param  string  $disk  The storage disk to use (default: 'public')
     * @return string|false The stored file path or false on failure
     */
    public function uploadAs(UploadedFile $file, string $path, string $filename, string $disk = 'public'): string|false
    {
        return $file->storeAs($path, $filename, $disk);
    }

    /**
     * Delete an image file from storage
     *
     * @param  string  $path  The file path to delete
     * @param  string  $disk  The storage disk (default: 'public')
     */
    public function delete(string $path, string $disk = 'public'): bool
    {
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }

    /**
     * Get the full URL for the uploaded image
     *
     * @param  string  $path  The file path
     * @param  string  $disk  The storage disk (default: 'public')
     */
    public function getUrl(string $path, string $disk = 'public'): ?string
    {
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->path($path);
        }

        return null;
    }

    /**
     * Check if a file exists in storage
     *
     * @param  string  $path  The file path
     * @param  string  $disk  The storage disk (default: 'public')
     */
    public function exists(string $path, string $disk = 'public'): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Replace an existing image with a new one
     *
     * @param  UploadedFile  $file  The new uploaded file
     * @param  string  $path  The storage path (e.g., 'uploads/products')
     * @param  string|null  $oldPath  The old file path to delete
     * @param  string  $disk  The storage disk to use (default: 'public')
     * @return string|false The stored file path or false on failure
     */
    public function replace(UploadedFile $file, string $path, ?string $oldPath = null, string $disk = 'public'): string|false
    {
        // Delete old file if exists
        if ($oldPath) {
            $this->delete($oldPath, $disk);
        }

        // Upload new file
        return $this->upload($file, $path, $disk);
    }
}
