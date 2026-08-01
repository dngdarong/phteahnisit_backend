<?php

return [
    /*
    | Business Rules doc: "Maximum upload size should be configurable."
    | Value in kilobytes (Laravel's `max:` image rule uses KB).
    */
    'max_image_kb' => env('MAX_ROOM_IMAGE_KB', 5120), // 5MB default
];
