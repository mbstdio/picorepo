<?php

return [
    'enabled' => (bool) env('REGISTRATION_ENABLED', false),
    'max_attempts' => (int) env('REGISTRATION_MAX_ATTEMPTS', 5),
    'resource_creation_max_attempts' => (int) env('RESOURCE_CREATION_MAX_ATTEMPTS', 10),
];
