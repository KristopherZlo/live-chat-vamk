<?php

return [
    'links' => [
        'support_email' => env('SUPPORT_EMAIL', 'zloydeveloper.info@gmail.com'),
        'github_repository' => env('GITHUB_REPO_URL', 'https://github.com/KristopherZlo/live-chat-vamk'),
        'tutorial_video_url' => env('TUTORIAL_VIDEO_URL', 'https://www.youtube.com/embed/VIDEO_ID'),
    ],
    'meta' => [
        'default_description' => 'Ghost Room is an anonymous live Q&A chat for lectures so attendees can send questions without interrupting the class.',
    ],
    'tutorial' => [
        'auto_show_routes' => ['dashboard'],
    ],
    'limits' => [
        'user' => [
            'name_max' => 255,
            'email_max' => 255,
        ],
        'room' => [
            'title_max' => 255,
            'code_max' => 255,
        ],
        'message' => [
            'content_max' => 2048,
            'poll_option_max' => 480,
            'emoji_max' => 32,
        ],
        'client_error' => [
            'message_max' => 2000,
            'stack_max' => 12000,
            'url_max' => 2048,
            'line_max' => 1000000,
            'column_max' => 1000000,
        ],
        'admin' => [
            'invite_code_max' => 64,
            'session_token_max' => 255,
            'display_name_max' => 255,
            'ip_address_max' => 255,
            'fingerprint_max' => 255,
        ],
        'update_post' => [
            'version_max' => 50,
            'title_max' => 255,
            'slug_max' => 255,
            'excerpt_max' => 500,
            'image_max_kb' => 4096,
        ],
    ],
];
