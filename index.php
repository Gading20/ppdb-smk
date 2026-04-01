<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Web Absensi</title>
    <link rel="icon" href="../assets/default/logosmk.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(160deg, #fdf2f8 0%, #ede9fe 35%, #e0f2fe 65%, #ecfdf5 100%);
            color: #1e293b;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: -25%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(244, 114, 182, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -20%;
            right: -5%;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(52, 211, 153, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .container {
            text-align: center;
            position: relative;
            z-index: 1;
            padding: 3rem;
        }

        .container h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #e11d48, #7c3aed, #2563eb, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .container p {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 2.5rem;
        }

        .links {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .links a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .links a i {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.85);
        }

        .links a.admin {
            background: linear-gradient(135deg, #e11d48, #f43f5e);
        }

        .links a.siswa {
            background: linear-gradient(135deg, #7c3aed, #a78bfa);
        }

        .links a.kepsek {
            background: linear-gradient(135deg, #2563eb, #60a5fa);
        }

        .links a.wali {
            background: linear-gradient(135deg, #059669, #34d399);
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Web Absensi</h1>
        <p>Silakan pilih portal yang ingin Anda akses.</p>
        <div class="links">
            <a href="admin/" class="admin"><i class="fa-solid fa-shield-halved"></i> Admin</a>
            <a href="siswa/" class="siswa"><i class="fa-solid fa-user-graduate"></i> Siswa</a>
            <a href="kepsek/" class="kepsek"><i class="fa-solid fa-building-columns"></i> Kepala Sekolah</a>
            <a href="wali_kelas/" class="wali"><i class="fa-solid fa-chalkboard-user"></i> Wali Kelas</a>
        </div>
    </div>
</body>

</html>