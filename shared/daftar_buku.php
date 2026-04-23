<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../index.php');
}

$page_title = "Daftar Buku";

// Handle peminjaman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pinjam'])) {
    $buku_id = $_POST['buku_id'];
    $user_id = $_SESSION['user_id'];
    $tanggal_pinjam = date('Y-m-d');

    // Cek apakah sudah meminjam buku ini
    $stmt = $pdo->prepare("SELECT * FROM peminjaman WHERE user_id = ? AND buku_id = ? AND status = 'dipinjam'");
    $stmt->execute([$user_id, $buku_id]);
    $sudahPinjam = $stmt->fetch();

    if ($sudahPinjam) {
        $_SESSION['swal_error'] = "Anda sudah meminjam buku ini dan belum mengembalikannya!";
        redirect('daftar_buku.php');
    }

    // Cek stok buku
    $stmt = $pdo->prepare("SELECT stok, judul FROM buku WHERE id = ?");
    $stmt->execute([$buku_id]);
    $buku = $stmt->fetch();

    if (!$buku) {
        $_SESSION['swal_error'] = "Buku tidak ditemukan!";
        redirect('daftar_buku.php');
    }

    if ($buku['stok'] > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE buku SET stok = stok - 1 WHERE id = ? AND stok > 0");
            $stmt->execute([$buku_id]);

            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO peminjaman (user_id, buku_id, tanggal_pinjam, status) VALUES (?, ?, ?, 'dipinjam')");
                $stmt->execute([$user_id, $buku_id, $tanggal_pinjam]);
                $pdo->commit();
                $_SESSION['swal_success'] = "Buku '{$buku['judul']}' berhasil dipinjam!";
            } else {
                $pdo->rollBack();
                $_SESSION['swal_error'] = "Stok buku habis!";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else {
        $_SESSION['swal_error'] = "Stok buku habis!";
    }

    redirect('daftar_buku.php');
}

// Ambil session messages
$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : '';
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : '';
unset($_SESSION['swal_success']);
unset($_SESSION['swal_error']);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$kategori_filter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Pagination
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Buat folder upload path untuk cek file gambar
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/perpus_sede/uploads/buku/';

// Query untuk menghitung total data
$count_sql = "SELECT COUNT(*) as total 
        FROM buku b 
        LEFT JOIN kategori k ON b.kategori_id = k.id 
        WHERE b.stok > 0";
$count_params = [];

if (!empty($search)) {
    $count_sql .= " AND (b.judul LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $count_sql .= " AND b.kategori_id = ?";
    $count_params[] = $kategori_filter;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_params);
$total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $limit);

// Query utama dengan pagination dan urutan abjad
$sql = "SELECT b.*, k.nama_kategori 
        FROM buku b 
        LEFT JOIN kategori k ON b.kategori_id = k.id 
        WHERE b.stok > 0";

$params = [];

if (!empty($search)) {
    $sql .= " AND (b.judul LIKE ? OR b.penulis LIKE ? OR b.penerbit LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $sql .= " AND b.kategori_id = ?";
    $params[] = $kategori_filter;
}

// Urutkan berdasarkan judul secara abjad A-Z
$sql .= " ORDER BY b.judul ASC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);

// Bind parameter pencarian dan pagination (semua menggunakan positional parameter)
$param_index = 1;
foreach ($params as $value) {
    $stmt->bindValue($param_index++, $value);
}
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
$stmt->execute();
$buku = $stmt->fetchAll();

$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, b.judul, b.penulis, b.penerbit, b.id as buku_id
    FROM peminjaman p 
    JOIN buku b ON p.buku_id = b.id 
    WHERE p.user_id = ? AND p.status = 'dipinjam'
");
$stmt->execute([$_SESSION['user_id']]);
$pinjaman_aktif = $stmt->fetchAll();

$borrowed_ids = [];
foreach ($pinjaman_aktif as $p) {
    $borrowed_ids[] = $p['buku_id'];
}

