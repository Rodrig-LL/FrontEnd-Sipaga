<?php

return [
    // Python untuk jembatan proses gaji (folder python/). Pasang dengan:
    //   python3 -m venv python/.venv && python/.venv/bin/pip install -r python/requirements.txt
    'python' => env('SIPAGA_PYTHON', base_path('python/.venv/bin/python')),

    // Batas waktu satu proses. Pembacaan PDF hasil scan (OCR) bisa memakan menit.
    'python_timeout' => (int) env('SIPAGA_PYTHON_TIMEOUT', 1800),
];
