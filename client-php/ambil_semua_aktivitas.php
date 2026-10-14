<?php
/**
 * File ini: Mengambil SEMUA aktivitas dari blockchain
 * Akses: php ambil_semua_aktivitas.php  (via command line)
 *        atau http://localhost/client-php/ambil_semua_aktivitas.php (via browser)
 */

// URL endpoint REST API yang sudah dibuat di server.js
$apiUrl = "http://localhost:3000/api/activities";

// Inisialisasi sesi cURL
$ch = curl_init($apiUrl);

// Set opsi cURL untuk HTTP GET
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Eksekusi request dan dapatkan respons
$response = curl_exec($ch);

// Cek error cURL
if (curl_errno($ch)) {
    echo "cURL error: " . curl_error($ch);
} else {
    // Parse JSON response
    $data = json_decode($response, true);

    echo "=================================================\n";
    echo "  WFH Activity Monitoring - Semua Aktivitas\n";
    echo "=================================================\n";
    echo "Total Aktivitas: " . ($data['total'] ?? 0) . "\n\n";

    if (!empty($data['data'])) {
        foreach ($data['data'] as $i => $activity) {
            echo "--- Aktivitas #" . ($i + 1) . " ---\n";
            echo "ID           : " . ($activity['ID'] ?? '-') . "\n";
            echo "Karyawan     : " . ($activity['EmployeeName'] ?? '-') . "\n";
            echo "Departemen   : " . ($activity['Department'] ?? '-') . "\n";
            echo "Aktivitas    : " . ($activity['Activity'] ?? '-') . "\n";
            echo "Durasi       : " . ($activity['Duration'] ?? 0) . " menit\n";
            echo "Tipe         : " . ($activity['ActivityType'] ?? '-') . "\n";
            echo "Catatan      : " . ($activity['Notes'] ?? '-') . "\n";
            echo "Tanggal      : " . ($activity['Date'] ?? '-') . "\n";
            echo "Timestamp    : " . ($activity['Timestamp'] ?? '-') . "\n\n";
        }
    } else {
        echo "Belum ada data aktivitas dalam blockchain.\n";
    }
}

// Tutup sesi cURL
curl_close($ch);
?>
