<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class FlipbookController extends Controller
{


    public function index()
    {
        $books = Book::latest()->get();
        return view('admin.pages.list', compact('books'));
    }

    public function create()
    {
        return view('admin.pages.upload');
    }


    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        $isBulk = $request->has('is_bulk');

        $zipFile = $request->file('file');
        $zip = new ZipArchive;
        $tmpPath = storage_path('app/temp_zip');

        // Clear temp folder first
        if (File::exists($tmpPath)) {
            File::deleteDirectory($tmpPath);
        }
        File::makeDirectory($tmpPath, 0755, true);

        if ($zip->open($zipFile) === true) {
            $zip->extractTo($tmpPath);
            $zip->close();
        } else {
            return Response::json(['success' => false, 'message' => 'Unable to extract ZIP file'], 500);
        }

        $flipbookRoot = base_path('../uploads');
        if (!File::exists($flipbookRoot)) {
            File::makeDirectory($flipbookRoot, 0755, true);
        }

        $uploadedBooks = [];

        if ($isBulk) {

            $folders = File::directories($tmpPath);

            foreach ($folders as $folder) {
                $folderName = basename($folder);
                $slug = Str::slug($folderName);
                $destination = $flipbookRoot . '/' . $slug;

                File::copyDirectory($folder, $destination);

                Book::create([
                    'title' => $folderName,
                    'slug' => $slug,
                    'path' => $destination,
                ]);

                $uploadedBooks[] = [
                    'title' => $folderName,
                    'slug' => $slug,
                ];
            }
        } else {
            // Single book (extract folder contents to slug-named folder)
            $originalName = pathinfo($zipFile->getClientOriginalName(), PATHINFO_FILENAME);
            $slug = Str::slug($originalName);
            $destination = $flipbookRoot . '/' . $slug;

            File::copyDirectory($tmpPath, $destination);

            Book::create([
                'title' => $originalName,
                'slug' => $slug,
                'path' => $destination,
            ]);

            $uploadedBooks[] = [
                'title' => $originalName,
                'slug' => $slug,
            ];
        }

        File::deleteDirectory($tmpPath);

        return Response::json([
            'success' => true,
            'message' => $isBulk ? 'Bulk flipbooks uploaded successfully.' : 'Flipbook uploaded successfully.',
            'books' => $uploadedBooks
        ]);
    }


    public function getS3PresignedUrl(Request $request)
    {
        try {
            $validated = $request->validate([
                'filename' => 'required|string',
                'filetype' => 'nullable|string'
            ]);

            $filename = $validated['filename'];
            $filetype = $validated['filetype'] ?? 'application/zip';

            // Generate a unique path for the file
            $uuid = Str::uuid()->toString();
            $path = 'flipbooks/' . $uuid . '/' . $filename;

            // First check if AWS credentials are properly configured
            if (!config('filesystems.disks.s3.key') || !config('filesystems.disks.s3.secret') || !config('filesystems.disks.s3.region')) {
                Log::error('AWS S3 credentials are missing or incomplete');
                return response()->json(['error' => 'S3 configuration is incomplete'], 500);
            }

            // Create S3 client with credentials
            try {
                $s3Client = new S3Client([
                    'region' => config('filesystems.disks.s3.region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('filesystems.disks.s3.key'),
                        'secret' => config('filesystems.disks.s3.secret'),
                    ],
                ]);

                $bucket = config('filesystems.disks.s3.bucket');

                // Test the S3 connection by checking if the bucket exists
                if (!$s3Client->doesBucketExist($bucket)) {
                    Log::error("S3 bucket {$bucket} does not exist or is not accessible");
                    return response()->json(['error' => 'S3 bucket not accessible'], 500);
                }
            } catch (\Exception $e) {
                Log::error('AWS S3 client error: ' . $e->getMessage());
                return response()->json(['error' => 'AWS S3 client error: ' . $e->getMessage()], 500);
            }

            // Create simple presigned URL for PUT operation
            try {
                $command = $s3Client->getCommand('PutObject', [
                    'Bucket' => $bucket,
                    'Key' => $path,
                    'ContentType' => $filetype,
                ]);

                $presignedRequest = $s3Client->createPresignedRequest($command, '+1 hour');
                $presignedUrl = (string)$presignedRequest->getUri();

                // Calculate the public URL for this object after upload
                $publicUrl = "https://{$bucket}.s3.{$s3Client->getRegion()}.amazonaws.com/{$path}";

                return response()->json([
                    'url' => $presignedUrl,
                    'method' => 'PUT',
                    'fields' => [],
                    'headers' => [
                        'Content-Type' => $filetype
                    ],
                    'path' => $path,
                    'publicUrl' => $publicUrl
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create presigned URL: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to create presigned URL: ' . $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            Log::error('S3 presign general error: ' . $e->getMessage());
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    public function storeFlipbookMetadata(Request $request)
    {
        Log::info('request', $request->all());

        $validated = $request->validate([
            'name' => 'required|string',
            'url' => 'required|url',
        ]);

        Log::info('Incoming metadata', $validated);

        $slug = Str::slug($validated['name']);

        $book = Book::create([
            'title' => $validated['name'],
            'slug' => $slug,
            'path' => $validated['url'],
        ]);

        return response()->json([
            'message' => 'Flipbook metadata stored successfully.',
            'book' => $book
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'flipbook' => 'required|file|max:102400',
        ]);

        $file = $request->file('flipbook');
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($originalName);
        $tempPath = storage_path("app/temp/{$slug}");

        // Make temp directory
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        // Move ZIP to temp location
        $zipPath = "{$tempPath}/flipbook.zip";
        $file->move($tempPath, 'flipbook.zip');

        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempPath);
            $zip->close();
        } else {
            return response()->json(['error' => 'Failed to unzip flipbook'], 500);
        }

        // Upload to S3 (excluding the ZIP itself)
        $s3Path = "flipbooks/{$slug}/";
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $filePath) {
            if ($filePath->isFile() && $filePath->getFilename() !== 'flipbook.zip') {
                $localPath = $filePath->getPathname();
                $relativePath = str_replace($tempPath . '/', '', $localPath);
                $s3FullPath = $s3Path . $relativePath;

                Storage::disk('s3')->put($s3FullPath, file_get_contents($localPath), 'public');
            }
        }

        // Clean up local temp files
        File::deleteDirectory($tempPath);

        // Save to DB if needed (optional)
        Book::create([
            'name' => $originalName,
            'slug' => $slug,
            'url' => "https://books.futurecampus.in/{$slug}/"
        ]);

        return response()->json([
            'message' => 'Flipbook uploaded successfully.',
            'publicUrl' => "https://books.futurecampus.in/{$slug}/"
        ]);
    }
}
