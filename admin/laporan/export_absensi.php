<?php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// ──────────────────────────────────────────────
// PARAMETER FILTER
// ──────────────────────────────────────────────
$default_start_date = date('Y-m-01');
$default_end_date   = date('Y-m-t');

$start_date      = $_GET['start_date']      ?? $default_start_date;
$end_date        = $_GET['end_date']        ?? $default_end_date;
$kelas           = $_GET['kelas']           ?? '';
$jurusan         = $_GET['jurusan']         ?? '';
$siswa_id        = $_GET['siswa_id']        ?? '';
$status          = $_GET['status']          ?? '';
$approval_status = $_GET['approval_status'] ?? '';
$format          = $_GET['format']          ?? 'pdf';

// ── BASE WHERE ─────────────────────────────────────────────────────────────
$base_where  = "FROM absensi a
                JOIN siswa s ON a.siswa_id = s.id
                WHERE a.tanggal BETWEEN :start_date AND :end_date";
$base_params = ['start_date' => $start_date, 'end_date' => $end_date];

if ($kelas) {
    $base_where .= " AND s.kelas = :kelas";
    $base_params['kelas'] = $kelas;
}
if ($jurusan) {
    $base_where .= " AND s.jurusan = :jurusan";
    $base_params['jurusan'] = $jurusan;
}
if ($siswa_id) {
    $base_where .= " AND a.siswa_id = :siswa_id";
    $base_params['siswa_id'] = $siswa_id;
}
if ($status) {
    $base_where .= " AND a.status = :status";
    $base_params['status'] = $status;
}
if ($approval_status) {
    $base_where .= " AND a.approval_status = :approval_status";
    $base_params['approval_status'] = $approval_status;
} else {
    $base_where .= " AND a.approval_status = 'Approved'";
}

// ── 1. STATUS COUNTS ──────────────────────────────────────────────────────
$sc_stmt = $conn->prepare(
    "SELECT a.status, COUNT(*) as count $base_where GROUP BY a.status"
);
$sc_stmt->execute($base_params);
$status_counts = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Terlambat' => 0, 'Alpha' => 0];
foreach ($sc_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = (int)$row['count'];
    }
}
$total_absensi = array_sum($status_counts);

// ── 2. APPROVAL COUNTS ────────────────────────────────────────────────────
$apv_sql    = "FROM absensi a JOIN siswa s ON a.siswa_id = s.id
               WHERE a.tanggal BETWEEN :start_date AND :end_date";
$apv_params = ['start_date' => $start_date, 'end_date' => $end_date];
if ($kelas) {
    $apv_sql .= " AND s.kelas = :kelas";
    $apv_params['kelas'] = $kelas;
}
if ($jurusan) {
    $apv_sql .= " AND s.jurusan = :jurusan";
    $apv_params['jurusan'] = $jurusan;
}
if ($siswa_id) {
    $apv_sql .= " AND a.siswa_id = :siswa_id";
    $apv_params['siswa_id'] = $siswa_id;
}
if ($status) {
    $apv_sql .= " AND a.status = :status";
    $apv_params['status'] = $status;
}

$apv_stmt = $conn->prepare(
    "SELECT a.approval_status, COUNT(*) as count $apv_sql GROUP BY a.approval_status"
);
$apv_stmt->execute($apv_params);
$approval_counts = ['Approved' => 0, 'Pending' => 0, 'Rejected' => 0];
foreach ($apv_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($approval_counts[$row['approval_status']])) {
        $approval_counts[$row['approval_status']] = (int)$row['count'];
    }
}
$total_apv = array_sum($approval_counts);

