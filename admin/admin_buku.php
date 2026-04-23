<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = "Kelola Buku";

// Set error reporting untuk debugging (nonaktifkan di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Buat folder upload jika belum ada (menggunakan path absolut)
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/perpus_sede/uploads/buku/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle delete - juga hapus file gambar
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Ambil nama file gambar sebelum hapus
    $stmt = $pdo->prepare("SELECT gambar FROM buku WHERE id = ?");
    $stmt->execute([$id]);
    $buku = $stmt->fetch();
    
    if ($buku && !empty($buku['gambar'])) {
        $gambar_path = $upload_dir . $buku['gambar'];
        if (file_exists($gambar_path)) {
            unlink($gambar_path);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM buku WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['swal_success'] = "Buku berhasil dihapus!";
    redirect('admin_buku.php');
}

// Handle edit dengan upload gambar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $judul = $_POST['judul'];
    $penulis = $_POST['penulis'];
    $penerbit = $_POST['penerbit'];
    $tahun = $_POST['tahun_terbit'];
    $kategori_id = $_POST['kategori_id'];
    $stok = $_POST['stok'];
    
    // Proses upload gambar baru jika ada
    $gambar_baru = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0 && $_FILES['gambar']['size'] > 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['gambar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Hapus gambar lama
            $stmt = $pdo->prepare("SELECT gambar FROM buku WHERE id = ?");
            $stmt->execute([$id]);
            $buku_lama = $stmt->fetch();
            if ($buku_lama && !empty($buku_lama['gambar'])) {
                $gambar_lama_path = $upload_dir . $buku_lama['gambar'];
                if (file_exists($gambar_lama_path)) {
                    unlink($gambar_lama_path);
                }
            }
            
            // Upload gambar baru
            $new_filename = time() . '_' . uniqid() . '.' . $ext;
            $target = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
                $gambar_baru = $new_filename;
            }
        }
    }
    
    // Update query
    if ($gambar_baru) {
        $stmt = $pdo->prepare("UPDATE buku SET judul=?, penulis=?, penerbit=?, tahun_terbit=?, kategori_id=?, stok=?, gambar=? WHERE id=?");
        $stmt->execute([$judul, $penulis, $penerbit, $tahun, $kategori_id, $stok, $gambar_baru, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE buku SET judul=?, penulis=?, penerbit=?, tahun_terbit=?, kategori_id=?, stok=? WHERE id=?");
        $stmt->execute([$judul, $penulis, $penerbit, $tahun, $kategori_id, $stok, $id]);
    }
    
    $_SESSION['swal_success'] = "Buku berhasil diupdate!";
    redirect('admin_buku.php');
}

// Handle add dengan upload gambar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $judul = $_POST['judul'];
    $penulis = $_POST['penulis'];
    $penerbit = $_POST['penerbit'];
    $tahun = $_POST['tahun_terbit'];
    $kategori_id = $_POST['kategori_id'];
    $stok = $_POST['stok'];
    $gambar = null;
    
    // Proses upload gambar
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0 && $_FILES['gambar']['size'] > 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['gambar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Gunakan path absolut yang sudah didefinisikan
            $new_filename = time() . '_' . uniqid() . '.' . $ext;
            $target = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
                $gambar = $new_filename;
            }
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO buku (judul, penulis, penerbit, tahun_terbit, kategori_id, stok, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$judul, $penulis, $penerbit, $tahun, $kategori_id, $stok, $gambar]);
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
        font-size: 14px;
    }

    .btn:hover {
        background: #66757F;
        color: white;
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
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        color: #555;
        font-weight: 500;
    }

    input,
    select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    input[type="file"] {
        padding: 6px;
    }
    
    .gambar-preview {
        margin-top: 10px;
        max-width: 150px;
    }
    
    .gambar-preview img {
        width: 100%;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    
    .gambar-thumbnail {
        width: 50px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
    }
    
    /* Container untuk header Daftar Buku dan search */
    .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
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
        flex-wrap: wrap;
    }
    
    .search-input-group {
        width: 300px;
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
        border-collapse: collapse;
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
        max-height: 90vh;
        overflow-y: auto;
    }

    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
        gap: 5px;
        flex-wrap: wrap;
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
        flex-wrap: wrap;
        gap: 10px;
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
        }
        
        .search-input-group {
            width: 100%;
        }
        
        .search-form {
            width: 100%;
            flex-direction: column;
        }
        
        .search-buttons {
            width: 100%;
        }
        
        .search-buttons .btn {
            flex: 1;
            text-align: center;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        table {
            display: block;
            overflow-x: auto;
        }
    }
    
    small {
        font-size: 12px;
        color: #666;
    }
    
    .text-center {
        text-align: center;
    }
</style>

