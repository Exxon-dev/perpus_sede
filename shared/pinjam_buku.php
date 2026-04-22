<?php
// Proses pinjam
if (isset($_GET['pinjam'])) {
    $buku_id = (int)$_GET['pinjam'];
    $user_id = $_SESSION['user_id'];

    // CEK APAKAH SUDAH MEMINJAM BUKU YANG SAMA
    $cek_pinjam = mysqli_query($conn, "SELECT * FROM peminjaman 
                                        WHERE user_id = '$user_id' 
                                        AND buku_id = '$buku_id' 
                                        AND status = 'dipinjam'");

    if (mysqli_num_rows($cek_pinjam) > 0) {
        echo "<script>alert('Anda sudah meminjam buku ini! Belum dikembalikan.');</script>";
    } else {
        // Cek stok
        $cek_stok = mysqli_query($conn, "SELECT stok FROM buku WHERE id=$buku_id");
        $stok_data = mysqli_fetch_assoc($cek_stok);

        if ($stok_data['stok'] > 0) {
            $tgl_pinjam = date('Y-m-d');
            $tgl_kembali = date('Y-m-d', strtotime('+7 days'));

            $insert = mysqli_query($conn, "INSERT INTO peminjaman (user_id, buku_id, tanggal_pinjam, tanggal_kembali) 
                                 VALUES ('$user_id', '$buku_id', '$tgl_pinjam', '$tgl_kembali')");

            if ($insert) {
                mysqli_query($conn, "UPDATE buku SET stok = stok - 1 WHERE id=$buku_id");
                echo "<script>alert('Buku berhasil dipinjam');</script>";
            }
        } else {
            echo "<script>alert('Stok buku habis!');</script>";
        }
    }

    // Redirect untuk menghapus parameter GET
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit();
}

// PROSES PENGEMBALIAN - DIPERBAIKI
if (isset($_GET['kembalikan'])) {
    $pinjam_id = (int)$_GET['kembalikan'];
    $buku_id = (int)$_GET['buku_id'];

    // Debug: cek data sebelum update
    $cek = mysqli_query($conn, "SELECT * FROM peminjaman WHERE id = $pinjam_id");
    if (mysqli_num_rows($cek) == 0) {
        echo "<script>alert('Data peminjaman tidak ditemukan!');</script>";
    } else {
        // Update status peminjaman
        $update = mysqli_query($conn, "UPDATE peminjaman 
                                       SET status = 'dikembalikan', 
                                           tanggal_kembali = NOW() 
                                       WHERE id = $pinjam_id");

        if ($update) {
            // Update stok buku
            $update_stok = mysqli_query($conn, "UPDATE buku SET stok = stok + 1 WHERE id = $buku_id");

            if ($update_stok) {
                echo "<script>alert('Buku berhasil dikembalikan!');</script>";
            } else {
                echo "<script>alert('Gagal update stok: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            echo "<script>alert('Gagal mengembalikan buku: " . mysqli_error($conn) . "');</script>";
        }
    }

    // Redirect ke halaman yang sama tanpa parameter GET
    echo "<script>window.location.href = 'dashboard.php';</script>";
    exit();
}

// Ambil daftar peminjaman user
$user_id = $_SESSION['user_id'];
$pinjaman = mysqli_query($conn, "SELECT peminjaman.*, buku.judul, buku.id as buku_id 
                                  FROM peminjaman 
                                  JOIN buku ON peminjaman.buku_id = buku.id 
                                  WHERE user_id = '$user_id' AND status = 'dipinjam'");
?>

<h4>Buku yang sedang dipinjam</h4>
<?php if (mysqli_num_rows($pinjaman) == 0): ?>
    <p style="color: #666; padding: 10px; background: #f9f9f9;">
        Belum ada buku yang dipinjam
    </p>
<?php else: ?>
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="background: #f0f0f0;">Judul Buku</th>
                <th style="background: #f0f0f0;">Tanggal Pinjam</th>
                <th style="background: #f0f0f0;">Batas Kembali</th>
                <th style="background: #f0f0f0;">Status</th>
                <th style="background: #f0f0f0;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = mysqli_fetch_assoc($pinjaman)): ?>
                <tr>
                    <td><?= htmlspecialchars($p['judul']) ?></td>
                    <td><?= $p['tanggal_pinjam'] ?></td>
                    <td><?= $p['tanggal_kembali'] ?></td>
                    <td>
                        <?php if ($p['status'] == 'dipinjam'): ?>
                            <span style="color: #f57c00;">● Dipinjam</span>
                        <?php else: ?>
                            <span style="color: #4caf50;">● Dikembalikan</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['status'] == 'dipinjam'): ?>
                            <a href="?kembalikan=<?= $p['id'] ?>&buku_id=<?= $p['buku_id'] ?>"
                                class="btn btn-warning"
                                style="background: #f57c00; color: white; padding: 5px 10px; text-decoration: none;"
                                onclick="return confirm('Yakin ingin mengembalikan buku ini?')">
                                Kembalikan
                            </a>
                        <?php else: ?>
                            <span style="color: #999;">Sudah dikembalikan</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>