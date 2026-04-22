<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../index.php');
}

// Cek apakah request AJAX atau tidak
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['peminjaman_id'])) {
    $peminjaman_id = $_POST['peminjaman_id'];
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    $response = [];
    
    try {
        $pdo->beginTransaction();
        
        // Ambil data peminjaman berdasarkan role
        if ($role == 'siswa') {
            // Siswa hanya bisa mengembalikan bukunya sendiri
            $stmt = $pdo->prepare("
                SELECT p.*, b.judul 
                FROM peminjaman p 
                JOIN buku b ON p.buku_id = b.id 
                WHERE p.id = ? AND p.user_id = ? AND p.status = 'dipinjam'
            ");
            $stmt->execute([$peminjaman_id, $user_id]);
        } else {
            // Admin/guru bisa mengembalikan semua buku
            $stmt = $pdo->prepare("
                SELECT p.*, b.judul 
                FROM peminjaman p 
                JOIN buku b ON p.buku_id = b.id 
                WHERE p.id = ? AND p.status = 'dipinjam'
            ");
            $stmt->execute([$peminjaman_id]);
        }
        
        $peminjaman = $stmt->fetch();
        
        if (!$peminjaman) {
            throw new Exception("Data peminjaman tidak ditemukan atau sudah dikembalikan!");
        }
        
        // Update status peminjaman menjadi dikembalikan
        $stmt = $pdo->prepare("
            UPDATE peminjaman 
            SET status = 'dikembalikan', 
                tanggal_kembali = NOW() 
            WHERE id = ? AND status = 'dipinjam'
        ");
        $stmt->execute([$peminjaman_id]);
        
        if ($stmt->rowCount() == 0) {
            throw new Exception("Gagal mengembalikan buku. Mungkin sudah dikembalikan sebelumnya!");
        }
        
        // Tambah stok buku
        $stmt = $pdo->prepare("UPDATE buku SET stok = stok + 1 WHERE id = ?");
        $stmt->execute([$peminjaman['buku_id']]);
        
        $pdo->commit();
        
        if ($is_ajax) {
            // Response untuk AJAX request
            $response = [
                'success' => true,
                'message' => "Buku '{$peminjaman['judul']}' berhasil dikembalikan!",
                'judul' => $peminjaman['judul']
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            // Response untuk form submit biasa
            $_SESSION['success'] = "Buku '{$peminjaman['judul']}' berhasil dikembalikan!";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        if ($is_ajax) {
            // Response untuk AJAX request
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

// Redirect kembali ke halaman sebelumnya (hanya untuk non-AJAX request)
if (!$is_ajax) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } else {
        redirect('../dashboard.php');
    }
}
?>