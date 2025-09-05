<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project_indihome";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_order') {
    $nama = $conn->real_escape_string($_POST['nama']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $nomor = $conn->real_escape_string($_POST['nomor']);
    $paket = $conn->real_escape_string($_POST['paket']);
    $harga = $conn->real_escape_string($_POST['harga']);
    $tanggal = $conn->real_escape_string($_POST['tanggal']);
    $status = 'pending';

    $stmt = $conn->prepare("INSERT INTO orders (nama, alamat, nomor, paket, harga, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $nama, $alamat, $nomor, $paket, $harga, $tanggal, $status);

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        $stmt->close();
        header("Location: index.php?success=order_saved&order_id=$order_id");
        exit;
    } else {
        header("Location: index.php?error=save_failed");
        exit;
    }
}

// Handle order update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $order_id = intval($_POST['editId']);
    $nama = $conn->real_escape_string($_POST['nama']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $nomor = $conn->real_escape_string($_POST['nomor']);
    $paket = $conn->real_escape_string($_POST['paket']);
    $harga = $conn->real_escape_string($_POST['harga']);
    $tanggal = $conn->real_escape_string($_POST['tanggal']);

    $stmt = $conn->prepare("UPDATE orders SET nama = ?, alamat = ?, nomor = ?, paket = ?, harga = ?, tanggal = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $nama, $alamat, $nomor, $paket, $harga, $tanggal, $order_id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: index.php?success=order_updated&order_id=$order_id");
        exit;
    } else {
        header("Location: index.php?error=update_failed");
        exit;
    }
}

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $order_id = intval($_POST['order_id']);
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: index.php?success=order_deleted&order_id=$order_id");
        exit;
    } else {
        header("Location: index.php?error=delete_failed");
        exit;
    }
}

// Handle order confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_orders') {
    $order_ids = json_decode($_POST['order_ids'], true);
    if (!empty($order_ids)) {
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed' WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);

        if ($stmt->execute()) {
            $stmt->close();
            // Fetch confirmed orders for receipt
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $stmt = $conn->prepare("SELECT nama, alamat, nomor, paket, harga, tanggal FROM orders WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $confirmedOrders = [];
            while ($row = $result->fetch_assoc()) {
                $confirmedOrders[] = $row;
            }
            $stmt->close();
            // Redirect to Receipt.php with confirmed orders
            $pesananData = ['items' => $confirmedOrders];
            $encoded = urlencode(json_encode($pesananData));
            header("Location: Receipt.php?pesanan=$encoded");
            exit;
        } else {
            header("Location: index.php?error=confirm_failed");
            exit;
        }
    }
}

// Fetch order history (only confirmed orders)
$sql = "SELECT * FROM orders WHERE status = 'confirmed' ORDER BY created_at DESC";
$result = $conn->query($sql);

$orderHistory = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orderHistory[] = $row;
    }
}

// Fetch pending orders for detail page
$sql_pending = "SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at DESC";
$result_pending = $conn->query($sql_pending);

