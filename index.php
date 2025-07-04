<?php
// Mulai session PHP di awal skrip
session_start();

// Cek apakah user sudah login
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;

// Tentukan URL untuk tombol "Mulai Diagnosa Sekarang"
$diagnosa_url = $is_logged_in ? "user/diagnosa.php" : "login.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pakar Diagnosa Penyakit Kulit</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Google Fonts - Poppins untuk tampilan modern -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome untuk ikon-ikon yang menarik -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom CSS Anda -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-stethoscope me-2"></i> <!-- Ikon stetoskop -->
                Sistem Pakar Kulit
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="#">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tentang">Tentang Sistem</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#fitur">Fitur</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#kontak">Kontak</a>
                    </li>
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle btn btn-outline-light text-white ms-lg-3" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?php echo $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'user/profile.php'; ?>">Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white ms-lg-3" href="login.php">Login / Daftar</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section d-flex align-items-center">
        <div class="container text-center">
            <h1 class="display-3 fw-bold animate__animated animate__fadeInDown">
                Diagnosa Penyakit Kulit Lebih Cepat dan Akurat
            </h1>
            <p class="lead mt-3 mb-4 animate__animated animate__fadeInUp">
                Sistem pakar berbasis web ini membantu Anda mendiagnosa penyakit kulit infeksi jamur Dermatofit
                menggunakan metode <span class="fw-bold">Forward Chaining</span> dan <span class="fw-bold">Certainty Factor</span>.
            </p>
            <a href="<?php echo $diagnosa_url; ?>" class="btn btn-warning btn-lg me-3 animate__animated animate__zoomIn">
                <i class="fas fa-flask me-2"></i> Mulai Diagnosa Sekarang
            </a>
            <a href="#tentang" class="btn btn-outline-light btn-lg animate__animated animate__zoomIn">
                <i class="fas fa-info-circle me-2"></i> Pelajari Lebih Lanjut
            </a>
        </div>
    </section>

    <!-- Tentang Sistem Section -->
    <section id="tentang" class="py-5 bg-white shadow-sm">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <img src="https://placehold.co/600x400/ADD8E6/000000?text=Ilustrasi+Sistem+Pakar" class="img-fluid rounded shadow-lg animate__animated animate__fadeInLeft" alt="Ilustrasi Sistem Pakar">
                </div>
                <div class="col-md-6 animate__animated animate__fadeInRight">
                    <h2 class="mb-4 fw-bold text-primary">Apa Itu Sistem Pakar Kami?</h2>
                    <p class="lead">Sistem pakar ini dirancang untuk meniru proses berpikir seorang pakar (dokter kulit) dalam memberikan diagnosis awal penyakit kulit.</p>
                    <p>Kami fokus pada diagnosis penyakit kulit akibat infeksi jamur Dermatofit, yang sering ditemukan di negara beriklim tropis seperti Indonesia. Dengan memanfaatkan data dan nilai keyakinan langsung dari pakar melalui wawancara, sistem ini memberikan alur penalaran yang terstruktur dan transparan dari gejala ke diagnosis.</p>
                    <p>Metode <span class="fw-bold">Forward Chaining</span> membantu penalaran otomatis dari gejala ke kesimpulan, sementara <span class="fw-bold">Certainty Factor</span> mengukur tingkat kepastian diagnosis.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Fitur Unggulan Section -->
    <section id="fitur" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold text-secondary">Fitur Unggulan</h2>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-4 col-md-6">
                    <div class="card card-feature text-center p-4 animate__animated animate__fadeInUp">
                        <div class="card-body">
                            <i class="fas fa-robot icon-feature mb-3"></i>
                            <h5 class="card-title fw-bold">Diagnosa Cerdas</h5>
                            <p class="card-text">Menerapkan metode Forward Chaining untuk penalaran otomatis dari gejala yang Anda rasakan.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-feature text-center p-4 animate__animated animate__fadeInUp animate__delay-1s">
                        <div class="card-body">
                            <i class="fas fa-percent icon-feature mb-3"></i>
                            <h5 class="card-title fw-bold">Tingkat Kepastian</h5>
                            <p class="card-text">Menggunakan metode Certainty Factor untuk memberikan tingkat keyakinan pada setiap hasil diagnosa.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-feature text-center p-4 animate__animated animate__fadeInUp animate__delay-2s">
                        <div class="card-body">
                            <i class="fas fa-mobile-alt icon-feature mb-3"></i>
                            <h5 class="card-title fw-bold">Responsif & Aksesibel</h5>
                            <p class="card-text">Didesain dengan Bootstrap agar mudah diakses dari berbagai perangkat (komputer, tablet, mobile).</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-feature text-center p-4 animate__animated animate__fadeInUp animate__delay-3s">
                        <div class="card-body">
                            <i class="fas fa-book-medical icon-feature mb-3"></i>
                            <h5 class="card-title fw-bold">Basis Pengetahuan Ahli</h5>
                            <p class="card-text">Data gejala, penyakit, dan aturan diagnosis diambil langsung dari pakar kulit.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-feature text-center p-4 animate__animated animate__fadeInUp animate__delay-4s">
                        <div class="card-body">
                            <i class="fas fa-shield-alt icon-feature mb-3"></i>
                            <h5 class="card-title fw-bold">Akurasi Teruji</h5>
                            <p class="card-text">Sistem diuji dengan BlackBox Testing untuk memastikan fungsionalitas dan output yang sesuai spesifikasi.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card card-feature text-center p-4 animate__animated animate__fadeInUp animate__delay-5s">
                        <div class="card-body">
                            <i class="fas fa-chart-line icon-feature mb-3"></i>
                            <h5 class="card-title fw-bold">Meningkatkan Kesadaran</h5>
                            <p class="card-text">Membantu masyarakat meningkatkan kesadaran terhadap pentingnya deteksi dini penyakit kulit.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-5 bg-primary text-white text-center">
        <div class="container">
            <h2 class="fw-bold mb-3">Siap Mendapatkan Diagnosa Awal Anda?</h2>
            <p class="lead mb-4">Manfaatkan teknologi sistem pakar untuk kesehatan kulit Anda.</p>
            <a href="<?php echo $diagnosa_url; ?>" class="btn btn-light btn-lg custom-btn-cta">
                <i class="fas fa-notes-medical me-2"></i> Mulai Diagnosa Sekarang
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer id="kontak" class="footer mt-auto py-4 bg-dark text-white">
        <div class="container text-center">
            <p class="mb-1">&copy; 2025 Sistem Pakar Diagnosa Penyakit Kulit. Hak Cipta Dilindungi.</p>
            <p class="mb-1">Disusun oleh: FARHAN ARIF INDIARTO (2157201044)</p>
            <p class="mb-0">Institut Teknologi dan Bisnis Ahmad Dahlan, Jakarta</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper (termasuk JS) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Optional: Animate.css for subtle animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</body>
</html>