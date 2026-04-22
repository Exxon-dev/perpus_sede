<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = "Kelola Buku";

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM buku WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['swal_success'] = "Buku berhasil dihapus!";
    redirect('admin_buku.php');
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $judul = $_POST['judul'];
    $penulis = $_POST['penulis'];
    $penerbit = $_POST['penerbit'];
    $tahun = $_POST['tahun_terbit'];
    $kategori_id = $_POST['kategori_id'];
    $stok = $_POST['stok'];

    $stmt = $pdo->prepare("UPDATE buku SET judul=?, penulis=?, penerbit=?, tahun_terbit=?, kategori_id=?, stok=? WHERE id=?");
    $stmt->execute([$judul, $penulis, $penerbit, $tahun, $kategori_id, $stok, $id]);
    $_SESSION['swal_success'] = "Buku berhasil diupdate!";
    redirect('admin_buku.php');
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $judul = $_POST['judul'];
    $penulis = $_POST['penulis'];
    $penerbit = $_POST['penerbit'];
    $tahun = $_POST['tahun_terbit'];
    $kategori_id = $_POST['kategori_id'];
    $stok = $_POST['stok'];

    $stmt = $pdo->prepare("INSERT INTO buku (judul, penulis, penerbit, tahun_terbit, kategori_id, stok) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$judul, $penulis, $penerbit, $tahun, $kategori_id, $stok]);
    $_SESSION['swal_success'] = "Buku berhasil ditambahkan!";
    redirect('admin_buku.php');
}

// Handle search
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build search query
$search_condition = "";
$search_params = [];

if (!empty($search_keyword)) {
    $search_condition = "WHERE (b.judul LIKE :keyword OR b.penulis LIKE :keyword OR k.nama_kategori LIKE :keyword)";
    $search_params[':keyword'] = "%$search_keyword%";
}

// Get total records for pagination with search
$total_sql = "SELECT COUNT(*) as total FROM buku b LEFT JOIN kategori k ON b.kategori_id = k.id $search_condition";
$total_stmt = $pdo->prepare($total_sql);
foreach ($search_params as $key => $value) {
    $total_stmt->bindValue($key, $value);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $limit);

// Get books with pagination, search, and sort by judul alphabetically
$sql = "SELECT b.*, k.nama_kategori FROM buku b LEFT JOIN kategori k ON b.kategori_id = k.id $search_condition ORDER BY b.judul ASC LIMIT :limit OFFSET :offset";
$buku = $pdo->prepare($sql);

foreach ($search_params as $key => $value) {
    $buku->bindValue($key, $value);
}
$buku->bindValue(':limit', $limit, PDO::PARAM_INT);
$buku->bindValue(':offset', $offset, PDO::PARAM_INT);
$buku->execute();
$buku_list = $buku->fetchAll();

