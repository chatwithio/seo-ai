<?php

return [
    'total_steps' => max(1, (int) env('ACCOUNT_ONBOARDING_STEPS', 6)),
    'initial_import_days' => max(1, (int) env('ACCOUNT_ONBOARDING_IMPORT_DAYS', 90)),
];
