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
// PARAMETER FILTER — sama persis dengan laporan_konseling.php
// Kolom tabel: id, siswa_id, tanggal, jenis_konseling, masalah,
//              solusi, tindak_lanjut, konselor, status,
//              created_by, created_at, updated_at
// ──────────────────────────────────────────────
$default_start_date = date('Y-m-01');
$default_end_date   = date('Y-m-t');

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date   = $_GET['end_date']   ?? $default_end_date;
$kelas      = $_GET['kelas']      ?? '';
$jurusan    = $_GET['jurusan']    ?? '';
$siswa_id   = $_GET['siswa_id']   ?? '';
$jenis      = $_GET['jenis']      ?? '';
$status     = $_GET['status']     ?? '';
$konselor   = $_GET['konselor']   ?? '';
$format     = $_GET['format']     ?? 'pdf';

// ──────────────────────────────────────────────
// BASE WHERE — identik dengan laporan_konseling.php
// ──────────────────────────────────────────────
$base_where  = "FROM konseling k
                JOIN siswa s ON k.siswa_id = s.id
                WHERE k.tanggal BETWEEN :start_date AND :end_date";
$base_params = ['start_date' => $start_date, 'end_date' => $end_date];

if ($kelas) {
    $base_where .= " AND s.kelas = :kelas";
    $base_params['kelas']    = $kelas;
}
if ($jurusan) {
    $base_where .= " AND s.jurusan = :jurusan";
    $base_params['jurusan']  = $jurusan;
}
if ($siswa_id) {
    $base_where .= " AND k.siswa_id = :siswa_id";
    $base_params['siswa_id'] = $siswa_id;
}
if ($jenis) {
    $base_where .= " AND k.jenis_konseling = :jenis";
    $base_params['jenis']    = $jenis;
}
if ($status) {
    $base_where .= " AND k.status = :status";
    $base_params['status']   = $status;
}
if ($konselor) {
    $base_where .= " AND k.konselor LIKE :konselor";
    $base_params['konselor'] = "%$konselor%";
}

// ──────────────────────────────────────────────
// 1. JENIS COUNTS (Akademik / Pribadi / Sosial / Karir)
// ──────────────────────────────────────────────
$jenis_stmt = $conn->prepare(
    "SELECT k.jenis_konseling, COUNT(*) as count $base_where GROUP BY k.jenis_konseling"
);
$jenis_stmt->execute($base_params);
$jenis_counts = ['Akademik' => 0, 'Pribadi' => 0, 'Sosial' => 0, 'Karir' => 0];
foreach ($jenis_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($jenis_counts[$row['jenis_konseling']])) {
        $jenis_counts[$row['jenis_konseling']] = (int)$row['count'];
    }
}
$total_konseling = array_sum($jenis_counts);

// ──────────────────────────────────────────────
// 2. STATUS COUNTS (Dijadwalkan / Berlangsung / Selesai)
// ──────────────────────────────────────────────
$status_stmt = $conn->prepare(
    "SELECT k.status, COUNT(*) as count $base_where GROUP BY k.status"
);
$status_stmt->execute($base_params);
$status_counts = ['Dijadwalkan' => 0, 'Berlangsung' => 0, 'Selesai' => 0];
foreach ($status_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']] = (int)$row['count'];
    }
}

// ──────────────────────────────────────────────
// 3. TOP KONSELOR — sama dengan laporan_konseling.php
// ──────────────────────────────────────────────
$top_konselor_stmt = $conn->prepare(
    "SELECT k.konselor,
            COUNT(*) as jumlah,
            SUM(CASE WHEN k.status = 'Selesai' THEN 1 ELSE 0 END) as selesai
     $base_where
       AND k.konselor IS NOT NULL AND k.konselor != ''
     GROUP BY k.konselor
     ORDER BY jumlah DESC
     LIMIT 5"
);
$top_konselor_stmt->execute($base_params);
$top_konselor = $top_konselor_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 4. DETAIL DATA — semua kolom tabel konseling, tanpa pagination
// ──────────────────────────────────────────────
$detail_stmt = $conn->prepare(
    "SELECT k.id, k.tanggal, k.jenis_konseling, k.masalah, k.solusi,
            k.tindak_lanjut, k.konselor, k.status,
            s.nama_lengkap, s.nis, s.kelas, s.jurusan
     $base_where
     ORDER BY k.tanggal DESC, s.nama_lengkap ASC"
);
$detail_stmt->execute($base_params);
$data = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 5. SUBTITLE
// ──────────────────────────────────────────────
$subtitle = "Periode: " . date('d/m/Y', strtotime($start_date))
    . " - " . date('d/m/Y', strtotime($end_date));
