<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use ZipArchive;

class FlipbookController extends Controller
{


    public function index()
    {
        $books = Book::latest()->get();
        // return $books;
        return view('admin.pages.list', compact('books'));
    }

    public function create()
    {
        return view('admin.pages.upload');
    }


    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|file|mimes:zip',
    //     ]);

    //     $isBulk = $request->has('is_bulk');

    //     $zipFile = $request->file('file');
    //     $zip = new ZipArchive;
    //     $tmpPath = storage_path('app/temp_zip');

    //     // Clear temp folder first
    //     if (File::exists($tmpPath)) {
    //         File::deleteDirectory($tmpPath);
    //     }
    //     File::makeDirectory($tmpPath, 0755, true);

    //     if ($zip->open($zipFile) === true) {
    //         $zip->extractTo($tmpPath);
    //         $zip->close();
    //     } else {
    //         return Response::json(['success' => false, 'message' => 'Unable to extract ZIP file'], 500);
    //     }

    //     $flipbookRoot = base_path('./uploads');
    //     if (!File::exists($flipbookRoot)) {
    //         File::makeDirectory($flipbookRoot, 0755, true);
    //     }

    //     $uploadedBooks = [];

    //     if ($isBulk) {

    //         $folders = File::directories($tmpPath);

    //         foreach ($folders as $folder) {
    //             $folderName = basename($folder);
    //             $slug = Str::slug($folderName);
    //             $destination = $flipbookRoot . '/' . $slug;

    //             File::copyDirectory($folder, $destination);

    //             Book::create([
    //                 'title' => $folderName,
    //                 'slug' => $slug,
    //                 'path' => $destination,
    //             ]);

    //             $uploadedBooks[] = [
    //                 'title' => $folderName,
    //                 'slug' => $slug,
    //             ];
    //         }
    //     } else {
    //         // Single book (extract folder contents to slug-named folder)
    //         $originalName = pathinfo($zipFile->getClientOriginalName(), PATHINFO_FILENAME);
    //         $slug = Str::slug($originalName);
    //         $destination = $flipbookRoot . '/' . $slug;

    //         File::copyDirectory($tmpPath, $destination);

    //         Book::create([
    //             'title' => $originalName,
    //             'slug' => $slug,
    //             'path' => $destination,
    //         ]);

    //         $uploadedBooks[] = [
    //             'title' => $originalName,
    //             'slug' => $slug,
    //         ];
    //     }

    //     File::deleteDirectory($tmpPath);

    //     return Response::json([
    //         'success' => true,
    //         'message' => $isBulk ? 'Bulk flipbooks uploaded successfully.' : 'Flipbook uploaded successfully.',
    //         'books' => $uploadedBooks
    //     ]);
    // }


    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        $isBulk = $request->has('is_bulk');
        $zipFile = $request->file('file');
        $zip = new ZipArchive;
        $tmpPath = storage_path('app/temp_zip');

        // Clear temp folder
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

        $flipbookRoot = base_path('uploads');
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
                $this->createSymlink($slug, $destination);

                Book::create([
                    'title' => $folderName,
                    'slug' => $slug,
                    'path' => $destination,
                ]);

                $uploadedBooks[] = ['title' => $folderName, 'slug' => $slug];
            }
        } else {
            $originalName = pathinfo($zipFile->getClientOriginalName(), PATHINFO_FILENAME);
            $slug = Str::slug($originalName);
            $destination = $flipbookRoot . '/' . $slug;

            File::copyDirectory($tmpPath, $destination);
            $this->createSymlink($slug, $destination);

            Book::create([
                'title' => $originalName,
                'slug' => $slug,
                'path' => $destination,
            ]);

            $uploadedBooks[] = ['title' => $originalName, 'slug' => $slug];
        }

        File::deleteDirectory($tmpPath);

        return Response::json([
            'success' => true,
            'message' => $isBulk ? 'Bulk flipbooks uploaded successfully.' : 'Flipbook uploaded successfully.',
            'books' => $uploadedBooks
        ]);
    }

    private function createSymlink($slug, $target)
    {
        $symlinkPath = public_path($slug);

        // Remove if exists
        if (File::exists($symlinkPath) || is_link($symlinkPath)) {
            File::delete($symlinkPath);
        }

        // Create symlink (works on Linux/Mac, needs admin cmd on Windows)
        // symlink($target, $symlinkPath);
    }
}