// ── 3. REKAP TOP SISWA ────────────────────────────────────────────────────
$rekap_stmt = $conn->prepare(
    "SELECT s.nama_lengkap, s.nis, s.kelas, s.jurusan,
            COUNT(CASE WHEN a.status = 'Hadir'     THEN 1 END) as hadir,
            COUNT(CASE WHEN a.status = 'Sakit'     THEN 1 END) as sakit,
            COUNT(CASE WHEN a.status = 'Izin'      THEN 1 END) as izin,
            COUNT(CASE WHEN a.status = 'Terlambat' THEN 1 END) as terlambat,
            COUNT(CASE WHEN a.status = 'Alpha'     THEN 1 END) as alpha,
            COUNT(*) as total
     $base_where
     GROUP BY s.id, s.nama_lengkap, s.nis, s.kelas, s.jurusan
     ORDER BY alpha DESC, terlambat DESC
     LIMIT 10"
);
$rekap_stmt->execute($base_params);
$top_rekap = $rekap_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 4. SUBTITLE ───────────────────────────────────────────────────────────
$subtitle = "Periode: " . date('d/m/Y', strtotime($start_date))
    . " – " . date('d/m/Y', strtotime($end_date));
if ($kelas)           $subtitle .= " | Kelas: $kelas";
if ($jurusan)         $subtitle .= " | Jurusan: $jurusan";
if ($status)          $subtitle .= " | Status: $status";
if ($approval_status) $subtitle .= " | Approval: $approval_status";
if ($siswa_id) {
    $stu = $conn->prepare("SELECT nama_lengkap, nis FROM siswa WHERE id = :id");
    $stu->execute(['id' => $siswa_id]);
    $student = $stu->fetch(PDO::FETCH_ASSOC);
    if ($student) $subtitle .= " | Siswa: {$student['nama_lengkap']} ({$student['nis']})";
}

// ── Warna badge ───────────────────────────────────────────────────────────
$statusStyle = [
    'Hadir'     => ['bg' => '#D1FAE5', 'text' => '#065F46', 'border' => '#10B981'],
    'Sakit'     => ['bg' => '#FEF9C3', 'text' => '#854D0E', 'border' => '#EAB308'],
    'Izin'      => ['bg' => '#EDE9FE', 'text' => '#5B21B6', 'border' => '#8B5CF6'],
    'Terlambat' => ['bg' => '#FFEDD5', 'text' => '#9A3412', 'border' => '#F97316'],
    'Alpha'     => ['bg' => '#FEE2E2', 'text' => '#991B1B', 'border' => '#EF4444'],
];
$approvalStyle = [
    'Approved' => ['bg' => '#D1FAE5', 'text' => '#065F46'],
    'Pending'  => ['bg' => '#FEF9C3', 'text' => '#854D0E'],
    'Rejected' => ['bg' => '#FEE2E2', 'text' => '#991B1B'],
];

