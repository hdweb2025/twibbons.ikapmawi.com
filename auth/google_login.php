<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
    exit();
}

try {
    $credential = trim($_POST['credential'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    $peran = trim($_POST['peran'] ?? '');
    $tahun_alumni = intval($_POST['tahun_alumni'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($credential)) {
        echo json_encode(['status' => 'error', 'message' => 'Token Google tidak ditemukan.']);
        exit();
    }

    // 1. Verifikasi Google ID Token (JWT)
    $payload = null;

    // A. Coba verifikasi langsung ke Google TokenInfo API (Sangat aman & valid)
    $verify_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential);
    $ch = curl_init($verify_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $verify_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && !empty($verify_response)) {
        $payload = json_decode($verify_response, true);
    } else {
        // B. Fallback: Parse JWT Payload langsung jika timeout curl server
        $jwt_parts = explode('.', $credential);
        if (count($jwt_parts) === 3) {
            $raw_payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $jwt_parts[1]));
            $payload = json_decode($raw_payload, true);
        }
    }

    if (!$payload || empty($payload['sub'])) {
        echo json_encode(['status' => 'error', 'message' => 'Token Google tidak valid atau kedaluwarsa.']);
        exit();
    }

    $google_id = trim($payload['sub']);
    $email = trim($payload['email'] ?? '');
    $nama = trim($payload['name'] ?? 'Sahabat MWI');
    $avatar = trim($payload['picture'] ?? '');

    // 2. Cek apakah user sudah ada di database (berdasarkan google_id atau email)
    $stmt_find = mysqli_prepare($conn, "SELECT * FROM users WHERE google_id = ? OR (email = ? AND email != '') LIMIT 1");
    mysqli_stmt_bind_param($stmt_find, "ss", $google_id, $email);
    mysqli_stmt_execute($stmt_find);
    $res_find = mysqli_stmt_get_result($stmt_find);
    $existing_user = mysqli_fetch_assoc($res_find);
    mysqli_stmt_close($stmt_find);

    $user_id = 0;
    $final_peran = '';
    $final_tahun = intval(date('Y'));
    $final_keterangan = '';

    // Siapkan keterangan peran jika peran diisi
    if (!empty($peran)) {
        if ($peran === 'Alumni') {
            $final_tahun = ($tahun_alumni > 1940) ? $tahun_alumni : intval(date('Y'));
            $final_keterangan = 'Alumni Tahun ' . $final_tahun;
        } elseif ($peran === 'Santri Aktif') {
            $final_keterangan = !empty($keterangan) ? $keterangan : 'Santri MA WI Kebarongan - Kelas 11';
        } elseif ($peran === 'Asatidz / Guru') {
            $final_keterangan = !empty($keterangan) ? $keterangan : 'Dewan Asatidz MA WI Kebarongan';
        } elseif ($peran === 'Simpatisan / Umum') {
            $final_keterangan = !empty($keterangan) ? $keterangan : 'Wali Santri / Simpatisan MWI';
        }
        $final_peran = $peran;
    }

    if ($existing_user) {
        $user_id = intval($existing_user['id']);

        // Jika peran baru dikirimkan, perbarui data user
        if (!empty($final_peran)) {
            $stmt_up = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, peran = ?, keterangan_tingkat = ?, tahun_alumni = ?, google_id = ?, avatar = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up, "sssissi", $nama, $final_peran, $final_keterangan, $final_tahun, $google_id, $avatar, $user_id);
            mysqli_stmt_execute($stmt_up);
            mysqli_stmt_close($stmt_up);
        } else {
            // Gunakan peran yang sudah pernah disimpan sebelumnya
            $final_peran = $existing_user['peran'] ?? '';
            $final_tahun = intval($existing_user['tahun_alumni'] ?? date('Y'));
            $final_keterangan = $existing_user['keterangan_tingkat'] ?? '';

            // Update google_id & avatar jika belum tersimpan
            $stmt_up = mysqli_prepare($conn, "UPDATE users SET google_id = ?, avatar = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up, "ssi", $google_id, $avatar, $user_id);
            mysqli_stmt_execute($stmt_up);
            mysqli_stmt_close($stmt_up);
        }
    } else {
        // User baru pertama kali
        $placeholder_hp = 'google_' . substr($google_id, 0, 12);
        $pass = '';

        if (!empty($final_peran)) {
            $stmt_in = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, email, google_id, avatar, peran, keterangan_tingkat, tahun_alumni, nomor_hp, password, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt_in, "ssssssiss", $nama, $email, $google_id, $avatar, $final_peran, $final_keterangan, $final_tahun, $placeholder_hp, $pass);
            mysqli_stmt_execute($stmt_in);
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_in);
        } else {
            // Simpan profil dasar Google terlebih dahulu
            $stmt_in = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, email, google_id, avatar, nomor_hp, password, is_admin) VALUES (?, ?, ?, ?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt_in, "ssssss", $nama, $email, $google_id, $avatar, $placeholder_hp, $pass);
            mysqli_stmt_execute($stmt_in);
            $user_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_in);
        }
    }

    // 3. Jika pengguna belum memiliki peran (belum tentukan alumni tahun berapa/santri), minta lengkapi 1 langkah
    if (empty($final_peran)) {
        echo json_encode([
            'status' => 'need_role',
            'user_id' => $user_id,
            'nama' => $nama,
            'email' => $email,
            'avatar' => $avatar,
            'message' => 'Langkah terakhir: Silakan pilih status dan tahun alumni Anda di MWI.'
        ]);
        exit();
    }

    // 4. Catat keikutsertaan event di tabel event_usage
    if ($user_id > 0 && $event_id > 0) {
        $stmt_usage = mysqli_prepare($conn, "INSERT INTO event_usage (user_id, event_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($stmt_usage, "ii", $user_id, $event_id);
        mysqli_stmt_execute($stmt_usage);
        mysqli_stmt_close($stmt_usage);
    }

    // 5. Set PHP Session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user'] = $nama;
    $_SESSION['peran'] = $final_peran;
    $_SESSION['keterangan'] = $final_keterangan;
    $_SESSION['tahun'] = $final_tahun;
    $_SESSION['avatar'] = $avatar;

    // 6. Set Persistent Cookie 6 Bulan (180 hari)
    $cookie_lifetime = 180 * 86400;
    $cookie_data = json_encode([
        'user_id' => $user_id,
        'nama' => $nama,
        'peran' => $final_peran,
        'keterangan' => $final_keterangan,
        'tahun' => $final_tahun,
        'avatar' => $avatar,
        'auth_provider' => 'google'
    ]);
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    setcookie('ikapmawi_participant', $cookie_data, time() + $cookie_lifetime, '/', '', $is_secure, true);

    echo json_encode([
        'status' => 'success',
        'user_id' => $user_id,
        'nama' => $nama,
        'peran' => $final_peran,
        'keterangan' => $final_keterangan,
        'tahun' => $final_tahun,
        'avatar' => $avatar,
        'message' => 'Alhamdulillah, login Google berhasil.'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}
?>