<div class="form-card">
    <h3>Tambah Buku Baru</h3>
    <form method="POST" id="addBookForm" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label>Judul Buku *</label>
                <input type="text" name="judul" required>
            </div>
            <div class="form-group">
                <label>Penulis *</label>
                <input type="text" name="penulis" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Penerbit</label>
                <input type="text" name="penerbit">
            </div>
            <div class="form-group">
                <label>Tahun Terbit</label>
                <input type="number" name="tahun_terbit" min="1900" max="<?= date('Y') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Kategori</label>
                <select name="kategori_id">
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Stok</label>
                <input type="number" name="stok" value="1" min="0">
            </div>
        </div>
        <div class="form-group">
            <label>Cover Buku</label>
            <input type="file" name="gambar" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this, 'preview_add')">
            <div id="preview_add" class="gambar-preview"></div>
            <small>Format: JPG, JPEG, PNG, GIF (Max: 2MB)</small>
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
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Cari judul, penulis, atau kategori...">
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

<div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th class="text-center">Cover</th>
                <th style="text-align: center;">Judul</th>
                <th style="text-align: center;">Penulis</th>
                <th style="text-align: center;">Kategori</th>
                <th class="text-center">Stok</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $start_number = $offset + 1;
            foreach ($buku_list as $index => $b): 
            ?>
            <tr>
                <td class="text-center"><?= $start_number + $index ?></td>
                <td class="text-center">
                    <?php if (!empty($b['gambar']) && file_exists($upload_dir . $b['gambar'])): ?>
                        <img src="../uploads/buku/<?= $b['gambar'] ?>" class="gambar-thumbnail" alt="Cover">
                    <?php else: ?>
                        <img src="../assets/img/no-cover.png" class="gambar-thumbnail" alt="No Cover" style="width: 50px; height: 60px; object-fit: cover; background: #f0f0f0;">
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($b['judul']) ?></td>
                <td><?= htmlspecialchars($b['penulis']) ?></td>
                <td><?= htmlspecialchars($b['nama_kategori']) ?></td>
                <td class="text-center"><?= $b['stok'] ?></td>
                <td class="text-center">
                    <button class="btn btn-small btn-edit" onclick="editBuku(<?= htmlspecialchars(json_encode($b)) ?>)">Edit</button>
                    <button class="btn btn-small btn-delete" onclick="confirmDelete(<?= $b['id'] ?>)">Hapus</button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($buku_list)): ?>
            <tr>
                <td colspan="7" class="text-center">Tidak ada data buku</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

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
        <form method="POST" id="editBookForm" enctype="multipart/form-data">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Judul *</label>
                <input type="text" name="judul" id="edit_judul" required>
            </div>
            <div class="form-group">
                <label>Penulis *</label>
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
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Stok</label>
                <input type="number" name="stok" id="edit_stok" min="0">
            </div>
            <div class="form-group">
                <label>Cover Buku</label>
                <input type="file" name="gambar" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this, 'preview_edit')">
                <div id="preview_edit" class="gambar-preview"></div>
                <div id="current_gambar" style="margin-top: 5px; font-size: 12px; color: #666;"></div>
                <small>Kosongkan jika tidak ingin mengubah gambar</small>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit" class="btn btn-update">Update</button>
                <button type="button" class="btn btn-batal" onclick="closeModal()">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- SweetAlert2 Script -->
<?php if ($swal_success): ?>
<script>
    Swal.fire({
        position: "top-end",
        icon: "success",
        title: "<?= htmlspecialchars($swal_success) ?>",
        showConfirmButton: false,
        timer: 1500,
        toast: true
    });
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
    Swal.fire({
        icon: "error",
        title: "Gagal!",
        text: "<?= htmlspecialchars($error) ?>",
        confirmButtonColor: "#dc3545"
    });
</script>
<?php endif; ?>

<script>
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            // Validasi ukuran file (max 2MB)
            if (input.files[0].size > 2 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'File terlalu besar',
                    text: 'Ukuran file maksimal 2MB'
                });
                input.value = '';
                document.getElementById(previewId).innerHTML = '';
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).innerHTML = '<img src="' + e.target.result + '" style="max-width: 150px; border-radius: 4px; border: 1px solid #ddd;">';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function editBuku(buku) {
        document.getElementById('edit_id').value = buku.id;
        document.getElementById('edit_judul').value = buku.judul;
        document.getElementById('edit_penulis').value = buku.penulis;
        document.getElementById('edit_penerbit').value = buku.penerbit || '';
        document.getElementById('edit_tahun').value = buku.tahun_terbit || '';
        document.getElementById('edit_kategori').value = buku.kategori_id || '';
        document.getElementById('edit_stok').value = buku.stok;
        
        // Tampilkan gambar saat ini
        var currentGambarDiv = document.getElementById('current_gambar');
        if (buku.gambar && buku.gambar !== '') {
            currentGambarDiv.innerHTML = 'Gambar saat ini: <img src="../uploads/buku/' + buku.gambar + '" style="max-width: 100px; margin-top: 5px; border-radius: 4px; border: 1px solid #ddd;"><br><small>Kosongkan jika tidak ingin mengubah gambar</small>';
        } else {
            currentGambarDiv.innerHTML = 'Tidak ada gambar<br><small>Upload gambar jika ingin menambahkan cover</small>';
        }
        
        // Reset preview
        document.getElementById('preview_edit').innerHTML = '';
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
        // Reset file input
        document.querySelector('#editBookForm input[type="file"]').value = '';
        document.getElementById('preview_edit').innerHTML = '';
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
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Allow Enter key to submit search
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchForm').submit();
        }
    });
</script>

<?php include '../footer.php'; ?>