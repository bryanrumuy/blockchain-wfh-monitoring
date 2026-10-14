<?php
/**
 * PHP Client - Catat Aktivitas Baru ke Blockchain
 * Menggunakan HTTP POST ke REST API
 *
 * Jalankan: php catat_aktivitas.php
 */

$apiUrl = "http://localhost:3000/api/activities";

// Data aktivitas yang akan dicatat ke blockchain
$activityData = [
    "id"           => "act" . time(),          // ID unik berdasarkan waktu
    "employeeName" => "Budi Santoso",
    "department"   => "Engineering",
    "activity"     => "Coding - Implementasi REST API",
    "duration"     => 120,
    "activityType" => "Productive",
    "notes"        => "Mengerjakan integrasi API dengan frontend",
    "date"         => date('Y-m-d'),
];

// Konversi data ke format JSON
$jsonData = json_encode($activityData);

// Inisialisasi cURL
$ch = curl_init($apiUrl);

// Set opsi cURL untuk HTTP POST
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);

// Eksekusi request
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "cURL error: " . curl_error($ch) . "\n";
} else {
    $data = json_decode($response, true);

    echo "=================================================\n";
    echo "  Catat Aktivitas ke Blockchain\n";
    echo "=================================================\n";

    if ($data['success'] ?? false) {
        echo "✅ BERHASIL!\n";
        echo "Pesan : " . $data['message'] . "\n\n";
        echo "--- Data yang Dicatat ---\n";
        $saved = $data['data'];
        echo "ID         : " . ($saved['ID']            ?? '-') . "\n";
        echo "Karyawan   : " . ($saved['EmployeeName']  ?? '-') . "\n";
        echo "Departemen : " . ($saved['Department']    ?? '-') . "\n";
        echo "Aktivitas  : " . ($saved['Activity']      ?? '-') . "\n";
        echo "Durasi     : " . ($saved['Duration']      ?? 0)   . " menit\n";
        echo "Timestamp  : " . ($saved['Timestamp']     ?? '-') . "\n";
    } else {
        echo "❌ GAGAL!\n";
        echo "Error: " . ($data['error'] ?? 'Unknown error') . "\n";
    }
}

curl_close($ch);
?>
