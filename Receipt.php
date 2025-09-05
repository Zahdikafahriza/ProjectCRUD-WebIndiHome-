<?php
// Database connection (kept for consistency, though unused)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project_indihome";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// No database insertion; orders are already saved in index.php
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Struk Pembayaran</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Arial', sans-serif;
    }
    #strukContainer {
      transition: all 0.3s ease;
    }
    .lunas-badge {
      background-color: #10b981;
      color: white;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .divider {
      border-top: 1px dashed #d1d5db;
    }
    button {
      transition: transform 0.2s ease, background-color 0.2s ease;
    }
    button:hover {
      transform: scale(1.05);
    }
    button:active {
      transform: scale(0.95);
    }
    @media print {
      body {
        background: white;
        color: black;
        margin: 0;
      }
      #strukContainer {
        box-shadow: none;
        border: 1px solid black;
        margin: 0 auto;
        padding: 16px;
        width: 300px;
      }
      .no-print {
        display: none;
      }
      .lunas-badge {
        background-color: transparent;
        border: 1px solid black;
        color: black;
      }
      .divider {
        border-top: 1px dashed black;
      }
      .bg-white, .border-gray-300, .shadow-lg {
        background: white !important;
        border: none !important;
        box-shadow: none !important;
      }
    }
  </style>
</head>
<body class="bg-gray-100 text-gray-800 p-6">
  <div class="max-w-md mx-auto">
    <div id="strukContainer" class="bg-white border border-gray-300 p-6 rounded-xl shadow-lg">
      <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-[#dd2c1f]">Struk Pembayaran IndiHome</h1>
        <p class="text-sm text-gray-500 mt-1">Terima kasih telah memilih IndiHome</p>
      </div>
      
      <div id="strukDetail" class="text-sm space-y-4">
        <!-- Isi struk muncul di sini -->
      </div>
      
      <div class="mt-6 text-center border-t pt-4">
        <p class="text-sm text-gray-600">Silakan simpan struk ini sebagai bukti pembayaran</p>
        <p class="text-xs mt-2">Kode Transaksi: <span id="kodeTransaksi" class="font-semibold"></span></p>
      </div>
    </div>
    <div class="flex justify-center mt-6 no-print">
      <button onclick="window.location.href='index.php'" class="bg-gray-500 text-white px-4 py-2 rounded-lg mr-4 flex items-center hover:bg-gray-600">
        <i class="fas fa-arrow-left mr-2"></i> Kembali
      </button>
      <button onclick="window.print()" class="bg-gradient-to-r from-[#dd2c1f] to-[#b91c1c] text-white px-4 py-2 rounded-lg flex items-center hover:bg-[#c6281a]">
        <i class="fas fa-print mr-2"></i> Cetak Struk
      </button>
    </div>
  </div>

  <script>
    const urlParams = new URLSearchParams(window.location.search);
    const pesananStr = urlParams.get("pesanan");
    const transactionCode = 'TRX-' + Math.random().toString(36).substring(2, 10).toUpperCase();
    
    document.getElementById("kodeTransaksi").textContent = transactionCode;

    if (pesananStr) {
      const pesanan = JSON.parse(decodeURIComponent(pesananStr));
      let strukHTML = '';
      
      // Check if there are multiple items
      if (pesanan.items && pesanan.items.length > 0) {
        // Display each order item
        pesanan.items.forEach((item, index) => {
          strukHTML += `
            <div class="${index > 0 ? 'mt-4 pt-4 divider' : ''}">
              <p><strong class="font-semibold">Nama:</strong> ${item.nama}</p>
              <p><strong class="font-semibold">Alamat:</strong> ${item.alamat}</p>
              <p><strong class="font-semibold">Nomor HP:</strong> ${item.nomor}</p>
              <p><strong class="font-semibold">Paket:</strong> ${item.paket}</p>
              <p><strong class="font-semibold">Harga:</strong> ${item.harga}</p>
              <p><strong class="font-semibold">Tanggal:</strong> ${item.tanggal}</p>
            </div>
          `;
        });
        
        // Calculate total if multiple items
        if (pesanan.items.length > 1) {
          let totalPrice = 0;
          pesanan.items.forEach(item => {
            const price = parseFloat(item.harga.replace(/[^0-9]/g, ''));
            if (!isNaN(price)) {
              totalPrice += price;
            }
          });
          
          strukHTML += `
            <div class="mt-4 pt-4 divider">
              <p class="font-bold text-base">Total Pesanan (${pesanan.items.length} item): Rp${totalPrice.toLocaleString('id-ID')}</p>
            </div>
          `;
        }
        
      } else {
        // Handle single order
        strukHTML = `
          <p><strong class="font-semibold">Nama:</strong> ${pesanan.nama}</p>
          <p><strong class="font-semibold">Alamat:</strong> ${pesanan.alamat}</p>
          <p><strong class="font-semibold">Nomor HP:</strong> ${pesanan.nomor}</p>
          <p><strong class="font-semibold">Paket:</strong> ${pesanan.paket}</p>
          <p><strong class="font-semibold">Harga:</strong> ${pesanan.harga}</p>
          <p><strong class="font-semibold">Tanggal:</strong> ${pesanan.tanggal}</p>
        `;
      }
      
      strukHTML += `
        <div class="my-4 py-2 divider text-center">
          <p class="font-semibold">Status Pembayaran: <span class="lunas-badge">LUNAS</span></p>
        </div>
        <p class="text-center font-semibold text-gray-700">Terima kasih telah menggunakan layanan IndiHome!</p>
      `;
      
      document.getElementById("strukDetail").innerHTML = strukHTML;
    } else {
      document.getElementById("strukDetail").textContent = "Data tidak ditemukan.";
    }
  </script>
</body>
</html>