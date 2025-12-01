<?php

return [
    'releases' => [
        '1.2.0' => [
            'date' => '2025-12-01',
            'image' => 'whats-new/message-reactions.png',
            'image_alt' => 'Preview of the new messaging updates',
            'sections' => [
                [
                    'title' => 'Message reactions',
                    'items' => [
                        'You can now add reactions to other users\' messages. When you hover over a message, a new icon appears next to "reply" that looks like a smiling face with a plus sign. Clicking it opens a new reaction selector window.',
                    ],
                ],
                [
                    'title' => 'UI improvements',
                    'items' => [
                        'Some interface elements received new colors to make them easier to recognize. For example, ban buttons are now red.',
                        'New icons were added to certain buttons to make their purpose clearer.',
                        'UI elements are gradually changing to move toward a unified style.',
                    ],
                ],
                [
                    'title' => 'Faster interface and button responsiveness',
                    'items' => [
                        'Chat messages and reactions now use an optimistic update approach: your action first appears on the client, and only after that is it confirmed by the server. This makes the interface respond faster to your actions.',
                    ],
                ],
                [
                    'title' => 'Welcome experience',
                    'items' => [
                        'A welcome modal has been added, which you may have seen when entering the site.',
                    ],
                ],
            ],
        ],
        '1.1.0' => [
            'date' => '2026-01-15',
            'image' => 'whats-new/message-reactions.png',
            'image_alt' => 'Sneak peek of the new experience',
            'sections' => [
                [
                    'title' => '',
                    'items' => [
                        '',
                    ],
                ],
                [
                    'title' => '',
                    'items' => [
                        '',
                    ],
                ],
            ],
        ],
    ],
];
