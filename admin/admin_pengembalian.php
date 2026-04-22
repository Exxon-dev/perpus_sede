<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = "Kelola Pengembalian Buku";

// Handle single return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_single'])) {
    $peminjaman_id = $_POST['peminjaman_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Ambil data peminjaman
        $stmt = $pdo->prepare("SELECT p.*, b.judul FROM peminjaman p JOIN buku b ON p.buku_id = b.id WHERE p.id = ? AND p.status = 'dipinjam'");
        $stmt->execute([$peminjaman_id]);
        $peminjaman = $stmt->fetch();
        
        if (!$peminjaman) {
            throw new Exception("Data peminjaman tidak ditemukan atau sudah dikembalikan!");
        }
        
        // Update status peminjaman
        $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'dikembalikan', tanggal_kembali = NOW() WHERE id = ?");
        $stmt->execute([$peminjaman_id]);
        
        // Tambah stok buku
        $stmt = $pdo->prepare("UPDATE buku SET stok = stok + 1 WHERE id = ?");
        $stmt->execute([$peminjaman['buku_id']]);
        
        $pdo->commit();
        // Simpan dalam bentuk JSON untuk menghindari masalah karakter
        $_SESSION['swal_success'] = json_encode([
            'message' => "Buku '{$peminjaman['judul']}' berhasil dikembalikan!",
            'judul' => $peminjaman['judul']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_error'] = $e->getMessage();
    }
    redirect('admin_pengembalian.php');
}

// Handle mass return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mass_return'])) {
    $peminjaman_ids = $_POST['peminjaman_ids'] ?? [];
    $success_count = 0;
    $error_count = 0;
    $returned_books = [];
    
    if (empty($peminjaman_ids)) {
        $_SESSION['swal_error'] = "Tidak ada buku yang dipilih untuk dikembalikan!";
        redirect('admin_pengembalian.php');
    }
    
    foreach ($peminjaman_ids as $id) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT p.*, b.judul FROM peminjaman p JOIN buku b ON p.buku_id = b.id WHERE p.id = ? AND p.status = 'dipinjam'");
            $stmt->execute([$id]);
            $peminjaman = $stmt->fetch();
            
            if ($peminjaman) {
                $stmt = $pdo->prepare("UPDATE peminjaman SET status = 'dikembalikan', tanggal_kembali = NOW() WHERE id = ?");
                $stmt->execute([$id]);
                
                $stmt = $pdo->prepare("UPDATE buku SET stok = stok + 1 WHERE id = ?");
                $stmt->execute([$peminjaman['buku_id']]);
                
                $pdo->commit();
                $success_count++;
                $returned_books[] = $peminjaman['judul'];
            } else {
                $pdo->rollBack();
                $error_count++;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        $_SESSION['swal_success'] = json_encode([
            'message' => "$success_count buku berhasil dikembalikan.",
            'count' => $success_count
        ]);
    }
    if ($error_count > 0) {
        $_SESSION['swal_error'] = "$error_count buku gagal dikembalikan.";
    }
    redirect('admin_pengembalian.php');
}

// Handle filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'dipinjam';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data peminjaman - PERBAIKAN: Gunakan positional parameters semua
$sql = "SELECT p.*, 
        b.judul, 
        b.penulis,
        u.nama_lengkap as peminjam_nama,
        u.username as peminjam_username,
        u.role as peminjam_role
        FROM peminjaman p
        JOIN buku b ON p.buku_id = b.id
        JOIN users u ON p.user_id = u.id
        WHERE 1=1";

$params = [];

if ($filter_status == 'dipinjam') {
    $sql .= " AND p.status = 'dipinjam'";
} elseif ($filter_status == 'dikembalikan') {
    $sql .= " AND p.status = 'dikembalikan'";
}