include '../header.php';
?>

<!-- Include SweetAlert2 CSS and JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .btn {
        display: inline-block;
        padding: 8px 16px;
        background: #E1E8ED;
        color: black;
        text-decoration: none;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn:hover {
        background: #66757F;
        color: white;
    }

    .btn-pinjam {
        background: #4d88ff;
        color: white;
    }

    .btn-pinjam:hover {
        background: #3366cc;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-disabled {
        background: #6c757d;
        cursor: not-allowed;
        color: white;
    }

    .btn-disabled:hover {
        background: #6c757d;
        transform: none;
    }

    .btn-sm {
        padding: 4px 12px;
        font-size: 12px;
    }

    .btn-cari {
        background: #333;
        color: white;
    }

    .btn-cari:hover {
        background: #555;
    }

    .btn-reset {
        background: #6c757d;
        color: white;
    }

    .btn-reset:hover {
        background: #5a6268;
    }

    /* Cover Image Styles */
    .gambar-thumbnail {
        width: 50px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    
    .gambar-thumbnail-pinjam {
        width: 40px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
    }

    /* Statistics Grid Styles */
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
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

    /* Filter Container Styles */
    .filter-container {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

    .filter-group select,
    .filter-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #2c3e50;
    }

    /* Table Styles */
    table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        border-collapse: collapse;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    th,
    td {
        padding: 12px;
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

    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
        color: #666;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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

    .pagination a,
    .pagination span {
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

    .text-center {
        text-align: center;
    }

    .borrowed-books-section {
        margin-bottom: 30px;
    }

    .section-title {
        margin-bottom: 15px;
        color: #333;
        font-size: 18px;
        font-weight: 600;
    }

    /* Responsive Styles */
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

        table,
        thead,
        tbody,
        th,
        td,
        tr {
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
            min-width: 100px;
        }

        td:last-child {
            border-bottom: none;
        }

        .pagination a,
        .pagination span {
            min-width: 32px;
            height: 32px;
            font-size: 12px;
        }
    }
</style>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= count($pinjaman_aktif) ?></div>
        <div class="stat-label">Buku Sedang Dipinjam</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_rows ?></div>
        <div class="stat-label">Buku Tersedia</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= count($kategori_list) ?></div>
        <div class="stat-label">Total Kategori</div>
    </div>
</div>

<!-- Active Loans Section -->
<?php if (!empty($pinjaman_aktif)): ?>
    <div class="borrowed-books-section">
        <div class="section-title">Buku yang Sedang Anda Pinjam</div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th class="text-center">No</th>
                        <th class="text-center">Cover</th>
                        <th>Judul</th>
                        <th>Penulis</th>
                        <th class="text-center">Tanggal Pinjam</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no_pinjam = 1; ?>
                    <?php foreach ($pinjaman_aktif as $p): 
                        // Ambil gambar buku
                        $stmt_gambar = $pdo->prepare("SELECT gambar FROM buku WHERE id = ?");
                        $stmt_gambar->execute([$p['buku_id']]);
                        $gambar_buku = $stmt_gambar->fetch();
                    ?>
                        <tr id="pinjam-row-<?= $p['id'] ?>">
                            <td class="text-center" data-label="No"><?= $no_pinjam++ ?></td>
                            <td class="text-center" data-label="Cover">
                                <?php if (!empty($gambar_buku['gambar']) && file_exists($upload_dir . $gambar_buku['gambar'])): ?>
                                    <img src="../uploads/buku/<?= $gambar_buku['gambar'] ?>" class="gambar-thumbnail-pinjam" alt="Cover">
                                <?php else: ?>
                                    <img src="../assets/img/no-cover.png" class="gambar-thumbnail-pinjam" alt="No Cover" style="background: #f0f0f0;">
                                <?php endif; ?>
                            </td>
                            <td data-label="Judul"><?= htmlspecialchars($p['judul']) ?></td>
                            <td data-label="Penulis"><?= htmlspecialchars($p['penulis']) ?></td>
                            <td class="text-center" data-label="Tanggal Pinjam"><?= date('d/m/Y', strtotime($p['tanggal_pinjam'])) ?></td>
                            <td class="text-center" data-label="Status">
                                <span class="badge badge-dipinjam">Dipinjam</span>
                            </td>
                            <td class="text-center" data-label="Aksi">
                                <button class="btn btn-success btn-sm return-book"
                                    data-id="<?= $p['id'] ?>"
                                    data-judul="<?= htmlspecialchars($p['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                    Kembalikan
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="stats" style="background: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <p>Anda sedang tidak meminjam buku apapun</p>
        <p style="margin-top: 5px; color: #666;">Silakan pinjam buku di bawah</p>
    </div>
<?php endif; ?>

<!-- Filter Container -->
<div class="filter-container">
    <div class="filter-group">
        <label>Filter Kategori</label>
        <select id="kategoriFilter">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategori_list as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kategori_filter == $k['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($k['nama_kategori']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Cari Buku</label>
        <form method="GET" id="searchForm" style="display: flex; gap: 10px;">
            <input type="hidden" name="page" value="1">
            <input type="text" name="search" placeholder="Cari judul, penulis, atau penerbit..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
            <button type="submit" class="btn btn-cari btn-sm">Cari</button>
            <?php if(!empty($search) || !empty($kategori_filter)): ?>
                <a href="daftar_buku.php" class="btn btn-reset btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Books List -->
<?php if (empty($buku)): ?>
    <div class="empty-state">
        <p>Tidak ada buku ditemukan.</p>
        <?php if(!empty($search) || !empty($kategori_filter)): ?>
            <a href="daftar_buku.php" class="btn btn-cari" style="margin-top: 15px;">Lihat Semua Buku</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th class="text-center">No</th>
                    <th class="text-center">Cover</th>
                    <th>Judul</th>
                    <th>Penulis</th>
                    <th>Penerbit</th>
                    <th>Kategori</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $start_number = $offset + 1;
                foreach ($buku as $index => $b):
                ?>
                    <tr id="buku-row-<?= $b['id'] ?>">
                        <td class="text-center" data-label="No"><?= $start_number + $index ?></td>
                        <td class="text-center" data-label="Cover">
                            <?php if (!empty($b['gambar']) && file_exists($upload_dir . $b['gambar'])): ?>
                                <img src="../uploads/buku/<?= $b['gambar'] ?>" class="gambar-thumbnail" alt="Cover">
                            <?php else: ?>
                                <img src="../assets/img/no-cover.png" class="gambar-thumbnail" alt="No Cover" style="background: #f0f0f0;">
                            <?php endif; ?>
                        </td>
                        <td data-label="Judul"><?= htmlspecialchars($b['judul']) ?></td>
                        <td data-label="Penulis"><?= htmlspecialchars($b['penulis']) ?></td>
                        <td data-label="Penerbit"><?= htmlspecialchars($b['penerbit'] ?? '-') ?></td>
                        <td data-label="Kategori"><?= htmlspecialchars($b['nama_kategori'] ?? '-') ?></td>
                        <td class="text-center" data-label="Stok">
                            <span style="font-weight: bold; color: <?= $b['stok'] <= 3 ? '#dc3545' : '#28a745' ?>;">
                                <?= $b['stok'] ?>
                            </span>
                        </td>
                        <td class="text-center" data-label="Aksi">
                            <?php if (in_array($b['id'], $borrowed_ids)): ?>
                                <span class="btn btn-disabled btn-sm">Sudah Dipinjam</span>
                            <?php else: ?>
                                <button class="btn btn-pinjam btn-sm borrow-book"
                                    data-id="<?= $b['id'] ?>"
                                    data-judul="<?= htmlspecialchars($b['judul'], ENT_QUOTES, 'UTF-8') ?>">
                                    Pinjam
                                </button>
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
            Menampilkan <?= count($buku) ?> dari <?= $total_rows ?> buku
        </div>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori_filter) ?>">&laquo; Sebelumnya</a>
            <?php else: ?>
                <span class="disabled">&laquo; Sebelumnya</span>
            <?php endif; ?>

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
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori_filter) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span>...</span>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&kategori=<?= urlencode($kategori_filter) ?>">Selanjutnya &raquo;</a>
            <?php else: ?>
                <span class="disabled">Selanjutnya &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Hidden Forms -->
<form id="borrowForm" method="POST" style="display: none;">
    <input type="hidden" name="buku_id" id="borrow_buku_id">
    <input type="hidden" name="pinjam" value="1">
</form>

<!-- SweetAlert2 Scripts -->
<?php if ($swal_success): ?>
<script>
    Swal.fire({
        position: "top-end",
        icon: "success",
        title: "Berhasil!",
        text: <?= json_encode($swal_success) ?>,
        showConfirmButton: false,
        timer: 1500,
        toast: true
    });
</script>
<?php endif; ?>

<?php if ($swal_error): ?>
<script>
    Swal.fire({
        icon: "error",
        title: "Gagal!",
        text: <?= json_encode($swal_error) ?>,
        confirmButtonColor: "#dc3545"
    });
</script>
<?php endif; ?>

<script>
    // Escape HTML function
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Apply filters when category changes
    const kategoriFilter = document.getElementById('kategoriFilter');
    if (kategoriFilter) {
        kategoriFilter.addEventListener('change', function() {
            const kategori = this.value;
            const search = document.querySelector('input[name="search"]').value;
            let url = 'daftar_buku.php?page=1';
            
            if (kategori) {
                url += '&kategori=' + encodeURIComponent(kategori);
            }
            if (search) {
                url += '&search=' + encodeURIComponent(search);
            }
            
            window.location.href = url;
        });
    }

    // Konfirmasi peminjaman buku dengan SweetAlert2
    document.querySelectorAll('.borrow-book').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const bukuId = this.dataset.id;
            const judul = this.dataset.judul;

            Swal.fire({
                title: 'Konfirmasi Peminjaman',
                html: `Apakah Anda yakin ingin meminjam buku <strong>"${escapeHtml(judul)}"</strong>?<br><br>
                       <small style="color:#666;">Lama pinjam maksimal 7 hari</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4d88ff',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Ya, Pinjam!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Sedang memproses peminjaman buku',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    document.getElementById('borrow_buku_id').value = bukuId;
                    document.getElementById('borrowForm').submit();
                }
            });
        });
    });

    // Konfirmasi pengembalian buku dengan SweetAlert2 menggunakan AJAX
    document.querySelectorAll('.return-book').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const peminjamanId = this.dataset.id;
            const judul = this.dataset.judul;

            Swal.fire({
                title: 'Konfirmasi Pengembalian',
                html: `Apakah Anda yakin ingin mengembalikan buku <strong>"${escapeHtml(judul)}"</strong>?`,
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
                        text: 'Sedang memproses pengembalian buku',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('../shared/kembalikan_buku.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `peminjaman_id=${peminjamanId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.success) {
                            Swal.fire({
                                position: "top-end",
                                icon: "success",
                                title: "Berhasil!",
                                text: data.message,
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Gagal!",
                                text: data.message,
                                confirmButtonColor: "#dc3545"
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire({
                            icon: "error",
                            title: "Error!",
                            text: "Terjadi kesalahan saat menghubungi server.",
                            confirmButtonColor: "#dc3545"
                        });
                        console.error('Error:', error);
                    });
                }
            });
        });
    });

    // Allow Enter key to submit search
    const searchInput = document.querySelector('#searchForm input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('searchForm').submit();
            }
        });
    }
</script>

<?php include '../footer.php'; ?>