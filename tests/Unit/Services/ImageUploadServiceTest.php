<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImageUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageUploadService;
    }

    public function test_upload_stores_file_successfully(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test-image.jpg');

        $path = $this->service->upload($file, 'uploads/test');

        $this->assertNotFalse($path);
        $this->assertIsString($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_upload_returns_correct_path_structure(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test-image.jpg');

        $path = $this->service->upload($file, 'uploads/test');

        $this->assertStringStartsWith('uploads/test/', $path);
    }

    public function test_upload_with_different_file_types(): void
    {
        Storage::fake('public');

        $jpgFile = UploadedFile::fake()->image('image.jpg');
        $pngFile = UploadedFile::fake()->image('image.png');

        $jpgPath = $this->service->upload($jpgFile, 'uploads/test');
        $pngPath = $this->service->upload($pngFile, 'uploads/test');

        $this->assertTrue(Storage::disk('public')->exists($jpgPath));
        $this->assertTrue(Storage::disk('public')->exists($pngPath));
    }

    public function test_upload_as_stores_file_with_custom_filename(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('original.jpg');
        $customFilename = 'custom-name.jpg';

        $path = $this->service->uploadAs($file, 'uploads/test', $customFilename);

        $this->assertNotFalse($path);
        $this->assertEquals('uploads/test/custom-name.jpg', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_upload_as_preserves_file_extension(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.png');

        $path = $this->service->uploadAs($file, 'uploads/test', 'custom.png');

        $this->assertStringEndsWith('.png', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_delete_removes_existing_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->service->upload($file, 'uploads/test');

        $this->assertTrue(Storage::disk('public')->exists($path));

        $result = $this->service->delete($path);

        $this->assertTrue($result);
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_delete_returns_false_for_nonexistent_file(): void
    {
        Storage::fake('public');

        $result = $this->service->delete('nonexistent/path.jpg');

        $this->assertFalse($result);
    }

    public function test_delete_with_custom_disk(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->service->upload($file, 'uploads/test', 'local');

        $this->assertTrue(Storage::disk('local')->exists($path));

        $result = $this->service->delete($path, 'local');

        $this->assertTrue($result);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_get_url_returns_path_for_existing_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->service->upload($file, 'uploads/test');

        $url = $this->service->getUrl($path);

        $this->assertNotNull($url);
        $this->assertIsString($url);
        $this->assertStringContainsString($path, $url);
    }

    public function test_get_url_returns_null_for_nonexistent_file(): void
    {
        Storage::fake('public');

        $url = $this->service->getUrl('nonexistent/path.jpg');

        $this->assertNull($url);
    }

    public function test_exists_returns_true_for_existing_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->service->upload($file, 'uploads/test');

        $exists = $this->service->exists($path);

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_for_nonexistent_file(): void
    {
        Storage::fake('public');

        $exists = $this->service->exists('nonexistent/path.jpg');

        $this->assertFalse($exists);
    }

    public function test_replace_uploads_new_file_and_deletes_old(): void
    {
        Storage::fake('public');

        $oldFile = UploadedFile::fake()->image('old.jpg');
        $oldPath = $this->service->upload($oldFile, 'uploads/test');

        $this->assertTrue(Storage::disk('public')->exists($oldPath));

        $newFile = UploadedFile::fake()->image('new.jpg');
        $newPath = $this->service->replace($newFile, 'uploads/test', $oldPath);

        $this->assertNotFalse($newPath);
        $this->assertTrue(Storage::disk('public')->exists($newPath));
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
        $this->assertNotEquals($oldPath, $newPath);
    }

    public function test_replace_uploads_new_file_without_old_path(): void
    {
        Storage::fake('public');

        $newFile = UploadedFile::fake()->image('new.jpg');
        $newPath = $this->service->replace($newFile, 'uploads/test');

        $this->assertNotFalse($newPath);
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_replace_with_null_old_path(): void
    {
        Storage::fake('public');

        $newFile = UploadedFile::fake()->image('new.jpg');
        $newPath = $this->service->replace($newFile, 'uploads/test', null);

        $this->assertNotFalse($newPath);
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_replace_handles_nonexistent_old_file_gracefully(): void
    {
        Storage::fake('public');

        $newFile = UploadedFile::fake()->image('new.jpg');
        $newPath = $this->service->replace($newFile, 'uploads/test', 'nonexistent/old.jpg');

        $this->assertNotFalse($newPath);
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_upload_to_different_disks(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $file1 = UploadedFile::fake()->image('public.jpg');
        $file2 = UploadedFile::fake()->image('local.jpg');

        $publicPath = $this->service->upload($file1, 'uploads/test', 'public');
        $localPath = $this->service->upload($file2, 'uploads/test', 'local');

        $this->assertTrue(Storage::disk('public')->exists($publicPath));
        $this->assertTrue(Storage::disk('local')->exists($localPath));
        $this->assertFalse(Storage::disk('public')->exists($localPath));
        $this->assertFalse(Storage::disk('local')->exists($publicPath));
    }

    public function test_upload_creates_directory_structure(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');

        $path = $this->service->upload($file, 'uploads/deep/nested/directory');

        $this->assertNotFalse($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringStartsWith('uploads/deep/nested/directory/', $path);
    }

    public function test_multiple_uploads_generate_unique_filenames(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('test.jpg');
        $file2 = UploadedFile::fake()->image('test.jpg');

        $path1 = $this->service->upload($file1, 'uploads/test');
        $path2 = $this->service->upload($file2, 'uploads/test');

        $this->assertNotEquals($path1, $path2);
        $this->assertTrue(Storage::disk('public')->exists($path1));
        $this->assertTrue(Storage::disk('public')->exists($path2));
    }

    public function test_upload_handles_large_file_sizes(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('large.jpg')->size(2048); // 2MB

        $path = $this->service->upload($file, 'uploads/test');

        $this->assertNotFalse($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_service_methods_are_chainable_with_fluent_interface(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->service->upload($file, 'uploads/test');

        $this->assertTrue($this->service->exists($path));
        $this->assertNotNull($this->service->getUrl($path));
        $this->assertTrue($this->service->delete($path));
        $this->assertFalse($this->service->exists($path));
    }

    public function test_replace_with_custom_disk(): void
    {
        Storage::fake('local');

        $oldFile = UploadedFile::fake()->image('old.jpg');
        $oldPath = $this->service->upload($oldFile, 'uploads/test', 'local');

        $newFile = UploadedFile::fake()->image('new.jpg');
        $newPath = $this->service->replace($newFile, 'uploads/test', $oldPath, 'local');

        $this->assertTrue(Storage::disk('local')->exists($newPath));
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
    }

    public function test_upload_as_overwrites_existing_file_with_same_name(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('first.jpg', 100, 100);
        $path1 = $this->service->uploadAs($file1, 'uploads/test', 'same-name.jpg');

        $this->assertTrue(Storage::disk('public')->exists($path1));
        $originalSize = Storage::disk('public')->size($path1);

        $file2 = UploadedFile::fake()->image('second.jpg', 200, 200);
        $path2 = $this->service->uploadAs($file2, 'uploads/test', 'same-name.jpg');

        $this->assertEquals($path1, $path2);
        $this->assertTrue(Storage::disk('public')->exists($path2));

        $newSize = Storage::disk('public')->size($path2);
        $this->assertNotEquals($originalSize, $newSize);
    }

    public function test_exists_with_custom_disk(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->service->upload($file, 'uploads/test', 'local');

        $this->assertTrue($this->service->exists($path, 'local'));
        $this->assertFalse($this->service->exists($path, 'public'));
    }
}