$pendingOrders = [];
if ($result_pending->num_rows > 0) {
    while ($row = $result_pending->fetch_assoc()) {
        $pendingOrders[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>IndiHome</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    ::-webkit-scrollbar {
      display: none;
    }
    .loading-spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #dd2c1f;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      animation: spin 1s linear infinite;
      margin: auto;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .nav-active {
      background-color: #b91c1c;
      border-radius: 8px;
      padding: 8px 12px;
      transform: scale(1.05);
      transition: all 0.2s ease;
    }
    .nav-button {
      pointer-events: auto;
      padding: 8px;
      transition: transform 0.2s ease, opacity 0.2s ease;
    }
    .nav-button:hover {
      opacity: 0.8;
      transform: scale(1.1);
    }
    .nav-button:active {
      transform: scale(0.95);
    }
    .page {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.3s ease, transform 0.3s ease;
    }
    .page.active {
      opacity: 1;
      transform: translateY(0);
    }
    .input-icon {
      position: relative;
    }
    .input-icon i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #6b7280;
    }
    .input-icon input {
      padding-left: 40px;
    }
    .avatar-placeholder {
      background: linear-gradient(135deg, #dd2c1f, #b91c1c);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: bold;
      color: white;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<!-- Halaman Home -->
<div id="homePage" class="page active">
  <header class="bg-gradient-to-r from-[#dd2c1f] to-[#b91c1c] text-white p-6 rounded-b-3xl shadow-lg">
    <h1 class="text-3xl font-bold text-center">Selamat Datang, Dika!</h1>
    <p class="text-sm text-center mt-2 opacity-90">Nikmati layanan IndiHome dengan mudah.</p>
  </header>

  <main class="px-4 mt-6 mb-24">
    <h2 class="text-xl font-semibold mb-3 text-gray-900">Rekomendasi Untukmu</h2>
    <div class="flex overflow-x-auto gap-4 pb-4 snap-x snap-mandatory">
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Home1.jpg" alt="Gambar 1" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Home2.webp" alt="Gambar 2" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Home3.webp" alt="Gambar 3" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Home4.webp" alt="Gambar 4" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
    </div>

    <h2 class="text-xl font-semibold mb-3 mt-6 text-gray-900">Promo Spesial</h2>
    <div class="flex overflow-x-auto gap-4 pt-4 pb-4 snap-x snap-mandatory">
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Promo1.webp" alt="Promo 1" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Promo2.webp" alt="Promo 2" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Promo3.jpeg" alt="Promo 3" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
      <div class="flex-shrink-0 w-64 h-40 bg-gray-200 rounded-xl overflow-hidden snap-center">
        <img src="Image/Promo4.jpg" alt="Promo 4" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
      </div>
    </div>
  </main>
</div>

<!-- Halaman List Paket -->
<div id="paketPage" class="page hidden px-4 py-6 mb-24">
  <h2 class="text-2xl font-bold text-[#dd2c1f] mb-6">Pilih Paket Internet</h2>
  <div class="grid gap-4">
    <div class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-shadow cursor-pointer" onclick="pilihPaket('Up to 20 Mbps', 'Rp375.000 /bulan')">
      <h3 class="text-lg font-bold text-gray-800">Up to 20 Mbps</h3>
      <p class="text-sm text-gray-600">3 - 5 Perangkat</p>
      <p class="text-lg font-semibold text-[#dd2c1f] mt-2">Rp375.000 /bulan</p>
      <p class="text-xs text-gray-500 mt-2">109 Channel (91 SD + 18 HD)</p>
      <p class="text-xs text-gray-500">Bebas 300 menit nelpon lokal/interlokal</p>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-shadow cursor-pointer" onclick="pilihPaket('Up to 50 Mbps', 'Rp590.000 /bulan')">
      <h3 class="text-lg font-bold text-gray-800">Up to 50 Mbps</h3>
      <p class="text-sm text-gray-600">10 - 12 Perangkat</p>
      <p class="text-lg font-semibold text-[#dd2c1f] mt-2">Rp590.000 /bulan</p>
      <p class="text-xs text-gray-500 mt-2">109 Channel (91 SD + 18 HD)</p>
      <p class="text-xs text-gray-500">Bebas 300 menit nelpon lokal/interlokal</p>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-shadow cursor-pointer" onclick="pilihPaket('Up to 100 Mbps', 'Rp945.000 /bulan')">
      <h3 class="text-lg font-bold text-gray-800">Up to 100 Mbps</h3>
      <p class="text-sm text-gray-600">12 - 18 Perangkat</p>
      <p class="text-lg font-semibold text-[#dd2c1f] mt-2">Rp945.000 /bulan</p>
      <p class="text-xs text-gray-500 mt-2">109 Channel (91 SD + 18 HD)</p>
      <p class="text-xs text-gray-500">Bebas 300 menit nelpon lokal/interlokal</p>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-shadow cursor-pointer" onclick="pilihPaket('Up to 200 Mbps', 'Rp1.665.000 /bulan')">
      <h3 class="text-lg font-bold text-gray-800">Up to 200 Mbps</h3>
      <p class="text-sm text-gray-600">18 - 25 Perangkat</p>
      <p class="text-lg font-semibold text-[#dd2c1f] mt-2">Rp1.665.000 /bulan</p>
      <p class="text-xs text-gray-500 mt-2">109 Channel (91 SD + 18 HD)</p>
      <p class="text-xs text-gray-500">Bebas 300 menit nelpon lokal/interlokal</p>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-shadow cursor-pointer" onclick="pilihPaket('Up to 300 Mbps', 'Rp2.665.000 /bulan')">
      <h3 class="text-lg font-bold text-gray-800">Up to 300 Mbps</h3>
      <p class="text-sm text-gray-600">25 - 31 Perangkat</p>
      <p class="text-lg font-semibold text-[#dd2c1f] mt-2">Rp2.665.000 /bulan</p>
      <p class="text-xs text-gray-500 mt-2">109 Channel (91 SD + 18 HD)</p>
      <p class="text-xs text-gray-500">Bebas 300 menit nelpon lokal/interlokal</p>
    </div>
  </div>
</div>

<!-- Halaman Form -->
<div id="formPage" class="page hidden px-4 py-6 mb-24">
  <div class="max-w-md mx-auto">
    <h2 class="text-2xl font-bold text-[#dd2c1f] mb-6 text-center">Formulir Pemasangan</h2>
    <div class="text-sm text-gray-600 mb-4 text-center">Langkah 1 dari 2: Informasi Pribadi</div>
    <div class="bg-white p-6 rounded-xl shadow-lg">
      <form id="installForm" method="POST" action="index.php" class="grid gap-5">
        <input type="hidden" name="action" id="formAction" value="save_order" />
        <input type="hidden" id="pesananEditId" name="editId" value="" />
        <input type="hidden" id="paket" name="paket" value="" />
        <input type="hidden" id="harga" name="harga" value="" />
        <input type="hidden" id="tanggal" name="tanggal" value="" />
        <div class="input-icon">
          <i class="fas fa-user"></i>
          <input type="text" id="nama" name="nama" placeholder="Nama Lengkap" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#dd2c1f] transition-shadow" />
          <p id="nama-error" class="text-red-500 text-xs mt-1 hidden">Nama harus diisi</p>
        </div>
        <div class="input-icon">
          <i class="fas fa-map-marker-alt"></i>
          <input type="text" id="alamat" name="alamat" placeholder="Alamat Lengkap" required class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#dd2c1f] transition-shadow" />
          <p id="alamat-error" class="text-red-500 text-xs mt-1 hidden">Alamat harus diisi</p>
        </div>
        <div class="input-icon">
          <i class="fas fa-phone"></i>
          <input type="tel" id="nomor" name="nomor" placeholder="Nomor HP" required pattern="[0-9]{10,13}" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#dd2c1f] transition-shadow" />
          <p id="nomor-error" class="text-red-500 text-xs mt-1 hidden">Nomor HP harus 10-13 digit</p>
        </div>
        <button type="submit" id="submitBtn" class="bg-[#dd2c1f] text-white py-3 rounded-lg font-semibold hover:bg-[#c6281a] transition-colors flex justify-center items-center">Lanjut</button>
      </form>
    </div>
  </div>
</div>

<!-- Halaman Detail Pesanan -->
<div id="detailPesananPage" class="page hidden px-4 py-6 mb-24">
  <h2 class="text-2xl font-bold text-[#dd2c1f] mb-6">Detail Pesanan</h2>
  <div id="pesanan-container" class="grid gap-4 mb-6">
    <?php if (empty($pendingOrders)): ?>
      <p class="text-center text-gray-500">Belum ada pesanan pending.</p>
    <?php else: ?>
      <?php foreach ($pendingOrders as $order): ?>
        <div class="bg-white p-5 rounded-xl shadow-md">
          <p><strong>Nama:</strong> <?php echo htmlspecialchars($order['nama']); ?></p>
          <p><strong>Alamat:</strong> <?php echo htmlspecialchars($order['alamat']); ?></p>
          <p><strong>Nomor HP:</strong> <?php echo htmlspecialchars($order['nomor']); ?></p>
          <hr class="my-3">
          <p><strong>Paket Pilihan:</strong> <?php echo htmlspecialchars($order['paket']); ?></p>
          <p><strong>Harga:</strong> <?php echo htmlspecialchars($order['harga']); ?></p>
          <div class="flex justify-center gap-4 mt-4">
            <button onclick="ubahData(<?php echo $order['id']; ?>)" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-600 transition-colors flex items-center">
              <i class="fas fa-edit mr-2"></i> Ubah
            </button>
            <button onclick="batalkan(<?php echo $order['id']; ?>)" class="bg-red-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-800 transition-colors flex items-center">
              <i class="fas fa-trash mr-2"></i> Batalkan
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <button id="konfirmasi-semua-btn" onclick="konfirmasiSemuaPesanan()" class="bg-green-600 text-white px-4 py-3 rounded-lg font-semibold w-full hover:bg-green-700 transition-colors flex justify-center items-center <?php echo empty($pendingOrders) ? 'hidden' : ''; ?>">
    <i class="fas fa-check-circle mr-2"></i> Konfirmasi Semua Pesanan
  </button>
</div>

<!-- Halaman Riwayat Pesanan -->
<div id="historyPage" class="page hidden px-4 py-6 mb-24">
  <h2 class="text-2xl font-bold text-[#dd2c1f] mb-6">Riwayat Pesanan</h2>
  <div id="history-container" class="grid gap-4">
    <?php if (empty($orderHistory)): ?>
      <p class="text-center text-gray-500">Belum ada riwayat pesanan.</p>
    <?php else: ?>
      <?php foreach ($orderHistory as $order): ?>
        <div class="bg-white p-5 rounded-xl shadow-md">
          <div class="flex justify-between items-start">
            <div>
              <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($order['paket']); ?></h3>
              <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['tanggal']); ?></p>
            </div>
            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full"><?php echo htmlspecialchars($order['status']); ?></span>
          </div>
          <hr class="my-3">
          <p><strong>Nama:</strong> <?php echo htmlspecialchars($order['nama']); ?></p>
          <p><strong>Alamat:</strong> <?php echo htmlspecialchars($order['alamat']); ?></p>
          <p><strong>Nomor HP:</strong> <?php echo htmlspecialchars($order['nomor']); ?></p>
          <p><strong>Harga:</strong> <?php echo htmlspecialchars($order['harga']); ?></p>
          <button onclick="lihatStruk(<?php echo $order['id']; ?>)" class="mt-3 bg-[#dd2c1f] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#c6281a] transition-colors flex items-center w-full justify-center">
            <i class="fas fa-receipt mr-2"></i> Lihat Struk
          </button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Halaman Profile -->
<div id="profilePage" class="page hidden px-4 py-6 mb-24">
  <div class="max-w-md mx-auto">
    <h2 class="text-2xl font-bold text-[#dd2c1f] mb-6 text-center">Profil Pengguna</h2>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
      <div class="bg-gradient-to-r from-[#dd2c1f] to-[#b91c1c] h-32"></div>
      <div class="relative flex justify-center -mt-16">
        <div class="w-24 h-24 rounded-full border-4 border-white shadow-md avatar-placeholder">
          D
        </div>
      </div>
      <div class="p-6 text-center">
        <h3 class="text-xl font-bold text-gray-800">Dika Pratama</h3>
        <p class="text-sm text-gray-500">Pelanggan IndiHome</p>
      </div>
      <div class="px-6 pb-6">
        <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
          <p class="flex items-center text-gray-700 mb-2"><i class="fas fa-envelope mr-3"></i> dika@gmail.com</p>
          <p class="flex items-center text-gray-700 mb-2"><i class="fas fa-phone mr-3"></i> +62 812 3456 7890</p>
          <p class="flex items-center text-gray-700"><i class="fas fa-map-marker-alt mr-3"></i> Jl. Sudirman No. 123, Jakarta</p>
        </div>
        <div class="mt-6 grid gap-4">
          <button onclick="Swal.fire({title: 'Fitur Segera Hadir', text: 'Edit profil akan tersedia di pembaruan berikutnya.', icon: 'info', confirmButtonColor: '#dd2c1f', draggable: true})" class="bg-[#dd2c1f] text-white py-3 rounded-lg font-semibold hover:bg-[#c6281a] transition-colors flex items-center justify-center">
            <i class="fas fa-edit mr-2"></i> Edit Profil
          </button>
          <button onclick="logout()" class="bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors flex items-center justify-center">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bottom Navbar -->
<nav id="navbar" class="bg-gradient-to-r from-[#dd2c1f] to-[#b91c1c] flex justify-around items-center py-4 rounded-t-xl text-white text-sm font-medium fixed bottom-0 w-full shadow-xl z-[2000]">
  <button id="nav-home" class="nav-button flex flex-col items-center gap-1 focus:outline-none" data-page="homePage">
    <i class="fas fa-home text-lg"></i>
    Home
  </button>
  <button id="nav-paket" class="nav-button flex flex-col items-center gap-1 focus:outline-none" data-page="paketPage">
    <i class="fas fa-list text-lg"></i>
    List Paket
  </button>
  <button id="nav-detail" class="nav-button flex flex-col items-center gap-1 focus:outline-none" data-page="detailPesananPage">
    <i class="fas fa-info-circle text-lg"></i>
    Detail
  </button>
  <button id="nav-history" class="nav-button flex flex-col items-center gap-1 focus:outline-none" data-page="historyPage">
    <i class="fas fa-history text-lg"></i>
    Riwayat
  </button>
  <button id="nav-profile" class="nav-button flex flex-col items-center gap-1 focus:outline-none" data-page="profilePage">
    <i class="fas fa-user text-lg"></i>
    Profile
  </button>
</nav>

<script>
  // UI Management
  const UI = {
    showPage(pageId) {
      console.log(`Switching to page: ${pageId}`);
      const pages = ['homePage', 'paketPage', 'formPage', 'detailPesananPage', 'historyPage', 'profilePage'];
      pages.forEach(id => {
        const page = document.getElementById(id);
        if (page) {
          page.classList.add('hidden');
          page.classList.remove('active');
        }
      });
      const targetPage = document.getElementById(pageId);
      if (targetPage) {
        targetPage.classList.remove('hidden');
        setTimeout(() => targetPage.classList.add('active'), 10);
      }

      // Update active navbar button
      const navButtons = document.querySelectorAll('.nav-button');
      navButtons.forEach(btn => {
        btn.classList.remove('nav-active');
        if (btn.getAttribute('data-page') === pageId) {
          btn.classList.add('nav-active');
        }
      });
    },
    showLoading(button) {
      button.innerHTML = '<div class="loading-spinner"></div>';
      button.disabled = true;
    },
    hideLoading(button, originalText) {
      button.innerHTML = originalText;
      button.disabled = false;
    },
    showError(elementId, message) {
      const errorEl = document.getElementById(elementId);
      if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
      }
    },
    hideError(elementId) {
      const errorEl = document.getElementById(elementId);
      if (errorEl) errorEl.classList.add('hidden');
    }
  };

  // Navbar Event Handling
  document.getElementById('navbar').addEventListener('click', (e) => {
    const button = e.target.closest('.nav-button');
    if (button) {
      const pageId = button.getAttribute('data-page');
      if (pageId) {
        console.log(`Navbar button clicked: ${pageId}`);
        e.preventDefault();
        e.stopPropagation();
        UI.showPage(pageId);
      }
    }
  });

  // Data Management
  let daftarPesanan = <?php echo json_encode($pendingOrders); ?>;

  // Form Validation
  function validateForm({ nama, alamat, nomor }) {
    let isValid = true;
    UI.hideError('nama-error');
    UI.hideError('alamat-error');
    UI.hideError('nomor-error');

    if (!nama) {
      UI.showError('nama-error', 'Nama harus diisi');
      isValid = false;
    }
    if (!alamat) {
      UI.showError('alamat-error', 'Alamat harus diisi');
      isValid = false;
    }
    if (!nomor || !/^[0-9]{10,13}$/.test(nomor)) {
      UI.showError('nomor-error', 'Nomor HP harus 10-13 digit');
      isValid = false;
    }
    return isValid;
  }

  // Paket Selection
  function pilihPaket(nama, harga) {
    document.getElementById('pesananEditId').value = '';
    document.getElementById('nama').value = '';
    document.getElementById('alamat').value = '';
    document.getElementById('nomor').value = '';
    document.getElementById('paket').value = nama;
    document.getElementById('harga').value = harga;
    document.getElementById('tanggal').value = new Date().toLocaleString('id-ID');
    document.getElementById('formAction').value = 'save_order';
    UI.hideError('nama-error');
    UI.hideError('alamat-error');
    UI.hideError('nomor-error');
    UI.showPage('formPage');
  }

  // Edit Order
  function ubahData(pesananId) {
    const pesanan = daftarPesanan.find(p => p.id == pesananId);
    if (!pesanan) return;

    document.getElementById('pesananEditId').value = pesananId;
    document.getElementById('nama').value = pesanan.nama;
    document.getElementById('alamat').value = pesanan.alamat;
    document.getElementById('nomor').value = pesanan.nomor;
    document.getElementById('paket').value = pesanan.paket;
    document.getElementById('harga').value = pesanan.harga;
    document.getElementById('tanggal').value = pesanan.tanggal;
    document.getElementById('formAction').value = 'update_order';
    UI.hideError('nama-error');
    UI.hideError('alamat-error');
    UI.hideError('nomor-error');
    UI.showPage('formPage');
  }

  // Form Submission
  document.getElementById('installForm').addEventListener('submit', (e) => {
    const submitBtn = document.getElementById('submitBtn');
    UI.showLoading(submitBtn);

    const formData = {
      nama: document.getElementById('nama').value.trim(),
      alamat: document.getElementById('alamat').value.trim(),
      nomor: document.getElementById('nomor').value.trim()
    };

    if (!validateForm(formData)) {
      e.preventDefault();
      UI.hideLoading(submitBtn, 'Lanjut');
      Swal.fire({
        title: 'Gagal',
        text: 'Harap isi semua field dengan benar',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#dd2c1f',
        draggable: true
      });
    }
  });

  // Display Pending Orders
  function tampilkanSemuaPesanan() {
    const containerEl = document.getElementById('pesanan-container');
    if (!containerEl) return;
    containerEl.innerHTML = '';

    if (daftarPesanan.length === 0) {
      containerEl.innerHTML = '<p class="text-center text-gray-500">Belum ada pesanan pending.</p>';
    } else {
      daftarPesanan.forEach((pesanan) => {
        const pesananEl = document.createElement('div');
        pesananEl.className = 'bg-white p-5 rounded-xl shadow-md';
        pesananEl.id = `pesanan-item-${pesanan.id}`;
        pesananEl.innerHTML = `
          <p><strong>Nama:</strong> <span>${pesanan.nama}</span></p>
          <p><strong>Alamat:</strong> <span>${pesanan.alamat}</span></p>
          <p><strong>Nomor HP:</strong> <span>${pesanan.nomor}</span></p>
          <hr class="my-3">
          <p><strong>Paket Pilihan:</strong> <span>${pesanan.paket}</span></p>
          <p><strong>Harga:</strong> <span>${pesanan.harga}</span></p>
          <div class="flex justify-center gap-4 mt-4">
            <button onclick="ubahData(${pesanan.id})" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-600 transition-colors flex items-center">
              <i class="fas fa-edit mr-2"></i> Ubah
            </button>
            <button onclick="batalkan(${pesanan.id})" class="bg-red-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-800 transition-colors flex items-center">
              <i class="fas fa-trash mr-2"></i> Batalkan
            </button>
          </div>
        `;
        containerEl.appendChild(pesananEl);
      });
    }

    const konfirmasiBtn = document.getElementById('konfirmasi-semua-btn');
    if (konfirmasiBtn) konfirmasiBtn.classList.toggle('hidden', daftarPesanan.length === 0);
  }

  // Cancel Order
  async function batalkan(pesananId) {
    const pesanan = daftarPesanan.find(p => p.id == pesananId);
    if (!pesanan) return;

    const result = await Swal.fire({
      title: 'Konfirmasi',
      text: 'Apakah Anda yakin ingin membatalkan pesanan ini?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, Batalkan',
      cancelButtonText: 'Tidak',
      confirmButtonColor: '#dd2c1f',
      cancelButtonColor: '#6b7280',
      draggable: true
    });

    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'index.php';
      form.innerHTML = `
        <input type="hidden" name="action" value="delete_order" />
        <input type="hidden" name="order_id" value="${pesananId}" />
      `;
      document.body.appendChild(form);
      form.submit();
    }
  }

  // Confirm All Orders
  async function konfirmasiSemuaPesanan() {
    if (daftarPesanan.length === 0) {
      Swal.fire({
        title: 'Gagal',
        text: 'Tidak ada pesanan untuk dikonfirmasi',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#dd2c1f',
        draggable: true
      });
      return;
    }

    const result = await Swal.fire({
      title: 'Konfirmasi',
      text: 'Apakah Anda yakin ingin mengkonfirmasi semua pesanan?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, Konfirmasi',
      cancelButtonText: 'Tidak',
      confirmButtonColor: '#dd2c1f',
      cancelButtonColor: '#6b7280',
      draggable: true
    });

    if (result.isConfirmed) {
      const orderIds = daftarPesanan.map(p => p.id);
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'index.php';
      form.innerHTML = `
        <input type="hidden" name="action" value="confirm_orders" />
        <input type="hidden" name="order_ids" value='${JSON.stringify(orderIds)}' />
      `;
      document.body.appendChild(form);
      form.submit();
    }
  }

  // View Receipt
  function lihatStruk(orderId) {
    const pesanan = <?php echo json_encode($orderHistory); ?>.find(p => p.id == orderId);
    if (!pesanan) {
      Swal.fire({
        title: 'Gagal',
        text: 'Pesanan tidak ditemukan',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#dd2c1f',
        draggable: true
      });
      return;
    }

    const pesananData = {
      items: [{
        nama: pesanan.nama,
        alamat: pesanan.alamat,
        nomor: pesanan.nomor,
        paket: pesanan.paket,
        harga: pesanan.harga,
        tanggal: pesanan.tanggal
      }]
    };
    const encoded = encodeURIComponent(JSON.stringify(pesananData));
    window.location.href = `Receipt.php?pesanan=${encoded}`;
  }

  // Logout
  async function logout() {
    const result = await Swal.fire({
      title: 'Konfirmasi',
      text: 'Apakah Anda yakin ingin logout?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, Logout',
      cancelButtonText: 'Tidak',
      confirmButtonColor: '#dd2c1f',
      cancelButtonColor: '#6b7280',
      draggable: true
    });

    if (result.isConfirmed) {
      window.location.href = "Login.php";
    }
  }

  // Initialize
  document.addEventListener('DOMContentLoaded', () => {
    UI.showPage('homePage');
    tampilkanSemuaPesanan();

    // Handle success/error messages from PHP
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
      const success = urlParams.get('success');
      const orderId = urlParams.get('order_id');
      if (success === 'order_saved') {
        const pesanan = {
          id: parseInt(orderId),
          nama: '<?php echo isset($_POST['nama']) ? addslashes($_POST['nama']) : ''; ?>',
          alamat: '<?php echo isset($_POST['alamat']) ? addslashes($_POST['alamat']) : ''; ?>',
          nomor: '<?php echo isset($_POST['nomor']) ? addslashes($_POST['nomor']) : ''; ?>',
          paket: '<?php echo isset($_POST['paket']) ? addslashes($_POST['paket']) : ''; ?>',
          harga: '<?php echo isset($_POST['harga']) ? addslashes($_POST['harga']) : ''; ?>',
          tanggal: '<?php echo isset($_POST['tanggal']) ? addslashes($_POST['tanggal']) : ''; ?>',
          status: 'pending'
        };
        if (pesanan.nama && pesanan.alamat && pesanan.nomor) {
          daftarPesanan.push(pesanan);
          tampilkanSemuaPesanan();
        }
        Swal.fire({
          title: 'Berhasil',
          text: 'Pesanan berhasil ditambahkan',
          icon: 'success',
          confirmButtonText: 'OK',
          confirmButtonColor: '#dd2c1f',
          draggable: true
        }).then(() => {
          UI.showPage('detailPesananPage');
          window.history.replaceState({}, document.title, 'index.php');
        });
      } else if (success === 'order_updated') {
        const pesanan = {
          id: parseInt(orderId),
          nama: '<?php echo isset($_POST['nama']) ? addslashes($_POST['nama']) : ''; ?>',
          alamat: '<?php echo isset($_POST['alamat']) ? addslashes($_POST['alamat']) : ''; ?>',
          nomor: '<?php echo isset($_POST['nomor']) ? addslashes($_POST['nomor']) : ''; ?>',
          paket: '<?php echo isset($_POST['paket']) ? addslashes($_POST['paket']) : ''; ?>',
          harga: '<?php echo isset($_POST['harga']) ? addslashes($_POST['harga']) : ''; ?>',
          tanggal: '<?php echo isset($_POST['tanggal']) ? addslashes($_POST['tanggal']) : ''; ?>',
          status: 'pending'
        };
        if (pesanan.nama && pesanan.alamat && pesanan.nomor) {
          const index = daftarPesanan.findIndex(p => p.id == orderId);
          if (index !== -1) {
            daftarPesanan[index] = pesanan;
          } else {
            daftarPesanan.push(pesanan);
          }
          tampilkanSemuaPesanan();
        }
        Swal.fire({
          title: 'Berhasil',
          text: 'Pesanan berhasil diperbarui',
          icon: 'success',
          confirmButtonText: 'OK',
          confirmButtonColor: '#dd2c1f',
          draggable: true
        }).then(() => {
          UI.showPage('detailPesananPage');
          window.history.replaceState({}, document.title, 'index.php');
        });
      } else if (success === 'order_deleted') {
        daftarPesanan = daftarPesanan.filter(p => p.id != orderId);
        tampilkanSemuaPesanan();
        Swal.fire({
          title: 'Berhasil',
          text: 'Pesanan berhasil dibatalkan',
          icon: 'success',
          confirmButtonText: 'OK',
          confirmButtonColor: '#dd2c1f',
          draggable: true
        }).then(() => {
          UI.showPage('detailPesananPage');
          window.history.replaceState({}, document.title, 'index.php');
        });
      }
    } else if (urlParams.has('error')) {
      const error = urlParams.get('error');
      Swal.fire({
        title: 'Gagal',
        text: error === 'save_failed' ? 'Gagal menyimpan pesanan' : 
              error === 'update_failed' ? 'Gagal memperbarui pesanan' : 
              error === 'confirm_failed' ? 'Gagal mengkonfirmasi pesanan' : 
              'Gagal membatalkan pesanan',
        icon: 'error',
        confirmButtonText: 'OK',
        confirmButtonColor: '#dd2c1f',
        draggable: true
      }).then(() => {
        window.history.replaceState({}, document.title, 'index.php');
      });
    }

    // Real-time form validation
    ['nama', 'alamat', 'nomor'].forEach(id => {
      const input = document.getElementById(id);
      if (input) {
        input.addEventListener('input', () => {
          const formData = {
            nama: document.getElementById('nama').value.trim(),
            alamat: document.getElementById('alamat').value.trim(),
            nomor: document.getElementById('nomor').value.trim()
          };
          validateForm(formData);
        });
      }
    });
  });
</script>

</body>
</html>