<?php

return [
    'use_pessimistic_lock' => env('USE_PESSIMISTIC_LOCK', true),
    'avoid_deadlock' => env('AVOID_DEADLOCK', true),
];