// ================================================================
// EXCEL EXPORT  ← FIX: tambahkan stream + exit di sini
// ================================================================
if ($format === 'excel') {
    // Bersihkan buffer output agar tidak ada karakter sebelum header
    if (ob_get_level()) {
        ob_end_clean();
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Absensi');
    $spreadsheet->getProperties()
        ->setCreator('SMK NURUL ULUM')
        ->setTitle('Laporan Absensi');

    $green    = '059669';
    $white    = 'FFFFFF';
    $greenHdr = [
        'font'      => ['bold' => true, 'color' => ['rgb' => $white], 'size' => 11],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $green],
        ],
    ];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];

    // ── Header blok ──
    foreach (['A1:K1', 'A2:K2', 'A3:K3', 'A4:K4'] as $m) {
        $sheet->mergeCells($m);
    }
    $sheet->setCellValue('A1', 'LAPORAN ABSENSI SISWA');
    $sheet->setCellValue('A2', 'SMK NURUL ULUM');
    $sheet->setCellValue('A3', $subtitle);
    $sheet->setCellValue('A4', 'Diekspor pada: ' . date('d/m/Y H:i'));

    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 16],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
    ]);
    $sheet->getStyle('A2')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 13],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('A3')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('A4')->applyFromArray([
        'font'      => ['italic' => true, 'size' => 9],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(24);
    $sheet->getRowDimension(5)->setRowHeight(8);

    // ── Ringkasan ──
    $sheet->mergeCells('A6:K6');
    $sheet->setCellValue('A6', 'RINGKASAN KEHADIRAN');
    $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

    foreach (['B8' => 'Status', 'C8' => 'Jumlah', 'D8' => 'Persentase'] as $c => $v) {
        $sheet->setCellValue($c, $v);
    }
    $sheet->getStyle('B8:D8')->applyFromArray($greenHdr);

    foreach (['F8' => 'Approval', 'G8' => 'Jumlah', 'H8' => 'Persentase'] as $c => $v) {
        $sheet->setCellValue($c, $v);
    }
    $sheet->getStyle('F8:H8')->applyFromArray($greenHdr);
    $sheet->getRowDimension(8)->setRowHeight(18);

    $r = 9;
    foreach (['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $st) {
        $cnt = $status_counts[$st];
        $pct = $total_absensi > 0 ? round($cnt / $total_absensi * 100, 1) . '%' : '0%';
        $sheet->setCellValue("B$r", $st);
        $sheet->setCellValue("C$r", $cnt);
        $sheet->setCellValue("D$r", $pct);
        $sheet->getStyle("C$r:D$r")->applyFromArray($center);
        $r++;
    }
    $sheet->setCellValue("B$r", 'TOTAL');
    $sheet->setCellValue("C$r", $total_absensi);
    $sheet->setCellValue("D$r", '100%');
    $sheet->getStyle("B$r:D$r")->applyFromArray($greenHdr);

    $r2 = 9;
    foreach (['Approved', 'Pending', 'Rejected'] as $apv) {
        $cnt = $approval_counts[$apv];
        $pct = $total_apv > 0 ? round($cnt / $total_apv * 100, 1) . '%' : '0%';
        $sheet->setCellValue("F$r2", $apv);
        $sheet->setCellValue("G$r2", $cnt);
        $sheet->setCellValue("H$r2", $pct);
        $sheet->getStyle("G$r2:H$r2")->applyFromArray($center);
        $r2++;
    }
    $sheet->setCellValue("F$r2", 'TOTAL');
    $sheet->setCellValue("G$r2", $total_apv);
    $sheet->setCellValue("H$r2", '100%');
    $sheet->getStyle("F$r2:H$r2")->applyFromArray($greenHdr);

    // ── Rekap Top Siswa ──
    $rK = max($r, $r2) + 2;
    if (!empty($top_rekap)) {
        $sheet->mergeCells("A$rK:J$rK");
        $sheet->setCellValue("A$rK", 'REKAP KEHADIRAN SISWA (TOP 10 ALPHA & TERLAMBAT)');
        $sheet->getStyle("A$rK")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
        $rK++;

        $hdrs = [
            'A' => 'No',
            'B' => 'NIS',
            'C' => 'Nama Siswa',
            'D' => 'Kelas',
            'E' => 'Hadir',
            'F' => 'Sakit',
            'G' => 'Izin',
            'H' => 'Terlambat',
            'I' => 'Alpha',
            'J' => 'Total',
        ];
        foreach ($hdrs as $col => $hdr) {
            $sheet->setCellValue("$col$rK", $hdr);
        }
        $sheet->getStyle("A$rK:J$rK")->applyFromArray($greenHdr);
        $sheet->getRowDimension($rK)->setRowHeight(18);
        $rK++;

        foreach ($top_rekap as $idx => $tk) {
            $sheet->setCellValue("A$rK", $idx + 1);
            $sheet->setCellValue("B$rK", $tk['nis']);
            $sheet->setCellValue("C$rK", $tk['nama_lengkap']);
            $sheet->setCellValue("D$rK", $tk['kelas'] . ' ' . $tk['jurusan']);
            $sheet->setCellValue("E$rK", $tk['hadir']);
            $sheet->setCellValue("F$rK", $tk['sakit']);
            $sheet->setCellValue("G$rK", $tk['izin']);
            $sheet->setCellValue("H$rK", $tk['terlambat']);
            $sheet->setCellValue("I$rK", $tk['alpha']);
            $sheet->setCellValue("J$rK", $tk['total']);
            $sheet->getStyle("A$rK")->applyFromArray($center);
            $sheet->getStyle("D$rK:J$rK")->applyFromArray($center);
            if ($idx % 2 === 0) {
                $sheet->getStyle("A$rK:J$rK")->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'ECFDF5'],
                    ],
                ]);
            }
            $rK++;
        }
    }

    // ── Lebar kolom otomatis ──
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // ── FIX: Stream ke browser lalu exit ──
    $filename = "Laporan_Absensi_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit(); // ← WAJIB: hentikan eksekusi setelah Excel dikirim
}

