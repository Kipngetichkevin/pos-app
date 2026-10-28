<?php

return [

    /*
    | How this installation runs:
    |  - standalone: one server, no syncing (single shop, always online or always local).
    |  - branch:     the shop's own server. Sells without internet and syncs with the cloud when online.
    |  - cloud:      the central server. Receives data from every branch and M-Pesa callbacks from Safaricom.
    */
    'mode' => env('POS_MODE', 'standalone'),

    // Branch mode only: where the cloud server is, and the token the cloud issued for this branch
    'cloud_url' => rtrim((string) env('POS_CLOUD_URL', ''), '/'),
    'sync_token' => env('POS_SYNC_TOKEN'),
    'branch_id' => env('POS_BRANCH_ID'),

    // Nightly backups (pos:backup): folder, and where mysqldump is (e.g. C:/laragon/bin/mysql/mysql-8.4/bin/mysqldump.exe)
    'backup_path' => env('POS_BACKUP_PATH'),
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),

    // Rows sent or received per request
    'sync_batch' => (int) env('POS_SYNC_BATCH', 200),

];
