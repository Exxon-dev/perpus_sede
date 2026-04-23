<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$page_title = "Dashboard";

// Get statistics for dashboard
$stats = [];
if (isAdmin()) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM buku");
    $stats['total_buku'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
    $stats['total_user'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'dipinjam'");
    $stats['buku_dipinjam'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kategori");
    $stats['total_kategori'] = $stmt->fetch()['total'];
} elseif (isSiswa()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE user_id = ? AND status = 'dipinjam'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pinjaman_saya'] = $stmt->fetch()['total'];
}

// Check if this is a fresh login (not a refresh)
$show_welcome_alert = false;
if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $show_welcome_alert = true;
    // Remove the flag so it won't show on refresh
    unset($_SESSION['just_logged_in']);
}

include 'header.php';
?>

<!-- Include SweetAlert2 CSS and JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    .stat-number {
        font-size: 36px;
        font-weight: bold;
        color: #2c3e50;
    }
    .stat-label {
        color: #666;
        margin-top: 10px;
        font-size: 14px;
    }
    .welcome-card {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    .info-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    .info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .info-icon {
        font-size: 40px;
        margin-bottom: 10px;
    }
    hr {
        margin: 30px 0;
        border: none;
        border-top: 1px solid #e0e0e0;
    }
    .text-center {
        text-align: center;
    }
</style>

<div class="welcome-card">
    <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?>!</h2>
    <p>Selamat datang di Sistem Informasi Perpustakaan Digital</p>
</div>

<div class="stats-grid">
    <?php if(isAdmin()): ?>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_buku'] ?></div>
            <div class="stat-label">Total Buku</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_user'] ?></div>
            <div class="stat-label">Total User</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['buku_dipinjam'] ?></div>
            <div class="stat-label">Buku Dipinjam</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['total_kategori'] ?></div>
            <div class="stat-label">Kategori</div>
        </div>
    <?php elseif(isSiswa()): ?>
        <div class="stat-card">
            <div class="stat-number"><?= $stats['pinjaman_saya'] ?></div>
            <div class="stat-label">Buku yang Saya Pinjam</div>
        </div>
    <?php endif; ?>
</div>

<hr>

<div class="info-grid">
    <div class="info-card">
        <div class="info-icon">📜</div>
        <h3>Peraturan Peminjaman</h3>
        <p>Lama pinjam maksimal 7 hari</p>
        <p>Denda keterlambatan: Rp 1.000/hari</p>
    </div>
</div>

<?php include 'footer.php'; ?>

<!-- SweetAlert2 Script for Welcome Message -->
<?php if($show_welcome_alert): ?>
<script>
    // Escape nama lengkap untuk JavaScript
    const namaLengkap = <?= json_encode(htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User')) ?>;
    
    Swal.fire({
        position: "top-end",
        icon: "success",
        title: "Selamat datang!",
        text: `Halo, ${namaLengkap}`,
        showConfirmButton: false,
        timer: 1500,
        toast: true
    });
</script>
<?php endif; ?>