if (!empty($search)) {
    $sql .= " AND (b.judul LIKE ? OR u.nama_lengkap LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Urutkan berdasarkan judul buku (abjad A-Z)
$sql .= " ORDER BY b.judul ASC";

// Buat query count secara terpisah - PERBAIKAN: Gunakan positional parameters
$countSql = "SELECT COUNT(*) as total 
             FROM peminjaman p
             JOIN buku b ON p.buku_id = b.id
             JOIN users u ON p.user_id = u.id
             WHERE 1=1";

// Tambahkan filter yang sama ke query count
if ($filter_status == 'dipinjam') {
    $countSql .= " AND p.status = 'dipinjam'";
} elseif ($filter_status == 'dikembalikan') {
    $countSql .= " AND p.status = 'dikembalikan'";
}

if (!empty($search)) {
    $countSql .= " AND (b.judul LIKE ? OR u.nama_lengkap LIKE ? OR u.username LIKE ?)";
}

// Eksekusi query count dengan positional parameters
$stmt = $pdo->prepare($countSql);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindValue(1, $search_param);
    $stmt->bindValue(2, $search_param);
    $stmt->bindValue(3, $search_param);
}
$stmt->execute();
$total_data_result = $stmt->fetch();
$total_data = $total_data_result['total'] ?? 0;
$total_pages = $total_data > 0 ? ceil($total_data / $limit) : 1;

