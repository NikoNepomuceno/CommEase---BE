<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true
            ]
        ]);

        $this->cloudinary = new Cloudinary();
    }

    /**
     * Upload a reflection paper to Cloudinary
     *
     * @param UploadedFile $file
     * @param string $eventId
     * @param string $volunteerId
     * @return array
     */
    public function uploadReflectionPaper(UploadedFile $file, string $eventId, string $volunteerId): array
    {
        try {
            $publicId = "reflection_paper_event_{$eventId}_volunteer_{$volunteerId}_" . time();
            
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getPathname(),
                [
                    'public_id' => $publicId,
                    'folder' => config('cloudinary.folders.reflection_papers'),
                    'resource_type' => 'auto',
                    'format' => $file->getClientOriginalExtension(),
                    'context' => [
                        'event_id' => $eventId,
                        'volunteer_id' => $volunteerId,
                        'original_name' => $file->getClientOriginalName(),
                        'upload_date' => now()->toISOString()
                    ]
                ]
            );

            return [
                'success' => true,
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'format' => $result['format'],
                'bytes' => $result['bytes'],
                'original_filename' => $file->getClientOriginalName()
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a file from Cloudinary
     *
     * @param string $publicId
     * @return array
     */
    public function deleteFile(string $publicId): array
    {
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId);
            
            return [
                'success' => $result['result'] === 'ok',
                'result' => $result['result']
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary delete failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file info from Cloudinary
     *
     * @param string $publicId
     * @return array
     */
    public function getFileInfo(string $publicId): array
    {
        try {
            $result = $this->cloudinary->adminApi()->asset($publicId);
            
            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            Log::error('Cloudinary get file info failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate file before upload
     *
     * @param UploadedFile $file
     * @return array
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > config('cloudinary.max_file_size')) {
            $errors[] = 'File size exceeds maximum allowed size of ' . (config('cloudinary.max_file_size') / 1024 / 1024) . 'MB';
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, config('cloudinary.allowed_formats'))) {
            $errors[] = 'File format not allowed. Allowed formats: ' . implode(', ', config('cloudinary.allowed_formats'));
        }

        // Check MIME type
        if (!in_array($file->getMimeType(), config('cloudinary.allowed_mime_types'))) {
            $errors[] = 'File type not allowed.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
