<?php

return [
    'random_start_hour' => max(0, min(23, (int) env('ACCOUNT_AUTOMATION_RANDOM_START_HOUR', 8))),
    'random_end_hour' => max(0, min(23, (int) env('ACCOUNT_AUTOMATION_RANDOM_END_HOUR', 20))),
];
