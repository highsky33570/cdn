<?php

return [
    // Dashboard reads include several parallel chart and status requests.
    'admin_reads_per_minute' => (int) env('ADMIN_API_READS_PER_MINUTE', 600),
    'admin_writes_per_minute' => (int) env('ADMIN_API_WRITES_PER_MINUTE', 120),
];