// ================================================================
// PDF EXPORT
// ================================================================

// Bersihkan buffer sebelum generate PDF
if (ob_get_level()) {
    ob_end_clean();
}

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
// FIX: Hapus chroot agar tidak error di shared hosting
// $options->set('chroot', $_SERVER['DOCUMENT_ROOT']);

$dompdf = new Dompdf($options);

// ── FIX: Cari logo dengan path yang lebih robust ──
$logoData   = '';
$scriptDir  = dirname(__FILE__); // direktori file ini
$logoPaths  = [
    $scriptDir . '/../../assets/default/logosmk.png',
    dirname(dirname($scriptDir)) . '/assets/default/logosmk.png',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/default/logosmk.png',
];
foreach ($logoPaths as $p) {
    $realPath = realpath($p);
    if ($realPath && file_exists($realPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($realPath));
        break;
    }
}

// ── Hitung metrik PDF ──
$hadir_terlambat  = $status_counts['Hadir'] + $status_counts['Terlambat'];
$tidak_hadir      = $status_counts['Alpha'] + $status_counts['Sakit'] + $status_counts['Izin'];
$kehadiran_pct    = $total_absensi > 0 ? round($hadir_terlambat / $total_absensi * 100, 1) : 0;
$tdkhadir_pct     = $total_absensi > 0 ? round($tidak_hadir     / $total_absensi * 100, 1) : 0;
$uniq_stmt        = $conn->prepare("SELECT COUNT(DISTINCT a.siswa_id) $base_where");
$uniq_stmt->execute($base_params);
$total_siswa_unik = $uniq_stmt->fetchColumn();

