<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = "Kelola User";

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Cek apakah user memiliki peminjaman aktif
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE user_id = ? AND status = 'dipinjam'");
    $stmt->execute([$id]);
    $pinjaman_aktif = $stmt->fetch()['total'];

    if ($pinjaman_aktif > 0) {
        $_SESSION['swal_error'] = "Siswa tidak bisa dihapus karena masih memiliki $pinjaman_aktif buku yang dipinjam!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$id]);
        $_SESSION['swal_success'] = "Siswa berhasil dihapus!";
    }
    redirect('admin_user.php');
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $role = $_POST['role'];

    $errors = [];

    if (empty($username)) {
        $errors[] = "Username harus diisi";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username minimal 3 karakter";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username hanya boleh berisi huruf, angka, dan underscore";
    }

    if (empty($password)) {
        $errors[] = "Password harus diisi";
    } elseif (strlen($password) < 3) {
        $errors[] = "Password minimal 3 karakter";
    }

    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap harus diisi";
    }

    // Cek username sudah ada
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username '$username' sudah digunakan!";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $password, $nama_lengkap, $role]);
            $_SESSION['swal_success'] = "User '$username' berhasil ditambahkan!";
        } catch (PDOException $e) {
            $_SESSION['swal_error'] = "Gagal menambahkan user: " . $e->getMessage();
        }
    } else {
        $_SESSION['swal_error'] = implode("<br>", $errors);
    }
    redirect('admin_user.php');
}

// Handle edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id = $_POST['id'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $role = $_POST['role'];

    if (empty($nama_lengkap)) {
        $_SESSION['swal_error'] = "Nama lengkap harus diisi!";
    } else {
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            if (strlen($password) < 3) {
                $_SESSION['swal_error'] = "Password minimal 3 karakter!";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, role=?, password=? WHERE id=? AND role != 'admin'");
                $stmt->execute([$nama_lengkap, $role, $password, $id]);
                $_SESSION['swal_success'] = "User berhasil diupdate!";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, role=? WHERE id=? AND role != 'admin'");
            $stmt->execute([$nama_lengkap, $role, $id]);
            $_SESSION['swal_success'] = "User berhasil diupdate!";
        }
    }
    redirect('admin_user.php');
}

// Get filter
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query users
$sql = "SELECT * FROM users WHERE role != 'admin'";
$params = [];

if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR nama_lengkap LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY role, username";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
$total_siswa = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$total_admin = $stmt->fetch()['total'];