$kategori = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['swal_success']);
unset($_SESSION['error']);

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
    }

    .btn:hover {
        background: #66757F;
    }

    .btn-small {
        padding: 4px 8px;
        font-size: 12px;
        color: white;
    }

    .btn-edit {
        background: #33CC33;
    }
    
    .btn-edit:hover {
        background: #009933;
    }

    .btn-delete {
        background: #dc3545;
    }

    .btn-delete:hover {
        background: #b91d47;
    }

    .btn-update {
        background: #0066FF;
        color: white;
    }
    .btn-update:hover {
        background: #0099FF;
    }

    .btn-batal {
        background: #5b9aa0;
        color: white;
    }
    
    .btn-cari {
        background: #333;
        color: white;
        padding: 8px 16px;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .btn-cari:hover {
        background: #66757F;
    }
    
    .btn-reset {
        background: #6c757d;
        color: white;
        padding: 8px 16px;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .btn-reset:hover {
        background: #333;
    }

    .form-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        color: #555;
    }

    input,
    select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    /* Container untuk header Daftar Buku dan search */
    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .header-section h3 {
        margin: 0;
    }
    
    .search-container {
        margin: 0;
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .search-input-group {
        width: 300px; /* Ukuran lebih pendek */
    }
    
    .search-input-group input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .search-buttons {
        display: flex;
        gap: 10px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    table {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    th {
        background: #f8f8f8;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        width: 500px;
    }

    .success {
        background: #d4edda;
        color: #155724;
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid #28a745;
    }

    .error {
        background: #f8d7da;
        color: #721c24;
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 20px;
        border-left: 4px solid #dc3545;
    }

    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 5px;
    }
    
    .pagination a, .pagination span {
        padding: 8px 16px;
        text-decoration: none;
        border: 1px solid #ddd;
        color: #333;
        border-radius: 4px;
        transition: background-color 0.3s;
    }
    
    .pagination a:hover {
        background-color: #E1E8ED;
    }
    
    .pagination .active {
        background-color: #0066FF;
        color: white;
        border: 1px solid #0066FF;
    }
    
    .pagination .disabled {
        color: #999;
        cursor: not-allowed;
    }
    
    .info-pagination {
        text-align: center;
        margin-top: 10px;
        color: #666;
        font-size: 14px;
    }
    
    .search-info {
        margin-bottom: 15px;
        padding: 10px;
        background: #e7f3ff;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .search-info span {
        color: #0066FF;
        font-weight: bold;
    }
    
    /* Responsif untuk mobile */
    @media (max-width: 768px) {
        .header-section {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .search-input-group {
            width: 100%;
        }
        
        .search-form {
            width: 100%;
        }
    }
</style>

<div class="form-card">
    <h3>Tambah Buku Baru</h3>
    <form method="POST" id="addBookForm">
        <div class="form-row">
            <div class="form-group"><label>Judul Buku</label><input type="text" name="judul" required></div>
            <div class="form-group"><label>Penulis</label><input type="text" name="penulis" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Penerbit</label><input type="text" name="penerbit"></div>
            <div class="form-group"><label>Tahun Terbit</label><input type="number" name="tahun_terbit"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori_id">
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Stok</label><input type="number" name="stok" value="1"></div>
        </div>
        <button type="submit" name="add" class="btn">Simpan Buku</button>
    </form>
</div>

<!-- Header dengan Daftar Buku dan Search di samping kanan -->
<div class="header-section">
    <h3>Daftar Buku</h3>
    
    <!-- Search Form -->
    <div class="search-container">
        <form method="GET" class="search-form" id="searchForm">
            <div class="search-input-group">
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Cari buku...">
            </div>
            <div class="search-buttons">
                <button type="submit" class="btn btn-cari">Cari</button>
                <?php if (!empty($search_keyword)): ?>
                    <button type="button" class="btn btn-reset" onclick="resetSearch()">Reset</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($search_keyword)): ?>
<div class="search-info">
    <div>Menampilkan hasil pencarian untuk: "<span><?= htmlspecialchars($search_keyword) ?></span>"</div>
    <div>Ditemukan: <?= $total_rows ?> buku</div>
</div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th style="text-align: center;">No</th>
            <th style="text-align: center;">Judul</th>
            <th style="text-align: center;">Penulis</th>
            <th style="text-align: center;">Kategori</th>
            <th style="text-align: center;">Stok</th>
            <th style="text-align: center;">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $start_number = $offset + 1;
        foreach ($buku_list as $index => $b): 
        ?>
            <tr>
                <td style="text-align: center;"><?= $start_number + $index ?></td>
                <td><?= htmlspecialchars($b['judul']) ?></td>
                <td><?= htmlspecialchars($b['penulis']) ?></td>
                <td><?= htmlspecialchars($b['nama_kategori']) ?></td>
                <td style="text-align: center;"><?= $b['stok'] ?></td>
                <td style="text-align: center;">
                    <button class="btn btn-small btn-edit" onclick="editBuku(<?= htmlspecialchars(json_encode($b)) ?>)">Edit</button>
                    <button class="btn btn-small btn-delete" onclick="confirmDelete(<?= $b['id'] ?>)">Hapus</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($buku_list)): ?>
            <tr>
                <td colspan="6" style="text-align: center;">Tidak ada data buku</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="info-pagination">
        Menampilkan <?= count($buku_list) ?> dari <?= $total_rows ?> data
    </div>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>">&laquo; Sebelumnya</a>
        <?php else: ?>
            <span class="disabled">&laquo; Sebelumnya</span>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?><?= !empty($search_keyword) ? '&search=' . urlencode($search_keyword) : '' ?>">Selanjutnya &raquo;</a>
        <?php else: ?>
            <span class="disabled">Selanjutnya &raquo;</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Buku</h3>
        <form method="POST" id="editBookForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Judul</label>
                <input type="text" name="judul" id="edit_judul" required>
            </div>
            <div class="form-group">
                <label>Penulis</label>
                <input type="text" name="penulis" id="edit_penulis" required>
            </div>
            <div class="form-group">
                <label>Penerbit</label>
                <input type="text" name="penerbit" id="edit_penerbit">
            </div>
            <div class="form-group">
                <label>Tahun</label>
                <input type="number" name="tahun_terbit" id="edit_tahun">
            </div>
            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori_id" id="edit_kategori">
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Stok</label><input type="number" name="stok" id="edit_stok"></div>
            <button type="submit" name="edit" class="btn btn-update">Update</button>
            <button type="button" class="btn btn-batal" onclick="closeModal()">Batal</button>
        </form>
    </div>
</div>

<!-- SweetAlert2 Script -->
<?php if ($swal_success): ?>
<script>
    Swal.fire({
        position: "top",
        icon: "success",
        title: "<?= htmlspecialchars($swal_success) ?>",
        showConfirmButton: false,
        timer: 1500
    });
</script>
<?php endif; ?>

<script>
    function editBuku(buku) {
        document.getElementById('edit_id').value = buku.id;
        document.getElementById('edit_judul').value = buku.judul;
        document.getElementById('edit_penulis').value = buku.penulis;
        document.getElementById('edit_penerbit').value = buku.penerbit || '';
        document.getElementById('edit_tahun').value = buku.tahun_terbit || '';
        document.getElementById('edit_kategori').value = buku.kategori_id || '';
        document.getElementById('edit_stok').value = buku.stok;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data buku akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete=' + id;
            }
        });
    }
    
    function resetSearch() {
        window.location.href = window.location.pathname.split('/').pop();
    }
    
    // Optional: Allow Enter key to submit search
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchForm').submit();
        }
    });
</script>

<?php include '../footer.php'; ?>