// PERBAIKAN UTAMA: Tambahkan limit dan offset dengan positional parameters
$sql .= " LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
// Bind parameter pencarian (positional)
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
// Bind limit dan offset (positional)
$param_count = count($params);
$stmt->bindValue($param_count + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_count + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$peminjaman = $stmt->fetchAll();

// Statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'dipinjam'");
$total_dipinjam_result = $stmt->fetch();
$total_dipinjam = $total_dipinjam_result['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'dikembalikan' AND DATE(tanggal_kembali) = CURDATE()");
$total_kembali_hari_ini_result = $stmt->fetch();
$total_kembali_hari_ini = $total_kembali_hari_ini_result['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM peminjaman");
$total_peminjaman_result = $stmt->fetch();
$total_peminjaman = $total_peminjaman_result['total'] ?? 0;

// Ambil dan decode session messages
$swal_success_raw = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : '';
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : '';

// Coba decode jika berupa JSON
$swal_success = '';
$swal_success_judul = '';
if ($swal_success_raw) {
    $decoded = json_decode($swal_success_raw, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
        $swal_success = $decoded['message'];
        $swal_success_judul = $decoded['judul'] ?? '';
    } else {
        $swal_success = $swal_success_raw;
    }
}

unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

include '../header.php';
?>

<!-- Include SweetAlert2 CSS and JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .btn {
        display: inline-block;
        padding: 8px 16px;
        background: #333;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    .btn:hover {
        opacity: 0.85;
        transform: translateY(-1px);
    }
    .btn-success {
        background: #28a745;
    }
    .btn-success:hover {
        background: #218838;
    }
    .btn-danger {
        background: #dc3545;
    }
    .btn-danger:hover {
        background: #c82333;
    }
    .btn-cari {
        background: #333;
    }
    .btn-cari:hover {
        background: #555;
    }
    .btn-sm {
        padding: 4px 12px;
        font-size: 12px;
    }
    .btn-batal {
        background: #5b9aa0;
        color: white;
    }
    .btn-batal:hover {
        background: #4a7a7f;
    }
    .btn-reset {
        background: #6c757d;
    }
    .btn-reset:hover {
        background: #5a6268;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: transform 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .stat-number {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
    }
    .stat-label {
        color: #666;
        margin-top: 8px;
        font-size: 13px;
    }
    .filter-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    .filter-group label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-size: 13px;
        font-weight: 500;
    }
    .filter-group select, .filter-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }
    .filter-group select:focus, .filter-group input:focus {
        outline: none;
        border-color: #2c3e50;
    }
    .action-bar {
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .checkbox-all {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    table {
        width: 100%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    th, td {
        padding: 14px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    th {
        background: #f8f8f8;
        font-weight: 600;
        color: #555;
    }
    tr:hover {
        background: #f9f9f9;
    }
    .badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    .badge-dipinjam {
        background: #ffeaa7;
        color: #d35400;
    }
    .badge-dikembalikan {
        background: #d4edda;
        color: #155724;
    }
    .badge-siswa {
        background: #98aef7;
        color: #0c5460;
    }
    .badge-admin {
        background: #ffd3b6;
        color: #d35400;
    }
    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
        color: #666;
    }
    .checkbox-col {
        width: 30px;
        text-align: center;
    }
    
    /* Pagination Styles */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 30px;
        flex-wrap: wrap;
    }
    .pagination a, .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        background: white;
        color: #333;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s;
        border: 1px solid #ddd;
    }
    .pagination a:hover {
        background: #2c3e50;
        color: white;
        border-color: #2c3e50;
    }
    .pagination .active {
        background: #2c3e50;
        color: white;
        border-color: #2c3e50;
    }
    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .info-pagination {
        text-align: center;
        margin-top: 15px;
        color: #666;
        font-size: 13px;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-container {
            flex-direction: column;
        }
        .filter-group {
            width: 100%;
        }
        .action-bar {
            flex-direction: column;
            align-items: stretch;
        }
        table, thead, tbody, th, td, tr {
            display: block;
        }
        thead {
            display: none;
        }
        tr {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        td:before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 10px;
            min-width: 120px;
        }
        td:last-child {
            border-bottom: none;
        }
        .checkbox-col {
            width: auto;
        }
        .pagination a, .pagination span {
            min-width: 32px;
            height: 32px;
            font-size: 12px;
        }
    }
</style>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $total_dipinjam ?></div>
        <div class="stat-label">Buku Sedang Dipinjam</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_kembali_hari_ini ?></div>
        <div class="stat-label">Dikembalikan Hari Ini</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_peminjaman ?></div>
        <div class="stat-label">Total Transaksi</div>
    </div>
</div>

<!-- Filter -->
<div class="filter-container">
    <div class="filter-group">
        <label>Filter Status</label>
        <select id="statusFilter" onchange="window.location.href='?status='+this.value+'&search=<?= urlencode($search) ?>&page=1'">
            <option value="dipinjam" <?= $filter_status == 'dipinjam' ? 'selected' : '' ?>>Sedang Dipinjam</option>
            <option value="dikembalikan" <?= $filter_status == 'dikembalikan' ? 'selected' : '' ?>>Sudah Dikembalikan</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Cari</label>
        <form method="GET" id="searchForm" style="display: flex; gap: 10px;">
            <input type="hidden" name="status" value="<?= $filter_status ?>">
            <input type="hidden" name="page" value="1">
            <input type="text" name="search" placeholder="Cari judul atau peminjam..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
            <button type="submit" class="btn btn-cari btn-sm">Cari</button>
            <?php if($search): ?>
                <a href="?status=<?= $filter_status ?>&page=1" class="btn btn-reset btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($filter_status == 'dipinjam' && !empty($peminjaman)): ?>
<!-- Action Bar for Mass Return -->
<form method="POST" id="massReturnForm" action="">
    <input type="hidden" name="mass_return" value="1">
    <div class="action-bar">
        <div class="checkbox-all">
            <input type="checkbox" id="selectAllCheckbox">
            <label for="selectAllCheckbox">Pilih Semua</label>
        </div>
        <div>
            <button type="button" id="massReturnBtn" class="btn btn-success">
                Kembalikan Terpilih
            </button>
            <button type="button" class="btn btn-batal" onclick="deselectAll()">Batal Pilih</button>
        </div>
    </div>
<?php endif; ?>

<!-- Table -->
<?php if (empty($peminjaman)): ?>
    <div class="empty-state">
        <?php if ($filter_status == 'dipinjam'): ?>
            <p>Tidak ada buku yang sedang dipinjam.</p>
            <p>Semua buku telah dikembalikan.</p>
        <?php else: ?>
            <p>Belum ada riwayat pengembalian buku.</p>
            <p>Silakan lihat data peminjaman yang sedang berlangsung.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table id="peminjamanTable">
            <thead>
                <tr>
                    <?php if ($filter_status == 'dipinjam'): ?>
                        <th class="checkbox-col" style="text-align: center;">Pilih</th>
                    <?php endif; ?>
                    <th style="text-align: center;">No</th>
                    <th style="text-align: center;">Peminjam</th>
                    <th style="text-align: center;">Role</th>
                    <th style="text-align: center;">Judul Buku</th>
                    <th style="text-align: center;">Penulis</th>
                    <th style="text-align: center;">Tanggal Pinjam</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $start_number = $offset + 1;
                foreach($peminjaman as $index => $p): 
                ?>
                <tr id="row-<?= $p['id'] ?>">
                    <?php if ($filter_status == 'dipinjam'): ?>
                        <td class="checkbox-col" data-label="Pilih" style="text-align: center;">
                            <input type="checkbox" name="peminjaman_ids[]" value="<?= $p['id'] ?>" class="borrow-checkbox" data-judul="<?= htmlspecialchars($p['judul'], ENT_QUOTES, 'UTF-8') ?>">
                        </td>
                    <?php endif; ?>
                    <td data-label="No" style="text-align: center;"><?= $start_number + $index ?></td>
                    <td data-label="Peminjam">
                        <?= htmlspecialchars($p['peminjam_nama']) ?>
                        <br>
                        <small style="color:#888;"><?= htmlspecialchars($p['peminjam_username']) ?></small>
                    </td>
                    <td data-label="Role">
                        <span class="badge badge-<?= $p['peminjam_role'] ?>">
                            <?= $p['peminjam_role'] == 'siswa' ? 'Siswa' : ($p['peminjam_role'] == 'admin' ? 'Admin' : '') ?>
                        </span>
                    </td>
                    <td data-label="Judul Buku"><?= htmlspecialchars($p['judul']) ?></td>
                    <td data-label="Penulis"><?= htmlspecialchars($p['penulis']) ?></td>
                    <td data-label="Tanggal Pinjam" style="text-align: center;"><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                    <td data-label="Status">
                        <?php if($p['status'] == 'dipinjam'): ?>
                            <span class="badge badge-dipinjam">Dipinjam</span>
                        <?php else: ?>
                            <span class="badge badge-dikembalikan">Dikembalikan</span>
                            <br>
                            <small><?= date('d/m/Y', strtotime($p['tanggal_kembali'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td data-label="Aksi" style="text-align: center;">
                        <?php if($p['status'] == 'dipinjam'): ?>
                            <button class="btn btn-success btn-sm return-single" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-judul="<?= htmlspecialchars($p['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                Kembalikan
                            </button>
                        <?php else: ?>
                            <span class="btn btn-sm" style="background:#6c757d; cursor:default;">Sudah Kembali</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="info-pagination">
        Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $total_data) ?> dari <?= $total_data ?> data
    </div>
    <div class="pagination">
        <!-- Tombol First -->
        <?php if ($page > 1): ?>
            <a href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&page=1">&laquo; First</a>
        <?php else: ?>
            <span class="disabled">&laquo; First</span>
        <?php endif; ?>
        
        <!-- Tombol Previous -->
        <?php if ($page > 1): ?>
            <a href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">&lsaquo; Prev</a>
        <?php else: ?>
            <span class="disabled">&lsaquo; Prev</span>
        <?php endif; ?>
        
        <!-- Nomor Halaman -->
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<span>...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
            <span>...</span>
        <?php endif; ?>
        
        <!-- Tombol Next -->
        <?php if ($page < $total_pages): ?>
            <a href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next &rsaquo;</a>
        <?php else: ?>
            <span class="disabled">Next &rsaquo;</span>
        <?php endif; ?>
        
        <!-- Tombol Last -->
        <?php if ($page < $total_pages): ?>
            <a href="?status=<?= $filter_status ?>&search=<?= urlencode($search) ?>&page=<?= $total_pages ?>">Last &raquo;</a>
        <?php else: ?>
            <span class="disabled">Last &raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($filter_status == 'dipinjam' && !empty($peminjaman)): ?>
    </form>
    <?php endif; ?>
<?php endif; ?>

<!-- Form untuk single return (hidden) -->
<form id="singleReturnForm" method="POST" style="display: none;">
    <input type="hidden" name="peminjaman_id" id="single_return_id">
    <input type="hidden" name="return_single" value="1">
</form>

<!-- SweetAlert2 Scripts -->
<?php if ($swal_success): ?>
<script>
    // Escape string untuk JavaScript
    const successMessage = <?= json_encode($swal_success) ?>;
    Swal.fire({
        position: "top",
        icon: "success",
        title: "Berhasil!",
        text: successMessage,
        showConfirmButton: false,
        timer: 1500
    });
</script>
<?php endif; ?>

<?php if ($swal_error): ?>
<script>
    const errorMessage = <?= json_encode($swal_error) ?>;
    Swal.fire({
        position: "top",
        icon: "error",
        title: "Gagal!",
        text: errorMessage,
        showConfirmButton: true,
        confirmButtonColor: "#dc3545"
    });
</script>
<?php endif; ?>

<script>
    // Select All functionality - PERBAIKAN UTAMA
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    let checkboxes = document.querySelectorAll('.borrow-checkbox');
    
    // Fungsi untuk refresh daftar checkbox (karena mungkin ada perubahan halaman)
    function refreshCheckboxes() {
        checkboxes = document.querySelectorAll('.borrow-checkbox');
    }
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            refreshCheckboxes();
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
    function deselectAll() {
        refreshCheckboxes();
        checkboxes.forEach(cb => cb.checked = false);
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
    }
    
    // Update select all checkbox when individual checkboxes change
    function updateSelectAllCheckbox() {
        if (!selectAllCheckbox) return;
        refreshCheckboxes();
        const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
        const someChecked = Array.from(checkboxes).some(cb => cb.checked);
        selectAllCheckbox.checked = allChecked;
        selectAllCheckbox.indeterminate = !allChecked && someChecked;
    }
    
    if (checkboxes.length > 0 && selectAllCheckbox) {
        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateSelectAllCheckbox);
        });
    }
    
    // Single return confirmation with SweetAlert2
    document.querySelectorAll('.return-single').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            // Gunakan dataset.judul yang sudah di-escape
            const judul = this.dataset.judul;
            
            Swal.fire({
                title: 'Konfirmasi Pengembalian',
                html: `Apakah Anda yakin ingin mengembalikan buku "<strong>${escapeHtml(judul)}</strong>"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Ya, Kembalikan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tampilkan loading
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Sedang mengembalikan buku',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit form
                    document.getElementById('single_return_id').value = id;
                    document.getElementById('singleReturnForm').submit();
                }
            });
        });
    });
    
    // Mass return confirmation with SweetAlert2 - PERBAIKAN UTAMA
    const massReturnBtn = document.getElementById('massReturnBtn');
    if (massReturnBtn) {
        massReturnBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            refreshCheckboxes();
            const checked = Array.from(document.querySelectorAll('.borrow-checkbox:checked'));
            const checkedCount = checked.length;
            
            if (checkedCount === 0) {
                Swal.fire({
                    position: "top",
                    icon: "warning",
                    title: "Tidak ada buku dipilih!",
                    text: "Silakan pilih minimal satu buku yang akan dikembalikan.",
                    showConfirmButton: true,
                    confirmButtonColor: "#ffc107"
                });
                return false;
            }
            
            // Dapatkan daftar judul buku yang dipilih dengan aman
            const selectedRows = [];
            checked.forEach(cb => {
                const judul = cb.getAttribute('data-judul') || 'Buku';
                selectedRows.push(escapeHtml(judul));
            });
            
            Swal.fire({
                title: 'Konfirmasi Pengembalian Massal',
                html: `Apakah Anda yakin ingin mengembalikan <strong>${checkedCount}</strong> buku berikut?<br><br>
                       <div style="max-height:200px; overflow-y:auto; text-align:left; padding:10px; background:#f8f9fa; border-radius:8px;">
                       ${selectedRows.map(judul => `📖 ${judul}`).join('<br>')}
                       </div>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: `Ya, Kembalikan ${checkedCount} Buku!`,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tampilkan loading
                    Swal.fire({
                        title: 'Memproses...',
                        text: `Sedang mengembalikan ${checkedCount} buku`,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit form
                    const massForm = document.getElementById('massReturnForm');
                    if (massForm) {
                        massForm.submit();
                    }
                }
            });
        });
    }
    
    // Fungsi untuk escape HTML characters
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Untuk checkbox yang mungkin ditambahkan secara dinamis (pagination tidak mengubah halaman, tapi tetap aman)
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            refreshCheckboxes();
            if (selectAllCheckbox) {
                updateSelectAllCheckbox();
            }
            // Re-attach event listeners untuk return-single jika perlu
            document.querySelectorAll('.return-single').forEach(button => {
                // Hapus listener lama untuk menghindari duplikasi
                if (!button.hasAttribute('data-listener-attached')) {
                    button.setAttribute('data-listener-attached', 'true');
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const id = this.dataset.id;
                        const judul = this.dataset.judul;
                        
                        Swal.fire({
                            title: 'Konfirmasi Pengembalian',
                            html: `Apakah Anda yakin ingin mengembalikan buku "<strong>${escapeHtml(judul)}</strong>"?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#dc3545',
                            confirmButtonText: 'Ya, Kembalikan!',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: 'Memproses...',
                                    text: 'Sedang mengembalikan buku',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                
                                document.getElementById('single_return_id').value = id;
                                document.getElementById('singleReturnForm').submit();
                            }
                        });
                    });
                }
            });
            
            // Re-attach event listeners untuk checkbox
            document.querySelectorAll('.borrow-checkbox').forEach(cb => {
                if (!cb.hasAttribute('data-listener-attached')) {
                    cb.setAttribute('data-listener-attached', 'true');
                    cb.addEventListener('change', updateSelectAllCheckbox);
                }
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
    }
</script>

<?php include '../footer.php'; ?>