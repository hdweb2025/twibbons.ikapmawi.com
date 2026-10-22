<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode permintaan tidak valid.']);
    exit();
}

try {
    $nama = trim($_POST['nama'] ?? '');
    $peran = trim($_POST['peran'] ?? 'Santri Aktif');
    $hp = trim($_POST['hp'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);

    // Tentukan keterangan tingkat & tahun berdasarkan peran
    $keterangan = '';
    $tahun = intval(date('Y'));

    if ($peran === 'Santri Aktif') {
        $keterangan = trim($_POST['keterangan_santri'] ?? 'Santri MA WI Kebarongan - Kelas 11');
        $tahun = intval(date('Y'));
    } elseif ($peran === 'Alumni') {
        $tahun = intval($_POST['tahun_alumni'] ?? date('Y'));
        $keterangan = 'Alumni Tahun ' . $tahun;
    } elseif ($peran === 'Asatidz / Guru') {
        $keterangan = trim($_POST['keterangan_asatidz'] ?? 'Dewan Asatidz MA WI Kebarongan');
        $tahun = intval(date('Y'));
    } elseif ($peran === 'Simpatisan / Umum') {
        $keterangan = trim($_POST['keterangan_simpatisan'] ?? 'Wali Santri / Simpatisan MWI');
        $tahun = intval(date('Y'));
    } else {
        $peran = 'Santri Aktif';
        $keterangan = 'Santri MA WI Kebarongan';
    }

    if (empty($nama)) {
        echo json_encode(['status' => 'error', 'message' => 'Nama lengkap wajib diisi!']);
        exit();
    }

    // Generate unique guest phone identifier jika nomor HP dikosongkan
    if (empty($hp)) {
        $clean_nama = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nama));
        $hp = 'guest_' . substr($clean_nama, 0, 8) . '_' . time() . '_' . rand(100, 999);
    }

    $user_id = 0;

    // 1. Cek apakah nomor HP sudah ada di tabel users
    $stmt_find = mysqli_prepare($conn, "SELECT id FROM users WHERE nomor_hp = ?");
    mysqli_stmt_bind_param($stmt_find, "s", $hp);
    mysqli_stmt_execute($stmt_find);
    $res_find = mysqli_stmt_get_result($stmt_find);

    if ($row_user = mysqli_fetch_assoc($res_find)) {
        // Update user yang sudah ada
        $user_id = intval($row_user['id']);
        mysqli_stmt_close($stmt_find);

        $stmt_up = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, peran = ?, keterangan_tingkat = ?, tahun_alumni = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_up, "sssii", $nama, $peran, $keterangan, $tahun, $user_id);
        mysqli_stmt_execute($stmt_up);
        mysqli_stmt_close($stmt_up);
    } else {
        // Insert user baru
        mysqli_stmt_close($stmt_find);

        $pass = '';
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, peran, keterangan_tingkat, tahun_alumni, nomor_hp, password, is_admin) VALUES (?, ?, ?, ?, ?, ?, 0)");
        mysqli_stmt_bind_param($stmt_insert, "sssiss", $nama, $peran, $keterangan, $tahun, $hp, $pass);
        mysqli_stmt_execute($stmt_insert);
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_insert);
    }

    // 2. Catat penggunaan event di tabel event_usage (gunakan ON DUPLICATE KEY UPDATE agar user bisa unduh berulang kali tanpa error duplicate)
    if ($user_id > 0 && $event_id > 0) {
        $stmt_usage = mysqli_prepare($conn, "INSERT INTO event_usage (user_id, event_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP");
        mysqli_stmt_bind_param($stmt_usage, "ii", $user_id, $event_id);
        mysqli_stmt_execute($stmt_usage);
        mysqli_stmt_close($stmt_usage);
    }

    // 3. Set Session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user'] = $nama;
    $_SESSION['peran'] = $peran;
    $_SESSION['keterangan'] = $keterangan;
    $_SESSION['tahun'] = $tahun;

    // 4. Set Persistent Cookie 6 Bulan (180 hari) agar bebas komplain isi ulang
    $cookie_lifetime = 180 * 86400; // 180 hari
    $cookie_data = json_encode([
        'user_id' => $user_id,
        'nama' => $nama,
        'peran' => $peran,
        'keterangan' => $keterangan,
        'tahun' => $tahun
    ]);
    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    setcookie('ikapmawi_participant', $cookie_data, time() + $cookie_lifetime, '/', '', $is_secure, true);

    echo json_encode([
        'status' => 'success',
        'user_id' => $user_id,
        'nama' => $nama,
        'peran' => $peran,
        'keterangan' => $keterangan,
        'tahun' => $tahun,
        'message' => 'Identitas berhasil disimpan untuk 6 bulan.'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}
