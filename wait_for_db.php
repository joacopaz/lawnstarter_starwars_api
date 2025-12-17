<?php

while (true) {
    exec('docker compose exec pgsql pg_isready', $output, $exitCode);

    if ($exitCode === 0) {
        echo "DB is ready\n";
        exit(0);
    }

    echo "Waiting for DB setup...\n";
    sleep(1);
}
