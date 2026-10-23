<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// 1. Verifikasi Kunci Rahasia Sinkronisasi (Shared Secret API Key)
$expected_key = get_setting($conn, 'sync_secret_key', 'ikapmawi_etl_secret_9f83a71b2d4c6e80');

$client_key = '';
if (isset($_SERVER['HTTP_X_IKAPMAWI_SYNC_KEY'])) {
    $client_key = trim($_SERVER['HTTP_X_IKAPMAWI_SYNC_KEY']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $client_key = trim($matches[1]);
    }
} elseif (isset($_GET['key'])) {
    $client_key = trim($_GET['key']);
}

if (empty($client_key) || !hash_equals($expected_key, $client_key)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak: Kunci otentikasi konektor tidak valid.']);
    exit();
}

try {
    $since_id = isset($_GET['since_id']) ? intval($_GET['since_id']) : 0;
    $limit = isset($_GET['limit']) ? min(2000, max(1, intval($_GET['limit']))) : 500;

    // Ambil data peserta beserta riwayat event yang mereka ikuti
    $sql = "
        SELECT 
            u.id AS user_id,
            u.nama_lengkap,
            u.email,
            u.google_id,
            u.avatar,
            u.peran,
            u.keterangan_tingkat,
            u.tahun_alumni,
            u.nomor_hp,
            u.created_at AS user_created_at,
            GROUP_CONCAT(DISTINCT e.name ORDER BY e.id SEPARATOR ' || ') AS events_participated,
            GROUP_CONCAT(DISTINCT e.slug ORDER BY e.id SEPARATOR ' || ') AS event_slugs,
            COUNT(DISTINCT eu.event_id) AS total_events,
            MAX(eu.created_at) AS last_active_at
        FROM users u
        LEFT JOIN event_usage eu ON u.id = eu.user_id
        LEFT JOIN events e ON eu.event_id = e.id
        WHERE u.is_admin = 0 AND u.id > ?
        GROUP BY u.id
        ORDER BY u.id ASC
        LIMIT ?
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $since_id, $limit);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $records = [];
    $max_id = $since_id;

    while ($row = mysqli_fetch_assoc($res)) {
        $uid = intval($row['user_id']);
        if ($uid > $max_id) $max_id = $uid;

        // Sembunyikan guest phone string yang acak jika bukan nomor asli
        $hp = $row['nomor_hp'];
        $is_real_phone = false;
        if (!empty($hp) && strpos($hp, 'guest_') !== 0 && strpos($hp, 'google_') !== 0) {
            $is_real_phone = true;
        }

        $records[] = [
            'twibbon_user_id' => $uid,
            'nama_lengkap' => $row['nama_lengkap'],
            'email' => $row['email'],
            'google_id' => $row['google_id'],
            'avatar' => $row['avatar'],
            'peran' => $row['peran'] ?? 'Alumni',
            'keterangan_tingkat' => $row['keterangan_tingkat'],
            'tahun_alumni' => intval($row['tahun_alumni']),
            'no_whatsapp' => $is_real_phone ? $hp : null,
            'has_google_auth' => !empty($row['google_id']),
            'events_participated' => $row['events_participated'] ? explode(' || ', $row['events_participated']) : [],
            'event_slugs' => $row['event_slugs'] ? explode(' || ', $row['event_slugs']) : [],
            'total_events' => intval($row['total_events']),
            'user_created_at' => $row['user_created_at'],
            'last_active_at' => $row['last_active_at'] ?? $row['user_created_at'],
        ];
    }
    mysqli_stmt_close($stmt);

    // Hitung total peserta yang tersedia di database
    $count_res = mysqli_query($conn, "SELECT COUNT(1) AS total FROM users WHERE is_admin = 0");
    $total_in_source = ($row_c = mysqli_fetch_assoc($count_res)) ? intval($row_c['total']) : 0;

    echo json_encode([
        'status' => 'success',
        'source' => 'twibbons.ikapmawi.com',
        'since_id' => $since_id,
        'max_id' => $max_id,
        'fetched_count' => count($records),
        'total_in_source' => $total_in_source,
        'data' => $records
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Kesalahan query data: ' . $e->getMessage()
    ]);
}
?>
