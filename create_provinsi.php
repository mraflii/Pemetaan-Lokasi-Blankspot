<?php
include "config/db.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Provinsi Baru</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="css/create_provinsi.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header fade-in">
            <h1 class="page-title"><i class="fas fa-plus-circle"></i> Tambah Provinsi Baru</h1>
            <p class="page-subtitle">Tambahkan provinsi baru ke dalam sistem</p>
        </div>

        <!-- Form Container -->
        <div class="form-container fade-in">
            <!-- Info Card -->
            <div class="info-card">
                <div class="info-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <p class="info-text">
                    Provinsi adalah level tertinggi dalam hierarki wilayah. 
                    Pastikan kode provinsi mengikuti standar yang berlaku.
                </p>
            </div>

            <form action="proses/proses_tambah_provinsi.php" method="post" id="tambahProvinsiForm">
                <input type="hidden" name="action" value="add_provinsi">

                <!-- Kode Provinsi -->
                <div class="form-group">
                    <label for="kode">
                        <i class="fas fa-code"></i> Kode Provinsi
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               id="kode" 
                               name="provinsi[kode]" 
                               placeholder="Masukkan kode provinsi"
                               required
                               maxlength="2"
                               pattern="[0-9]{2}"
                               title="Kode provinsi harus 2 digit angka">
                        <i class="fas fa-hashtag input-icon"></i>
                    </div>
                    <p class="help-text">Kode provinsi harus terdiri dari 2 digit angka (contoh: 11, 12, 13)</p>
                    
                    <div class="example-box">
                        <div class="example-title">Contoh Kode Provinsi:</div>
                        <div class="example-content">
                            <strong>11</strong> - Aceh<br>
                            <strong>12</strong> - Sumatera Utara<br>
                            <strong>13</strong> - Sumatera Barat
                        </div>
                    </div>
                </div>

                <!-- Nama Provinsi -->
                <div class="form-group">
                    <label for="nama">
                        <i class="fas fa-tag"></i> Nama Provinsi
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               id="nama" 
                               name="provinsi[nama]" 
                               placeholder="Masukkan nama provinsi"
                               required
                               maxlength="100">
                        <i class="fas fa-map input-icon"></i>
                    </div>
                    <p class="help-text">Masukkan nama provinsi lengkap sesuai dengan nama resmi</p>
                    
                    <div class="example-box">
                        <div class="example-title">Contoh Nama Provinsi:</div>
                        <div class="example-content">
                            <strong>Aceh</strong><br>
                            <strong>Sumatera Utara</strong><br>
                            <strong>Jawa Barat</strong>
                        </div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-eye"></i> Preview Data
                    </label>
                    <div class="example-box">
                        <div class="example-title">Data yang akan disimpan:</div>
                        <div class="example-content">
                            Kode: <strong id="previewKode">-</strong><br>
                            Nama: <strong id="previewNama">-</strong>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="hasil_pemetaan.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" class="btn btn-success pulse">
                        <i class="fas fa-save"></i> Simpan Provinsi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Elements
        const kodeInput = document.getElementById('kode');
        const namaInput = document.getElementById('nama');
        const previewKode = document.getElementById('previewKode');
        const previewNama = document.getElementById('previewNama');
        const form = document.getElementById('tambahProvinsiForm');

        // Update preview in real-time
        function updatePreview() {
            previewKode.textContent = kodeInput.value || '-';
            previewNama.textContent = namaInput.value || '-';
        }

        // Event listeners for real-time preview
        kodeInput.addEventListener('input', updatePreview);
        namaInput.addEventListener('input', updatePreview);

        // Kode validation - only numbers
        kodeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 2) {
                this.value = this.value.slice(0, 2);
            }
        });

        // Nama validation - capitalize first letter of each word
        namaInput.addEventListener('blur', function(e) {
            if (this.value) {
                this.value = this.value.replace(/\b\w/g, function(l) {
                    return l.toUpperCase();
                });
                updatePreview();
            }
        });

        // Form validation
        form.addEventListener('submit', function(e) {
            const kode = kodeInput.value.trim();
            const nama = namaInput.value.trim();

            if (!kode || !nama) {
                e.preventDefault();
                alert('Harap lengkapi semua field!');
                return;
            }

            if (kode.length !== 2) {
                e.preventDefault();
                alert('Kode provinsi harus terdiri dari 2 digit angka!');
                kodeInput.focus();
                return;
            }

            if (!confirm(`Tambahkan provinsi baru?\n\nKode: ${kode}\nNama: ${nama}`)) {
                e.preventDefault();
                return;
            }
        });

        // Auto-focus on kode input
        document.addEventListener('DOMContentLoaded', function() {
            kodeInput.focus();
            updatePreview();
        });

        // Enter key navigation
        kodeInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                namaInput.focus();
            }
        });

        namaInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.querySelector('button[type="submit"]').focus();
            }
        });
    </script>
</body>
</html>