ob_start();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi</title>
    <style>
        @page {
            margin: 18mm 14mm 20mm 14mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1F2937;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        .header {
            border-bottom: 3px solid #059669;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .hdr-tbl {
            width: 100%;
            border-collapse: collapse;
        }

        .hdr-tbl td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .hdr-logo {
            width: 65px;
        }

        .hdr-logo img {
            height: 52px;
        }

        .hdr-text {
            text-align: center;
        }

        .hdr-text h1 {
            font-size: 17px;
            font-weight: bold;
            color: #059669;
            margin: 0 0 3px;
        }

        .hdr-text h2 {
            font-size: 12px;
            color: #374151;
            margin: 0;
        }

        .subtitle {
            text-align: center;
            font-size: 10px;
            color: #6B7280;
            margin: 5px 0 2px;
        }

        .print-date {
            text-align: right;
            font-size: 9px;
            color: #9CA3AF;
            font-style: italic;
            margin-bottom: 10px;
        }

        .sec {
            font-size: 12px;
            font-weight: bold;
            color: #059669;
            border-bottom: 2px solid #D1FAE5;
            padding-bottom: 3px;
            margin: 13px 0 7px;
        }

        .sum-wrap {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 4px;
        }

        .sum-wrap>tbody>tr>td {
            vertical-align: top;
            width: 50%;
            padding: 0;
            border: none;
        }

        .dt {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin: 4px 0;
        }

        .dt th,
        .dt td {
            border: 1px solid #E5E7EB;
            padding: 5px 6px;
        }

        .dt th {
            background: #059669;
            color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }

        .dt tr:nth-child(even) td {
            background: #F9FAFB;
        }

        .dt tr.tot td {
            background: #059669 !important;
            color: #fff;
            font-weight: bold;
        }

        .tc {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            white-space: nowrap;
        }

        .rekap-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin: 4px 0 14px;
        }

        .rekap-tbl th,
        .rekap-tbl td {
            border: 1px solid #E5E7EB;
            padding: 5px 7px;
        }

        .rekap-tbl th {
            background: #059669;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        .rekap-tbl tr:nth-child(even) td {
            background: #ECFDF5;
        }

        .footer {
            margin-top: 18px;
            border-top: 1px solid #E5E7EB;
            padding-top: 8px;
            text-align: center;
            font-size: 9px;
            color: #6B7280;
        }

        .wrap {
            word-wrap: break-word;
            white-space: normal;
            max-width: 110px;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <div class="header">
        <table class="hdr-tbl">
            <tr>
                <td class="hdr-logo">
                    <?php if ($logoData): ?>
                        <img src="<?= $logoData ?>" alt="SMK NURUL ULUM">
                    <?php endif; ?>
                </td>
                <td class="hdr-text">
                    <h1>LAPORAN ABSENSI SISWA</h1>
                    <h2>SMK NURUL ULUM LEBAKSIU</h2>
                </td>
                <td style="width:65px"></td>
            </tr>
        </table>
    </div>
    <p class="subtitle"><?= htmlspecialchars($subtitle) ?></p>
    <p class="print-date">Diekspor pada: <?= date('d/m/Y H:i') ?></p>

    <!-- REKAP TOP SISWA -->
    <?php if (!empty($top_rekap)): ?>
        <div class="sec">REKAP KESELURUHAN KEHADIRAN SISWA</div>
        <table class="rekap-tbl">
            <tr>
                <th style="width:25px">No</th>
                <th>Nama Siswa</th>
                <th style="width:58px">NIS</th>
                <th style="width:40px">Kelas</th>
                <th style="width:38px">Hadir</th>
                <th style="width:34px">Sakit</th>
                <th style="width:30px">Izin</th>
                <th style="width:52px">Terlambat</th>
                <th style="width:38px">Alpha</th>
                <th style="width:36px">Total</th>
            </tr>
            <?php foreach ($top_rekap as $idx => $tk): ?>
                <tr>
                    <td class="tc"><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($tk['nama_lengkap']) ?></td>
                    <td class="tc"><?= htmlspecialchars($tk['nis']) ?></td>
                    <td class="tc"><?= $tk['kelas'] . ' ' . $tk['jurusan'] ?></td>
                    <td class="tc" style="color:#065F46;font-weight:bold"><?= $tk['hadir'] ?></td>
                    <td class="tc" style="color:#854D0E"><?= $tk['sakit'] ?></td>
                    <td class="tc" style="color:#5B21B6"><?= $tk['izin'] ?></td>
                    <td class="tc" style="color:#9A3412;font-weight:bold"><?= $tk['terlambat'] ?></td>
                    <td class="tc" style="color:#991B1B;font-weight:bold"><?= $tk['alpha'] ?></td>
                    <td class="tc"><?= $tk['total'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="sec">RINGKASAN KEHADIRAN</div>
    <table class="sum-wrap">
        <tbody>
            <tr>
                <!-- Kiri: Per Status -->
                <td>
                    <table class="dt">
                        <tr>
                            <th colspan="3">Rekapitulasi Status Kehadiran</th>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <th>Jumlah</th>
                            <th>Persentase</th>
                        </tr>
                        <?php foreach (['Hadir', 'Sakit', 'Izin', 'Terlambat', 'Alpha'] as $st):
                            $cnt = $status_counts[$st];
                            $pct = $total_absensi > 0 ? round($cnt / $total_absensi * 100, 1) . '%' : '0%';
                            $ss  = $statusStyle[$st];
                        ?>
                            <tr>
                                <td>
                                    <span class="badge"
                                        style="background:<?= $ss['bg'] ?>;color:<?= $ss['text'] ?>;border:1px solid <?= $ss['border'] ?>">
                                        <?= $st ?>
                                    </span>
                                </td>
                                <td class="tc"><?= $cnt ?></td>
                                <td class="tc"><?= $pct ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="tot">
                            <td>TOTAL</td>
                            <td class="tc"><?= $total_absensi ?></td>
                            <td class="tc">100%</td>
                        </tr>
                    </table>
                </td>

                <!-- Kanan: Tingkat Kehadiran + Approval -->
                <td>
                    <table class="dt" style="margin-bottom:8px">
                        <tr>
                            <th colspan="2">Tingkat Kehadiran</th>
                        </tr>
                        <tr>
                            <td>Hadir + Terlambat</td>
                            <td class="tc" style="color:#059669;font-weight:bold"><?= $kehadiran_pct ?>%</td>
                        </tr>
                        <tr>
                            <td>Alpha + Sakit + Izin</td>
                            <td class="tc" style="color:#DC2626;font-weight:bold"><?= $tdkhadir_pct ?>%</td>
                        </tr>
                        <tr>
                            <td>Total Catatan</td>
                            <td class="tc" style="font-weight:bold"><?= $total_absensi ?></td>
                        </tr>
                        <tr>
                            <td>Siswa Tercatat</td>
                            <td class="tc" style="font-weight:bold"><?= $total_siswa_unik ?></td>
                        </tr>
                    </table>
                    <table class="dt">
                        <tr>
                            <th colspan="3">Status Approval</th>
                        </tr>
                        <tr>
                            <th>Approval</th>
                            <th>Jumlah</th>
                            <th>Persen</th>
                        </tr>
                        <?php foreach (['Approved', 'Pending', 'Rejected'] as $apv):
                            $cnt = $approval_counts[$apv];
                            $pct = $total_apv > 0 ? round($cnt / $total_apv * 100, 1) . '%' : '0%';
                            $as  = $approvalStyle[$apv];
                        ?>
                            <tr>
                                <td>
                                    <span class="badge"
                                        style="background:<?= $as['bg'] ?>;color:<?= $as['text'] ?>">
                                        <?= $apv ?>
                                    </span>
                                </td>
                                <td class="tc"><?= $cnt ?></td>
                                <td class="tc"><?= $pct ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="tot">
                            <td>TOTAL</td>
                            <td class="tc"><?= $total_apv ?></td>
                            <td class="tc">100%</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        <p>Lebaksiu, <?= date('d F Y') ?></p>
        <br>
        <p>___________________________</p>
        <p><strong>Admin SMK NURUL ULUM LEBAKSIU</strong></p>
        <p style="margin-top:8px;font-style:italic">
            Laporan ini digenerate otomatis oleh Sistem Absensi SMK NURUL ULUM LEBAKSIU
        </p>
    </div>

</body>

</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ── Nomor halaman ──
$canvas = $dompdf->getCanvas();
$canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
    $text  = "Halaman $pageNumber dari $pageCount";
    $font  = $fontMetrics->getFont("Helvetica");
    $size  = 9;
    $width = $fontMetrics->getTextWidth($text, $font, $size);
    $canvas->text(
        ($canvas->get_width() - $width) / 2,
        $canvas->get_height() - 26,
        $text,
        $font,
        $size,
        [0.5, 0.5, 0.5]
    );
});

$filename = "Laporan_Absensi_" . date('Y-m-d') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit();
