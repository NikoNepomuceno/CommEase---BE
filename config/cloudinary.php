<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Cloudinary settings. Cloudinary is a cloud
    | service that offers a solution to a web application's entire image
    | management pipeline.
    |
    */

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'url' => env('CLOUDINARY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configure default upload settings for your application.
    |
    */

    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET', 'commease_uploads'),
    
    'folders' => [
        'reflection_papers' => 'commease/reflection_papers',
        'documents' => 'commease/documents',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Validation
    |--------------------------------------------------------------------------
    |
    | Configure file validation rules for uploads.
    |
    */

    'max_file_size' => 10 * 1024 * 1024, // 10MB in bytes
    'allowed_formats' => ['pdf', 'doc', 'docx', 'txt'],
    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ],
];