if ($kelas)    $subtitle .= " | Kelas: $kelas";
if ($jurusan)  $subtitle .= " | Jurusan: $jurusan";
if ($jenis)    $subtitle .= " | Jenis: $jenis";
if ($status)   $subtitle .= " | Status: $status";
if ($konselor) $subtitle .= " | Konselor: $konselor";
if ($siswa_id) {
    $stu = $conn->prepare("SELECT nama_lengkap, nis FROM siswa WHERE id = :id");
    $stu->execute(['id' => $siswa_id]);
    $student = $stu->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $subtitle .= " | Siswa: " . $student['nama_lengkap'] . " (" . $student['nis'] . ")";
    }
}

// ── Warna badge — sesuai CSS laporan_konseling.php ──
$jenisStyle = [
    'Akademik' => ['bg' => '#EDE9FE', 'text' => '#5B21B6', 'border' => '#8B5CF6'],
    'Pribadi'  => ['bg' => '#DBEAFE', 'text' => '#1E40AF', 'border' => '#3B82F6'],
    'Sosial'   => ['bg' => '#D1FAE5', 'text' => '#065F46', 'border' => '#10B981'],
    'Karir'    => ['bg' => '#FFEDD5', 'text' => '#9A3412', 'border' => '#F97316'],
];
$statusStyle = [
    'Dijadwalkan' => ['bg' => '#F3F4F6', 'text' => '#374151'],
    'Berlangsung' => ['bg' => '#DBEAFE', 'text' => '#1E40AF'],
    'Selesai'     => ['bg' => '#D1FAE5', 'text' => '#065F46'],
];

