<?php

namespace App\Http\Controllers;

use App\Models\ImageUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploaderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $images = ImageUploader::latest()->get();
        return response()->json($images);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'files' => 'required|array',
                'files.*' => 'required|file|max:10240', // Max 10MB
                'document_type' => 'required|string',
            ]);

            $uploadedFiles = [];

            foreach ($request->file('files') as $file) {
                try {
                    // Generate filename based on document type
                    $extension = $file->getClientOriginalExtension();
                    $fileName = $request->document_type . '_' . time() . '.' . $extension;

                    // Store file in storage/app/public/uploads
                    $path = $file->storeAs('uploads', $fileName, 'public');

                    if (!$path) {
                        throw new \Exception('Failed to store file: ' . $fileName);
                    }

                    $image = ImageUploader::create([
                        'document_type' => $request->document_type,
                        'document_path' => $path,
                        'user_id' => $request->user()->id,
                        'file_name' => $fileName,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ]);

                    $uploadedFiles[] = $image;

                } catch (\Exception $e) {
                    // Clean up any files that were uploaded before the error
                    foreach ($uploadedFiles as $uploadedFile) {
                        Storage::disk('public')->delete($uploadedFile->document_path);
                        $uploadedFile->delete();
                    }

                    return response()->json([
                        'message' => 'Error processing file: ' . $fileName,
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            if (empty($uploadedFiles)) {
                return response()->json([
                    'message' => 'No files were uploaded',
                ], 400);
            }

            return response()->json([
                'message' => count($uploadedFiles) . ' files uploaded successfully',
                'data' => $uploadedFiles
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ImageUploader $imageUploader)
    {
        return response()->json([
            'data' => $imageUploader
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ImageUploader $imageUploader)
    {
        $request->validate([
            'document_type' => 'sometimes|string',
            'status' => 'sometimes|in:active,inactive',
            'files' => 'sometimes|array',
            'files.*' => 'file|max:10240'
        ]);

        if ($request->hasFile('files')) {
            // Delete old file
            Storage::disk('public')->delete($imageUploader->document_path);

            $file = $request->file('files')[0]; // Update with first file if multiple provided
            $extension = $file->getClientOriginalExtension();
            $fileName = $request->document_type . '_' . time() . '.' . $extension;
            $path = $file->storeAs('uploads', $fileName, 'public');

            $imageUploader->update([
                'document_path' => $path,
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
        }

        $imageUploader->update($request->only(['document_type', 'status']));

        return response()->json([
            'message' => 'File updated successfully',
            'data' => $imageUploader
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ImageUploader $imageUploader)
    {
        // Delete file from storage
        Storage::disk('public')->delete($imageUploader->document_path);

        // Delete record from database
        $imageUploader->delete();

        return response()->json([
            'message' => 'File deleted successfully'
        ]);
    }
    public function downloadFile(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|string'
        ]);

        // Extract just the filenames from the full paths
        $fileNames = $request->input('files'); // now directly receiving file names
        $files = ImageUploader::whereIn('file_name', $fileNames)->get();


        if ($files->isEmpty()) {
            return response()->json([
                'message' => 'No files found: ' . implode(', ', $fileNames)
            ], 404);
        }

        if (count($files) === 1) {
            // If only one file, download directly
            $file = $files->first();
            $filePath = storage_path('app/public/' . $file->document_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'message' => 'File not found in storage'
                ], 404);
            }

            return response()->download(
                $filePath,
                $file->file_name,
                ['Content-Type' => $file->mime_type]
            );
        }

        // Create temp directory if it doesn't exist
        $tempPath = storage_path('app/temp');
        if (!file_exists($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        // Create zip file
        $zip = new \ZipArchive();
        $zipFileName = 'files_' . time() . '.zip';
        $zipPath = $tempPath . '/' . $zipFileName;

        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            foreach ($files as $file) {
                $filePath = storage_path('app/public/' . $file->document_path);
                if (file_exists($filePath)) {
                    // Add file to zip with its original name and preserve file permissions
                    $zip->addFile($filePath, $file->file_name);
                    $zip->setMtimeIndex($zip->numFiles - 1, time());
                }
            }
            $zip->close();

            // Download zip file and then delete it
            return response()->download($zipPath, $zipFileName, [
                'Content-Type' => 'application/zip'
            ])->deleteFileAfterSend(true);
        }

        return response()->json([
            'message' => 'Error creating zip file'
        ], 500);
    }



}
