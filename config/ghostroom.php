<?php

return [
    'links' => [
        'support_email' => env('SUPPORT_EMAIL', 'zloydeveloper.info@gmail.com'), // Support contact shown in the UI.
        'github_repository' => env('GITHUB_REPO_URL', 'https://github.com/KristopherZlo/live-chat-vamk'), // Link to the repo displayed in the UI.
        'tutorial_video_url' => env('TUTORIAL_VIDEO_URL', 'https://www.youtube.com/embed/_ZLGiWFADis'), // Embedded tutorial video URL.
    ],
    'meta' => [
        'default_description' => 'Ghost Room is an anonymous live Q&A chat for lectures so attendees can send questions without interrupting the class.', // Default meta description.
    ],
    'tutorial' => [
        'auto_show_routes' => [
            'dashboard', // Routes that auto-show the tutorial modal.
        ],
    ],
    'limits' => [
        'user' => [
            'name_max' => 255, // Max user name length.
            'email_max' => 255, // Max user email length.
        ],
        'room' => [
            'title_max' => 255, // Max room title length.
            'code_max' => 255, // Max room join code length.
            'messages_per_minute_guest' => 20, // Per-guest message limit (per identity and room).
            'messages_per_minute_auth' => 40, // Per-authenticated user message limit (per identity and room).
            'messages_per_minute_ip_guest' => 6000, // Per-IP message limit for guests (room messages).
            'messages_per_minute_ip_auth' => 6000, // Per-IP message limit for authenticated users (room messages).
            'messages_per_minute_room' => 5000, // Aggregate per-room message limit.
            'messages_per_minute_fingerprint' => 60, // Per-fingerprint guest message limit within a room.
            'participant_create_per_minute' => 12, // Per-fingerprint participant creation limit.
            'participant_create_per_minute_ip' => 1200, // Per-IP participant creation limit.
            'join_per_minute_ip' => 600, // Per-IP room join attempts.
            'join_per_minute_code_ip' => 400, // Per-IP room join attempts per code.
        ],
        'web' => [
            'guest_ip_per_minute' => 6000, // Global per-IP limit for guest web routes.
            'user_per_minute' => 8000, // Global per-user limit for authenticated routes.
            'user_ip_per_minute' => 6000, // Global per-IP limit for authenticated routes.
        ],
        'message' => [
            'content_max' => 2048, // Max message content length.
            'poll_option_max' => 480, // Max poll option length.
            'emoji_max' => 32, // Max emoji string length.
        ],
        'client_error' => [
            'message_max' => 2000, // Max client error message length.
            'stack_max' => 12000, // Max client error stack length.
            'url_max' => 2048, // Max URL length for client error reports.
            'line_max' => 1000000, // Max line number for client error reports.
            'column_max' => 1000000, // Max column number for client error reports.
        ],
        'admin' => [
            'invite_code_max' => 64, // Max admin invite code length.
            'session_token_max' => 255, // Max session token length.
            'display_name_max' => 255, // Max display name length.
            'ip_address_max' => 255, // Max IP address length stored.
            'fingerprint_max' => 255, // Max fingerprint length stored.
        ],
        'update_post' => [
            'version_max' => 50, // Max update version string length.
            'title_max' => 255, // Max update title length.
            'slug_max' => 255, // Max update slug length.
            'excerpt_max' => 500, // Max update excerpt length.
            'image_max_kb' => 4096, // Max update image size in KB.
        ],
    ],
];