// ================================================================
// EXCEL EXPORT — urutan: Header → Data Detail → Ringkasan
//                        (Aktivitas Konselor dihapus)
// ================================================================
if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Konseling');
    $spreadsheet->getProperties()->setCreator('SMK NURUL ULUM')->setTitle('Laporan Konseling');

    $purple = '5E35B1';
    $white  = 'FFFFFF';
    $purpleHdr = [
        'font'      => ['bold' => true, 'color' => ['rgb' => $white], 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $purple]],
    ];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];

    // ── Header blok ──
    foreach (['A1:K1', 'A2:K2', 'A3:K3', 'A4:K4'] as $m) $sheet->mergeCells($m);
    $sheet->setCellValue('A1', 'LAPORAN KONSELING SISWA');
    $sheet->setCellValue('A2', 'SMK NURUL ULUM');
    $sheet->setCellValue('A3', $subtitle);
    $sheet->setCellValue('A4', 'Diekspor pada: ' . date('d/m/Y H:i'));
    $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE9FE']]]);
    $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $sheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 10], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $sheet->getStyle('A4')->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getRowDimension(1)->setRowHeight(24);
    $sheet->getRowDimension(5)->setRowHeight(8);

    // ================================================================
    // BAGIAN 1 — DATA DETAIL KONSELING (sekarang di atas)
    // ================================================================
    $rD = 6;
    $sheet->mergeCells("A$rD:K$rD");
    $sheet->setCellValue("A$rD", 'DATA DETAIL KONSELING');
    $sheet->getStyle("A$rD")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
    $rD += 2;

    $cols = [
        'A' => 'No',
        'B' => 'Tanggal',
        'C' => 'NIS',
        'D' => 'Nama Siswa',
        'E' => 'Kelas',
        'F' => 'Jenis',
        'G' => 'Masalah',
        'H' => 'Solusi',
        'I' => 'Tindak Lanjut',
        'J' => 'Konselor',
        'K' => 'Status'
    ];
    foreach ($cols as $col => $hdr) {
        $sheet->setCellValue("$col$rD", $hdr);
    }
    $sheet->getStyle("A$rD:K$rD")->applyFromArray($purpleHdr);
    $sheet->getRowDimension($rD)->setRowHeight(18);
    $rD++;

    foreach ($data as $idx => $rec) {
        $sheet->setCellValue("A$rD", $idx + 1);
        $sheet->setCellValue("B$rD", date('d/m/Y', strtotime($rec['tanggal'])));
        $sheet->setCellValue("C$rD", $rec['nis']);
        $sheet->setCellValue("D$rD", $rec['nama_lengkap']);
        $sheet->setCellValue("E$rD", $rec['kelas'] . ' ' . $rec['jurusan']);
        $sheet->setCellValue("F$rD", $rec['jenis_konseling']);
        $sheet->setCellValue("G$rD", $rec['masalah'] ?? '-');
        $sheet->setCellValue("H$rD", $rec['solusi'] ?? '-');
        $sheet->setCellValue("I$rD", $rec['tindak_lanjut'] ?? '-');
        $sheet->setCellValue("J$rD", $rec['konselor'] ?? '-');
        $sheet->setCellValue("K$rD", $rec['status']);
        $sheet->getStyle("A$rD:B$rD")->applyFromArray($center);
        $sheet->getStyle("E$rD:F$rD")->applyFromArray($center);
        $sheet->getStyle("K$rD")->applyFromArray($center);
        if ($idx % 2 === 0) {
            $sheet->getStyle("A$rD:K$rD")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F3FF']],
            ]);
        }
        $rD++;
    }

    // ================================================================
    // BAGIAN 2 — RINGKASAN KONSELING (sekarang di bawah detail)
    // ================================================================
    $rS = $rD + 2;
    $sheet->mergeCells("A$rS:K$rS");
    $sheet->setCellValue("A$rS", 'RINGKASAN KONSELING');
    $sheet->getStyle("A$rS")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
    $rS += 2;

    // Header ringkasan jenis
    foreach (['B' . $rS => 'Jenis Konseling', 'C' . $rS => 'Jumlah', 'D' . $rS => 'Persentase'] as $cell => $val) {
        $sheet->setCellValue($cell, $val);
    }
    $sheet->getStyle("B$rS:D$rS")->applyFromArray($purpleHdr);

    // Header ringkasan status
    foreach (['F' . $rS => 'Status Sesi', 'G' . $rS => 'Jumlah', 'H' . $rS => 'Persentase'] as $cell => $val) {
        $sheet->setCellValue($cell, $val);
    }
    $sheet->getStyle("F$rS:H$rS")->applyFromArray($purpleHdr);
    $sheet->getRowDimension($rS)->setRowHeight(18);
    $rS++;

    // Data jenis
    $r = $rS;
    foreach (['Akademik', 'Pribadi', 'Sosial', 'Karir'] as $j) {
        $cnt = $jenis_counts[$j];
        $pct = $total_konseling > 0 ? round($cnt / $total_konseling * 100, 1) . '%' : '0%';
        $sheet->setCellValue("B$r", $j);
        $sheet->setCellValue("C$r", $cnt);
        $sheet->setCellValue("D$r", $pct);
        $sheet->getStyle("C$r:D$r")->applyFromArray($center);
        $r++;
    }
    $sheet->setCellValue("B$r", 'TOTAL');
    $sheet->setCellValue("C$r", $total_konseling);
    $sheet->setCellValue("D$r", '100%');
    $sheet->getStyle("B$r:D$r")->applyFromArray($purpleHdr);

    // Data status
    $total_status = array_sum($status_counts);
    $r2 = $rS;
    foreach (['Dijadwalkan', 'Berlangsung', 'Selesai'] as $s) {
        $cnt = $status_counts[$s];
        $pct = $total_status > 0 ? round($cnt / $total_status * 100, 1) . '%' : '0%';
        $sheet->setCellValue("F$r2", $s);
        $sheet->setCellValue("G$r2", $cnt);
        $sheet->setCellValue("H$r2", $pct);
        $sheet->getStyle("G$r2:H$r2")->applyFromArray($center);
        $r2++;
    }
    $sheet->setCellValue("F$r2", 'TOTAL');
    $sheet->setCellValue("G$r2", $total_status);
    $sheet->setCellValue("H$r2", '100%');
    $sheet->getStyle("F$r2:H$r2")->applyFromArray($purpleHdr);

    // ── Lebar kolom ──
    foreach (
        [
            'A' => 5,
            'B' => 13,
            'C' => 14,
            'D' => 28,
            'E' => 14,
            'F' => 12,
            'G' => 30,
            'H' => 30,
            'I' => 30,
            'J' => 22,
            'K' => 13
        ] as $col => $w
    ) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    // ── Footer ──
    $rFooter = max($r, $r2) + 2;
    $sheet->mergeCells("A$rFooter:K$rFooter");
    $sheet->setCellValue("A$rFooter", 'Laporan ini digenerate otomatis oleh Sistem Absensi SMK NURUL ULUM');
    $sheet->getStyle("A$rFooter")->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Laporan_Konseling_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

