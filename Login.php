<?php
$conn = new mysqli("localhost", "root", "", "project_indihome");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $data = json_decode(file_get_contents("php://input"), true);
  if (isset($data["action"])) {
    if ($data["action"] === "signup") {
      $name = $data["name"];
      $email = $data["email"];
      $password = password_hash($data["password"], PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO Login (name, email, password) VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $name, $email, $password);
      echo $stmt->execute() ? "success" : "error";
      exit;
    }
    if ($data["action"] === "login") {
      $email = $data["email"];
      $password = $data["password"];
      $stmt = $conn->prepare("SELECT * FROM Login WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $result = $stmt->get_result();
      $user = $result->fetch_assoc();
    
      if ($user && password_verify($password, $user["password"])) {
        echo "success";
      } else {
        echo "wrong_password";
      }
      exit;
    }
  }
}
?>

<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>IndiHome</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
    rel="stylesheet" />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap"
    rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      font-family: "Inter", sans-serif;
    }
  </style>
</head>

<body
  class="bg-gray-100 flex justify-center items-center min-h-screen p-4">
  <div class="w-full max-w-md bg-white rounded-3xl shadow-md overflow-hidden">
    <div class="bg-white p-6 flex justify-center items-center gap-2 rounded-t-3xl">
      <h1 class="text-white text-2xl font-bold flex items-center gap-1">
        <img src="Image/Logo-Indihome.png" alt="" class="size-svh">
      </h1>
    </div>
    <div class="p-6" id="form-container">
      <!-- Login Form -->
      <div id="login-form" class="flex flex-col gap-4">
        <h2 class="text-[#d30000] text-3xl font-extrabold text-center">Login</h2>
        <input
          id="login-email"
          class="border border-gray-300 rounded-lg px-4 py-3 text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#dd3b2a]"
          placeholder="Email"
          type="email" />
        <input
          id="login-password"
          class="border border-gray-300 rounded-lg px-4 py-3 text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#dd3b2a]"
          placeholder="Password"
          type="password" />
        <button
          id="login-btn"
          class="bg-[#dd3b2a] text-white font-semibold rounded-lg py-3 hover:bg-[#c63626] transition-colors">
          Login
        </button>
        <p class="text-center text-base mt-6">
          Don't have an account?
          <a href="#" class="text-[#dd3b2a] font-semibold" onclick="toggleForm()">Sign Up</a>
        </p>
      </div>

      <!-- Sign Up Form -->
      <div id="signup-form" class="flex flex-col gap-4 hidden">
        <h2 class="text-[#d30000] text-3xl font-extrabold text-center">Sign Up</h2>
        <input
          id="signup-name"
          class="border border-gray-300 rounded-lg px-4 py-3 text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#dd3b2a]"
          placeholder="Name"
          type="text" />
        <input
          id="signup-email"
          class="border border-gray-300 rounded-lg px-4 py-3 text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#dd3b2a]"
          placeholder="Email"
          type="email" />
        <input
          id="signup-password"
          class="border border-gray-300 rounded-lg px-4 py-3 text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#dd3b2a]"
          placeholder="Password"
          type="password" />
        <button
          id="signup-btn"
          class="bg-[#dd3b2a] text-white font-semibold rounded-lg py-3 hover:bg-[#c63626] transition-colors">
          Sign Up
        </button>
        <p class="text-center text-base mt-6">
          Already have an account?
          <a href="#" class="text-[#dd3b2a] font-semibold" onclick="toggleForm()">Login</a>
        </p>
      </div>
    </div>
  </div>

  <script>
    function toggleForm() {
      document.getElementById("login-form").classList.toggle("hidden");
      document.getElementById("signup-form").classList.toggle("hidden");
    }

    document.addEventListener("DOMContentLoaded", () => {
      const signupBtn = document.getElementById("signup-btn");
      const loginBtn = document.getElementById("login-btn");

      signupBtn?.addEventListener("click", async (e) => {
        e.preventDefault();
        const name = document.getElementById("signup-name").value.trim();
        const email = document.getElementById("signup-email").value.trim();
        const password = document.getElementById("signup-password").value.trim();

        if (!name || !email || !password) {
          Swal.fire({
            title: "Error",
            text: "Isi semua data dulu!",
            icon: "error",
            confirmButtonText: "OK",
            draggable: true,
          });
          return;
        }

        const res = await fetch("", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "signup", name, email, password }),
        });

        const result = await res.text();
        if (result === "success") {
          Swal.fire({
            title: "Sign up berhasil",
            icon: "success",
            draggable: true,
            confirmButtonText: "OK",
          }).then((result) => {
            if (result.isConfirmed) {
              document.getElementById("signup-name").value = "";
              document.getElementById("signup-email").value = "";
              document.getElementById("signup-password").value = "";
              toggleForm();
            }
          });
        } else {
          Swal.fire({
            title: "Gagal",
            text: "Gagal sign up: " + result,
            icon: "error",
            confirmButtonText: "OK",
            draggable: true,
          });
        }
      });

      loginBtn?.addEventListener("click", async (e) => {
        e.preventDefault();
        const email = document.getElementById("login-email").value.trim();
        const password = document.getElementById("login-password").value.trim();

        if (!email || !password) {
          Swal.fire({
            title: "Error",
            text: "Email dan password harus diisi!",
            icon: "error",
            confirmButtonText: "OK",
            draggable: true,
          });
          return;
        }

        const res = await fetch("", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ action: "login", email, password }),
        });

        const result = await res.text();
        if (result === "success") {
          Swal.fire({
            title: "Login berhasil",
            icon: "success",
            draggable: true,
            confirmButtonText: "OK",
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "index.php";
            }
          });
        } else if (result === "wrong_password") {
          Swal.fire({
            title: "Error",
            text: "Email atau Password salah!",
            icon: "error",
            confirmButtonText: "OK",
            draggable: true,
          });
        } else {
          Swal.fire({
            title: "Gagal",
            text: "Login gagal: " + result,
            icon: "error",
            confirmButtonText: "OK",
            draggable: true,
          });
        }
      });
    });
  </script>
</body>
</html>