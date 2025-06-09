<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FileUploadController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Upload reflection paper to Cloudinary
     */
    public function uploadReflectionPaper(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'event_id' => ['required', 'exists:events,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $eventId = $request->event_id;
        $volunteerId = $request->user()->id;

        // Validate file type and size
        $validation = $this->cloudinaryService->validateFile($file);
        if (!$validation['valid']) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $validation['errors']
            ], 422);
        }

        // Upload to Cloudinary
        $result = $this->cloudinaryService->uploadReflectionPaper($file, $eventId, $volunteerId);

        if (!$result['success']) {
            return response()->json([
                'message' => 'File upload failed',
                'error' => $result['error']
            ], 500);
        }

        return response()->json([
            'message' => 'File uploaded successfully',
            'data' => [
                'url' => $result['url'],
                'public_id' => $result['public_id'],
                'filename' => $result['original_filename'],
                'size' => $result['bytes'],
                'format' => $result['format']
            ]
        ], 200);
    }

    /**
     * Delete reflection paper from Cloudinary
     */
    public function deleteReflectionPaper(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->cloudinaryService->deleteFile($request->public_id);

        if (!$result['success']) {
            return response()->json([
                'message' => 'File deletion failed',
                'error' => $result['error']
            ], 500);
        }

        return response()->json([
            'message' => 'File deleted successfully'
        ], 200);
    }

    /**
     * Get file information from Cloudinary
     */
    public function getFileInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->cloudinaryService->getFileInfo($request->public_id);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to get file info',
                'error' => $result['error']
            ], 500);
        }

        return response()->json([
            'message' => 'File info retrieved successfully',
            'data' => $result['data']
        ], 200);
    }
}