// ================================================================
// PDF EXPORT
// ================================================================
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('chroot', $_SERVER['DOCUMENT_ROOT']);
$dompdf = new Dompdf($options);

// Logo
$logoData = '';
$possiblePaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/assets/default/logosmk.png',
    dirname(dirname(dirname(__FILE__))) . '/assets/default/logosmk.png',
];
foreach ($possiblePaths as $p) {
    if (file_exists($p)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($p));
        break;
    }
}

ob_start();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Konseling</title>
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

        /* ── HEADER ── */
        .header {
            border-bottom: 3px solid #5E35B1;
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
            color: #5E35B1;
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

        /* ── SECTION TITLE ── */
        .sec {
            font-size: 12px;
            font-weight: bold;
            color: #5E35B1;
            border-bottom: 2px solid #EDE9FE;
            padding-bottom: 3px;
            margin: 13px 0 7px;
        }

        /* ── SUMMARY — 2 kolom ── */
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

        /* ── DATA TABLE ── */
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
            background: #5E35B1;
            color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }

        .dt tr:nth-child(even) td {
            background: #FAFAFA;
        }

        .dt tr.tot td {
            background: #5E35B1 !important;
            color: #fff;
            font-weight: bold;
        }

        .tc {
            text-align: center;
        }

        /* ── BADGE ── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            white-space: nowrap;
        }

        /* ── PROGRESS BAR (untuk konselor) ── */
        .bar-wrap {
            width: 50px;
            height: 5px;
            background: #E5E7EB;
            border-radius: 999px;
            display: inline-block;
            vertical-align: middle;
            overflow: hidden;
            margin-left: 4px;
        }

        .bar-fill {
            height: 100%;
            border-radius: 999px;
            background: #8B5CF6;
        }

        /* ── TOP KONSELOR ── */
        .konselor-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin: 4px 0 14px;
        }

        .konselor-tbl th,
        .konselor-tbl td {
            border: 1px solid #E5E7EB;
            padding: 5px 7px;
        }

        .konselor-tbl th {
            background: #5E35B1;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        .konselor-tbl tr:nth-child(even) td {
            background: #F5F3FF;
        }

        /* ── FOOTER ── */
        .footer {
            margin-top: 18px;
            border-top: 1px solid #E5E7EB;
            padding-top: 8px;
            text-align: center;
            font-size: 9px;
            color: #6B7280;
        }

        /* teks panjang — wrap di PDF */
        .wrap {
            word-wrap: break-word;
            white-space: normal;
            max-width: 100px;
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
                    <h1>LAPORAN KONSELING SISWA</h1>
                    <h2>SMK NURUL ULUM</h2>
                </td>
                <td style="width:65px"></td>
            </tr>
        </table>
    </div>
    <p class="subtitle"><?= htmlspecialchars($subtitle) ?></p>
    <p class="print-date">Diekspor pada: <?= date('d/m/Y H:i') ?></p>

    <!-- DETAIL KONSELING -->
    <div class="sec">DETAIL KONSELING</div>
    <?php if (count($data) > 0): ?>
        <table class="dt">
            <tr>
                <th style="width:25px">No</th>
                <th style="width:55px">Tanggal</th>
                <th style="width:60px">NIS</th>
                <th style="width:80px">Nama Siswa</th>
                <th style="width:45px">Kelas</th>
                <th style="width:42px">Jenis</th>
                <th>Masalah</th>
                <th>Solusi</th>
                <th>Tindak Lanjut</th>
                <th style="width:65px">Konselor</th>
                <th style="width:52px">Status</th>
            </tr>
            <?php foreach ($data as $no => $rec):
                $js = $jenisStyle[$rec['jenis_konseling']] ?? ['bg' => '#F3F4F6', 'text' => '#374151', 'border' => '#D1D5DB'];
                $ss = $statusStyle[$rec['status']] ?? ['bg' => '#F3F4F6', 'text' => '#374151'];
            ?>
                <tr>
                    <td class="tc"><?= $no + 1 ?></td>
                    <td class="tc"><?= date('d/m/Y', strtotime($rec['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($rec['nis']) ?></td>
                    <td><?= htmlspecialchars($rec['nama_lengkap']) ?></td>
                    <td class="tc"><?= $rec['kelas'] . ' ' . $rec['jurusan'] ?></td>
                    <td class="tc">
                        <span class="badge" style="background:<?= $js['bg'] ?>;color:<?= $js['text'] ?>;border:1px solid <?= $js['border'] ?>">
                            <?= $rec['jenis_konseling'] ?>
                        </span>
                    </td>
                    <td class="wrap"><?= htmlspecialchars($rec['masalah'] ?? '-') ?></td>
                    <td class="wrap"><?= htmlspecialchars($rec['solusi'] ?? '-') ?></td>
                    <td class="wrap"><?= htmlspecialchars($rec['tindak_lanjut'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($rec['konselor'] ?? '-') ?></td>
                    <td class="tc">
                        <span class="badge" style="background:<?= $ss['bg'] ?>;color:<?= $ss['text'] ?>">
                            <?= $rec['status'] ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="text-align:center;color:#9CA3AF;padding:16px 0">
            Tidak ada data konseling untuk filter yang dipilih.
        </p>
    <?php endif; ?>

    <!-- RINGKASAN -->
    <div class="sec">RINGKASAN KONSELING</div>
    <table class="sum-wrap">
        <tbody>
            <tr>

                <!-- Kiri: Ringkasan Jenis Konseling -->
                <td>
                    <table class="dt">
                        <tr>
                            <th colspan="3">Berdasarkan Jenis Konseling</th>
                        </tr>
                        <tr>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Persentase</th>
                        </tr>
                        <?php foreach (['Akademik', 'Pribadi', 'Sosial', 'Karir'] as $j):
                            $cnt = $jenis_counts[$j];
                            $pct = $total_konseling > 0 ? round($cnt / $total_konseling * 100, 1) . '%' : '0%';
                            $js  = $jenisStyle[$j];
                        ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background:<?= $js['bg'] ?>;color:<?= $js['text'] ?>;border:1px solid <?= $js['border'] ?>">
                                        <?= $j ?>
                                    </span>
                                </td>
                                <td class="tc"><?= $cnt ?></td>
                                <td class="tc"><?= $pct ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="tot">
                            <td>TOTAL</td>
                            <td class="tc"><?= $total_konseling ?></td>
                            <td class="tc">100%</td>
                        </tr>
                    </table>
                </td>

                <!-- Kanan: Ringkasan Status -->
                <td>
                    <table class="dt">
                        <tr>
                            <th colspan="3">Berdasarkan Status Sesi</th>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <th>Jumlah</th>
                            <th>Persentase</th>
                        </tr>
                        <?php
                        $total_status = array_sum($status_counts);
                        foreach (['Dijadwalkan', 'Berlangsung', 'Selesai'] as $s):
                            $ss  = $statusStyle[$s];
                            $cnt = $status_counts[$s];
                            $pct = $total_status > 0 ? round($cnt / $total_status * 100, 1) . '%' : '0%';
                        ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background:<?= $ss['bg'] ?>;color:<?= $ss['text'] ?>">
                                        <?= $s ?>
                                    </span>
                                </td>
                                <td class="tc"><?= $cnt ?></td>
                                <td class="tc"><?= $pct ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="tot">
                            <td>TOTAL</td>
                            <td class="tc"><?= $total_status ?></td>
                            <td class="tc">100%</td>
                        </tr>
                    </table>
                </td>

            </tr>
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        <p>Jakarta, <?= date('d F Y') ?></p>
        <br>
        <p>___________________________</p>
        <p><strong>Admin SMK NURUL ULUM</strong></p>
        <p style="margin-top:8px;font-style:italic">
            Laporan ini digenerate otomatis oleh Sistem Absensi SMK NURUL ULUM
        </p>
    </div>

</body>

</html>
<?php
$html = ob_get_clean();

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Nomor halaman
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

$dompdf->stream("Laporan_Konseling_" . date('Y-m-d') . ".pdf", ["Attachment" => true]);
exit;