$swal_success = isset($_SESSION['swal_success']) ? $_SESSION['swal_success'] : '';
$swal_error = isset($_SESSION['swal_error']) ? $_SESSION['swal_error'] : '';
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

    .btn-small {
        padding: 4px 12px;
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

    .btn-add {
        background: #2c3e50;
        color: white;
    }

    .btn-add:hover {
        background: #1a252f;
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

    .form-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-card h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 18px;
        border-left: 4px solid #2c3e50;
        padding-left: 15px;
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
        font-size: 14px;
        box-sizing: border-box;
    }

    input:focus,
    select:focus {
        outline: none;
        border-color: #2c3e50;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
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

    .badge-siswa {
        background: #d4edda;
        color: #155724;
    }

    .badge-admin {
        background: #cce5ff;
        color: #004085;
    }

    .empty-state {
        text-align: center;
        padding: 60px;
        background: white;
        border-radius: 12px;
        color: #666;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
        border-radius: 12px;
        width: 450px;
        max-width: 90%;
    }

    .modal-content h3 {
        margin-bottom: 20px;
        color: #333;
    }

    .modal-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .modal-buttons button {
        flex: 1;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .table-header h3 {
        margin: 0;
    }

    .text-center {
        text-align: center;
    }

    small {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #888;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .filter-container {
            flex-direction: column;
        }

        .filter-group {
            width: 100%;
        }

        .table-header {
            flex-direction: column;
            align-items: stretch;
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
    }
</style>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $total_siswa ?></div>
        <div class="stat-label">Siswa</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_admin ?></div>
        <div class="stat-label">Admin</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $total_siswa + $total_admin ?></div>
        <div class="stat-label">Total User</div>
    </div>
</div>

<!-- Form Tambah User -->
<div class="form-card">
    <h3>Tambah User Baru</h3>
    <form method="POST" id="addUserForm">
        <div class="form-row">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" placeholder="contoh: siswa_baru" required autocomplete="off">
                <small>Minimal 3 karakter, hanya huruf, angka, underscore</small>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Minimal 3 karakter" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" placeholder="Nama lengkap user" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="siswa">Siswa</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <button type="submit" name="add" class="btn btn-add">Simpan User</button>
    </form>
</div>

<!-- Filter dan Daftar User -->
<div class="filter-container">
    <div class="filter-group">
        <label>Filter Role</label>
        <select id="roleFilter" onchange="window.location.href='?role='+this.value+'&search=<?= urlencode($search) ?>'">
            <option value="" <?= empty($role_filter) ? 'selected' : '' ?>>Semua Role</option>
            <option value="siswa" <?= $role_filter == 'siswa' ? 'selected' : '' ?>>Siswa</option>
            <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Cari User</label>
        <form method="GET" id="searchForm" style="display: flex; gap: 10px;">
            <input type="hidden" name="role" value="<?= $role_filter ?>">
            <input type="text" name="search" placeholder="Cari username atau nama..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
            <button type="submit" class="btn btn-cari">Cari</button>
            <?php if ($search): ?>
                <a href="?role=<?= $role_filter ?>" class="btn btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-header">
    <h3>Daftar User</h3>
</div>

<?php if (empty($users)): ?>
    <div class="empty-state">
        <p>Tidak ada user ditemukan.</p>
        <p>Silakan tambahkan user baru menggunakan form di atas.</p>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table id="userTable">
            <thead>
                <tr>
                    <th class="text-center">No</th>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th class="text-center">Role</th>
                    <th class="text-center">Tanggal Daftar</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($users as $u): 
                ?>
                <tr>
                    <td class="text-center" data-label="No"><?= $no++ ?></td>
                    <td data-label="Username"><?= htmlspecialchars($u['username']) ?></td>
                    <td data-label="Nama Lengkap"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                    <td class="text-center" data-label="Role">
                        <span class="badge badge-<?= $u['role'] ?>">
                            <?= $u['role'] == 'siswa' ? 'Siswa' : 'Admin' ?>
                        </span>
                    </td>
                    <td class="text-center" data-label="Tanggal Daftar"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-center" data-label="Aksi">
                        <button class="btn btn-small btn-edit" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">Edit</button>
                        <button class="btn btn-small btn-delete" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Edit User -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit User</h3>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" id="edit_nama" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="edit_role">
                    <option value="siswa">Siswa</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="password" placeholder="Kosongkan jika tidak diubah">
                <small>Minimal 3 karakter jika diisi</small>
            </div>
            <div class="modal-buttons">
                <button type="submit" name="edit" class="btn btn-update">Update</button>
                <button type="button" class="btn btn-batal" onclick="closeModal()">Batal</button>
            </div>
        </form>
    </div>
</div>

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
        html: <?= json_encode($swal_error) ?>,
        confirmButtonColor: "#dc3545"
    });
</script>
<?php endif; ?>

<script>
    function editUser(user) {
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_nama').value = user.nama_lengkap;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
        // Reset password field
        document.querySelector('#editUserForm input[name="password"]').value = '';
    }

    function confirmDelete(id, username) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "User '" + username + "' akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Sedang menghapus user',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                window.location.href = '?delete=' + id;
            }
        });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        let modal = document.getElementById('editModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Form validation for add user
    document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
        let username = document.querySelector('#addUserForm input[name="username"]').value.trim();
        let password = document.querySelector('#addUserForm input[name="password"]').value;
        let namaLengkap = document.querySelector('#addUserForm input[name="nama_lengkap"]').value.trim();
        
        if (username.length < 3) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Username minimal 3 karakter!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Username hanya boleh berisi huruf, angka, dan underscore!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        if (password.length < 3) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Password minimal 3 karakter!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        if (namaLengkap === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Nama lengkap harus diisi!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        Swal.fire({
            title: 'Menyimpan...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });

    // Form validation for edit user
    document.getElementById('editUserForm')?.addEventListener('submit', function(e) {
        let namaLengkap = document.getElementById('edit_nama').value.trim();
        let password = document.querySelector('#editUserForm input[name="password"]').value;
        
        if (namaLengkap === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Nama lengkap harus diisi!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        if (password !== '' && password.length < 3) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Password minimal 3 karakter!',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
        
        Swal.fire({
            title: 'Mengupdate...',
            text: 'Mohon tunggu',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    });
    
    // Allow Enter key to submit search
    const searchInput = document.querySelector('#searchForm input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });
    }
</script>

<?php include '../footer.php'; ?>