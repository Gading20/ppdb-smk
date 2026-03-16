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
// PARAMETER FILTER — sama dengan pelanggaran.php
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
$format     = $_GET['format']     ?? 'pdf';

// ──────────────────────────────────────────────
// BASE WHERE (kolom sesuai tabel pelanggaran)
// ──────────────────────────────────────────────
$base_where  = "FROM pelanggaran p
                JOIN siswa s ON p.siswa_id = s.id
                WHERE p.tanggal BETWEEN :start_date AND :end_date";
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
    $base_where .= " AND p.siswa_id = :siswa_id";
    $base_params['siswa_id'] = $siswa_id;
}
if ($jenis) {
    $base_where .= " AND p.jenis_pelanggaran = :jenis";
    $base_params['jenis']    = $jenis;
}
if ($status) {
    $base_where .= " AND p.status = :status";
    $base_params['status']   = $status;
}

// ──────────────────────────────────────────────
// 1. JENIS COUNTS
// ──────────────────────────────────────────────
$jenis_stmt = $conn->prepare(
    "SELECT p.jenis_pelanggaran, COUNT(*) as count $base_where GROUP BY p.jenis_pelanggaran"
);
$jenis_stmt->execute($base_params);
$jenis_counts = ['Ringan' => 0, 'Sedang' => 0, 'Berat' => 0];
foreach ($jenis_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['jenis_pelanggaran'], $jenis_counts)) {
        $jenis_counts[$row['jenis_pelanggaran']] = (int)$row['count'];
    }
}
$total_pelanggaran = array_sum($jenis_counts);

// ──────────────────────────────────────────────
// 2. STATUS COUNTS
// ──────────────────────────────────────────────
$status_stmt = $conn->prepare(
    "SELECT p.status, COUNT(*) as count $base_where GROUP BY p.status"
);
$status_stmt->execute($base_params);
$status_counts = ['Pending' => 0, 'Proses' => 0, 'Selesai' => 0];
foreach ($status_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists($row['status'], $status_counts)) {
        $status_counts[$row['status']] = (int)$row['count'];
    }
}

// ──────────────────────────────────────────────
// 3. TOP 5 SISWA — total poin langsung dari kolom p.poin
// ──────────────────────────────────────────────
$top_where  = "WHERE p.tanggal BETWEEN :start_date AND :end_date";
$top_params = ['start_date' => $start_date, 'end_date' => $end_date];
if ($kelas) {
    $top_where .= " AND s.kelas = :kelas";
    $top_params['kelas']   = $kelas;
}
if ($jurusan) {
    $top_where .= " AND s.jurusan = :jurusan";
    $top_params['jurusan'] = $jurusan;
}

$top_stmt = $conn->prepare(
    "SELECT s.id, s.nama_lengkap, s.nis, s.kelas, s.jurusan,
            COALESCE(SUM(p.poin), 0) AS total_poin,
            COUNT(p.id) AS jumlah
     FROM siswa s
     JOIN pelanggaran p ON p.siswa_id = s.id
     $top_where
     GROUP BY s.id, s.nama_lengkap, s.nis, s.kelas, s.jurusan
     ORDER BY total_poin DESC
     LIMIT 5"
);
$top_stmt->execute($top_params);
$top_siswa = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 4. DETAIL DATA — total_poin = akumulasi semua poin siswa
// ──────────────────────────────────────────────
$detail_stmt = $conn->prepare(
    "SELECT p.id, p.tanggal, p.jenis_pelanggaran, p.deskripsi,
            p.poin, p.tindakan, p.status,
            s.nama_lengkap, s.nis, s.kelas, s.jurusan,
            (SELECT COALESCE(SUM(pp.poin), 0)
             FROM pelanggaran pp
             WHERE pp.siswa_id = s.id) AS total_poin
     $base_where
     ORDER BY p.tanggal DESC, s.nama_lengkap ASC"
);
$detail_stmt->execute($base_params);
$data = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 5. SUBTITLE
// ──────────────────────────────────────────────
$subtitle = "Periode: " . date('d/m/Y', strtotime($start_date))
    . " - " . date('d/m/Y', strtotime($end_date));
if ($kelas)   $subtitle .= " | Kelas: $kelas";
if ($jurusan) $subtitle .= " | Jurusan: $jurusan";
if ($jenis)   $subtitle .= " | Jenis: $jenis";
if ($status)  $subtitle .= " | Status: $status";
if ($siswa_id) {
    $stu = $conn->prepare("SELECT nama_lengkap, nis FROM siswa WHERE id = :id");
    $stu->execute(['id' => $siswa_id]);
    $student = $stu->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $subtitle .= " | Siswa: " . $student['nama_lengkap'] . " (" . $student['nis'] . ")";
    }
}

// ── Helper warna total poin ──
function totalPoinColor(int $poin): array
{
    if ($poin >= 75) return ['hex' => '#EF4444', 'label' => 'Kritis'];
    if ($poin >= 50) return ['hex' => '#F97316', 'label' => 'Tinggi'];
    if ($poin >= 25) return ['hex' => '#EAB308', 'label' => 'Sedang'];
    return ['hex' => '#22C55E', 'label' => 'Rendah'];
}

// Warna badge sesuai CSS pelanggaran.php
$jenisStyle = [
    'Ringan' => ['bg' => '#DCFCE7', 'text' => '#166534', 'border' => '#22C55E'],
    'Sedang' => ['bg' => '#FEF9C3', 'text' => '#854D0E', 'border' => '#EAB308'],
    'Berat'  => ['bg' => '#FEE2E2', 'text' => '#991B1B', 'border' => '#EF4444'],
];
$statusStyle = [
    'Pending' => ['bg' => '#F3F4F6', 'text' => '#374151'],
    'Proses'  => ['bg' => '#FEF3C7', 'text' => '#92400E'],
    'Selesai' => ['bg' => '#D1FAE5', 'text' => '#065F46'],
];

// ================================================================
// EXCEL EXPORT
// ================================================================
if ($format === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Pelanggaran');
    $spreadsheet->getProperties()->setCreator('SMK NURUL ULUM')->setTitle('Laporan Pelanggaran');

    $purple = '5E35B1';
    $white  = 'FFFFFF';
    $purpleHdr = [
        'font'      => ['bold' => true, 'color' => ['rgb' => $white], 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $purple]],
    ];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];

    // ── Header blok ──
    foreach (['A1:J1', 'A2:J2', 'A3:J3', 'A4:J4'] as $m) $sheet->mergeCells($m);
    $sheet->setCellValue('A1', 'LAPORAN PELANGGARAN SISWA');
    $sheet->setCellValue('A2', 'SMK NURUL ULUM');
    $sheet->setCellValue('A3', $subtitle);
    $sheet->setCellValue('A4', 'Diekspor pada: ' . date('d/m/Y H:i'));
    $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EDE9FE']]]);
    $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 13], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $sheet->getStyle('A3')->applyFromArray(['font' => ['bold' => true, 'size' => 10], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
    $sheet->getStyle('A4')->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
    $sheet->getRowDimension(1)->setRowHeight(24);
    $sheet->getRowDimension(5)->setRowHeight(8);

    // ── Ringkasan ──
    $sheet->mergeCells('A6:J6');
    $sheet->setCellValue('A6', 'RINGKASAN PELANGGARAN');
    $sheet->getStyle('A6')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

    // Header ringkasan jenis & status
    foreach (['B8' => 'Jenis', 'C8' => 'Jumlah', 'D8' => '% Kasus', 'F8' => 'Status', 'G8' => 'Jumlah'] as $cell => $val) {
        $sheet->setCellValue($cell, $val);
    }
    $sheet->getStyle('B8:D8')->applyFromArray($purpleHdr);
    $sheet->getStyle('F8:G8')->applyFromArray($purpleHdr);
    $sheet->getRowDimension(8)->setRowHeight(18);

    $r = 9;
    foreach (['Ringan', 'Sedang', 'Berat'] as $j) {
        $cnt = $jenis_counts[$j];
        $pct = $total_pelanggaran > 0 ? round($cnt / $total_pelanggaran * 100, 1) . '%' : '0%';
        $sheet->setCellValue("B$r", $j);
        $sheet->setCellValue("C$r", $cnt);
        $sheet->setCellValue("D$r", $pct);
        $sheet->getStyle("C$r:D$r")->applyFromArray($center);
        $r++;
    }
    $sheet->setCellValue("B$r", 'TOTAL');
    $sheet->setCellValue("C$r", $total_pelanggaran);
    $sheet->setCellValue("D$r", '100%');
    $sheet->getStyle("B$r:D$r")->applyFromArray($purpleHdr);

    $r2 = 9;
    foreach (['Pending', 'Proses', 'Selesai'] as $s) {
        $sheet->setCellValue("F$r2", $s);
        $sheet->setCellValue("G$r2", $status_counts[$s]);
        $sheet->getStyle("G$r2")->applyFromArray($center);
        $r2++;
    }
    $sheet->setCellValue("F$r2", 'TOTAL');
    $sheet->setCellValue("G$r2", array_sum($status_counts));
    $sheet->getStyle("F$r2:G$r2")->applyFromArray($purpleHdr);

    // ── Top 5 Siswa ──
    $rT = max($r, $r2) + 2;
    $sheet->mergeCells("A$rT:J$rT");
    $sheet->setCellValue("A$rT", 'TOP 5 SISWA AKUMULASI POIN PELANGGARAN');
    $sheet->getStyle("A$rT")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
    $rT++;
    foreach (['A' => 'Rank', 'B' => 'NIS', 'C' => 'Nama Siswa', 'D' => 'Kelas', 'E' => 'Jurusan', 'F' => 'Jumlah Kasus', 'G' => 'Total Poin', 'H' => 'Kategori'] as $col => $hdr) {
        $sheet->setCellValue("$col$rT", $hdr);
    }
    $sheet->getStyle("A$rT:H$rT")->applyFromArray($purpleHdr);
    $sheet->getRowDimension($rT)->setRowHeight(18);
    $rT++;
    foreach ($top_siswa as $idx => $ts) {
        $color = totalPoinColor((int)$ts['total_poin']);
        $sheet->setCellValue("A$rT", '#' . ($idx + 1));
        $sheet->setCellValue("B$rT", $ts['nis']);
        $sheet->setCellValue("C$rT", $ts['nama_lengkap']);
        $sheet->setCellValue("D$rT", $ts['kelas']);
        $sheet->setCellValue("E$rT", $ts['jurusan']);
        $sheet->setCellValue("F$rT", $ts['jumlah']);
        $sheet->setCellValue("G$rT", $ts['total_poin']);
        $sheet->setCellValue("H$rT", $color['label']);
        $sheet->getStyle("A$rT:H$rT")->applyFromArray($center);
        $rT++;
    }

    // ── Detail ──
    $rD = $rT + 2;
    $sheet->mergeCells("A$rD:J$rD");
    $sheet->setCellValue("A$rD", 'DATA DETAIL PELANGGARAN');
    $sheet->getStyle("A$rD")->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
    $rD += 2;

    foreach (['A' => 'No', 'B' => 'Tanggal', 'C' => 'NIS', 'D' => 'Nama Siswa', 'E' => 'Kelas', 'F' => 'Jenis', 'G' => 'Deskripsi', 'H' => 'Poin', 'I' => 'Total Poin', 'J' => 'Status'] as $col => $hdr) {
        $sheet->setCellValue("$col$rD", $hdr);
    }
    $sheet->getStyle("A$rD:J$rD")->applyFromArray($purpleHdr);
    $sheet->getRowDimension($rD)->setRowHeight(18);
    $rD++;

    foreach ($data as $idx => $rec) {
        $sheet->setCellValue("A$rD", $idx + 1);
        $sheet->setCellValue("B$rD", date('d/m/Y', strtotime($rec['tanggal'])));
        $sheet->setCellValue("C$rD", $rec['nis']);
        $sheet->setCellValue("D$rD", $rec['nama_lengkap']);
        $sheet->setCellValue("E$rD", $rec['kelas'] . ' ' . $rec['jurusan']);
        $sheet->setCellValue("F$rD", $rec['jenis_pelanggaran']);
        $sheet->setCellValue("G$rD", $rec['deskripsi']);
        $sheet->setCellValue("H$rD", $rec['poin']);
        $sheet->setCellValue("I$rD", $rec['total_poin']);
        $sheet->setCellValue("J$rD", $rec['status']);
        $sheet->getStyle("A$rD:B$rD")->applyFromArray($center);
        $sheet->getStyle("E$rD:F$rD")->applyFromArray($center);
        $sheet->getStyle("H$rD:J$rD")->applyFromArray($center);
        if ($idx % 2 === 0) {
            $sheet->getStyle("A$rD:J$rD")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F3FF']],
            ]);
        }
        $rD++;
    }

    // Lebar kolom
    foreach (['A' => 5, 'B' => 13, 'C' => 14, 'D' => 28, 'E' => 14, 'F' => 10, 'G' => 35, 'H' => 7, 'I' => 10, 'J' => 10] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    $rD++;
    $sheet->mergeCells("A$rD:J$rD");
    $sheet->setCellValue("A$rD", 'Laporan ini digenerate otomatis oleh Sistem Absensi SMK NURUL ULUM');
    $sheet->getStyle("A$rD")->applyFromArray(['font' => ['italic' => true, 'size' => 9], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Laporan_Pelanggaran_' . date('Y-m-d') . '.xlsx"');
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
$possiblePaths = [
    $_SERVER['DOCUMENT_ROOT'] . '/assets/default/logosmk.png',
    dirname(dirname(dirname(__FILE__))) . '/assets/default/logosmk.png',
];
$logoData = '';
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
    <title>Laporan Pelanggaran</title>
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

        /* HEADER */
        .header {
            border-bottom: 3px solid #5E35B1;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .header-tbl {
            width: 100%;
            border-collapse: collapse;
        }

        .header-tbl td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .logo-cell {
            width: 65px;
        }

        .logo-cell img {
            height: 52px;
        }

        .title-cell {
            text-align: center;
        }

        .title-cell h1 {
            font-size: 17px;
            font-weight: bold;
            color: #5E35B1;
            margin: 0 0 3px;
        }

        .title-cell h2 {
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

        /* SECTION TITLE */
        .sec {
            font-size: 12px;
            font-weight: bold;
            color: #5E35B1;
            border-bottom: 2px solid #EDE9FE;
            padding-bottom: 3px;
            margin: 13px 0 7px;
        }

        /* SUMMARY LAYOUT */
        .sum-tbl {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 2px;
        }

        .sum-tbl>tbody>tr>td {
            vertical-align: top;
            width: 50%;
            padding: 0;
            border: none;
        }

        /* DATA TABLE */
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

        /* BADGE */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            white-space: nowrap;
        }

        /* POIN BAR */
        .bar-wrap {
            width: 42px;
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
        }

        /* TOP 5 */
        .top-tbl {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
        }

        .top-tbl td {
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 7px 4px;
            text-align: center;
            background: #FAFAFA;
            vertical-align: top;
            width: 20%;
        }

        .t-rank {
            font-size: 10px;
            font-weight: bold;
            color: #5E35B1;
        }

        .t-name {
            font-size: 9px;
            font-weight: bold;
            margin: 3px 0 1px;
            line-height: 1.3;
        }

        .t-kelas {
            font-size: 8px;
            color: #6B7280;
        }

        .t-poin {
            font-size: 12px;
            font-weight: bold;
            margin-top: 4px;
        }

        .t-info {
            font-size: 8px;
            color: #6B7280;
        }

        /* FOOTER */
        .footer {
            margin-top: 18px;
            border-top: 1px solid #E5E7EB;
            padding-top: 8px;
            text-align: center;
            font-size: 9px;
            color: #6B7280;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <div class="header">
        <table class="header-tbl">
            <tr>
                <td class="logo-cell">
                    <?php if ($logoData): ?>
                        <img src="<?= $logoData ?>" alt="SMK NURUL ULUM">
                    <?php endif; ?>
                </td>
                <td class="title-cell">
                    <h1>LAPORAN PELANGGARAN SISWA</h1>
                    <h2>SMK NURUL ULUM</h2>
                </td>
                <td style="width:65px"></td>
            </tr>
        </table>
    </div>
    <p class="subtitle"><?= htmlspecialchars($subtitle) ?></p>
    <p class="print-date">Diekspor pada: <?= date('d/m/Y H:i') ?></p>

    <!-- RINGKASAN -->
    <div class="sec">RINGKASAN PELANGGARAN</div>
    <table class="sum-tbl">
        <tbody>
            <tr>
                <!-- Kiri: Jenis -->
                <td>
                    <table class="dt">
                        <tr>
                            <th colspan="3">Berdasarkan Jenis Pelanggaran</th>
                        </tr>
                        <tr>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Persentase</th>
                        </tr>
                        <?php foreach (['Ringan', 'Sedang', 'Berat'] as $j):
                            $cnt = $jenis_counts[$j];
                            $pct = $total_pelanggaran > 0 ? round($cnt / $total_pelanggaran * 100, 1) . '%' : '0%';
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
                            <td class="tc"><?= $total_pelanggaran ?></td>
                            <td class="tc">100%</td>
                        </tr>
                    </table>
                </td>
                <!-- Kanan: Status -->
                <td>
                    <table class="dt">
                        <tr>
                            <th colspan="3">Berdasarkan Status Tindak Lanjut</th>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <th>Jumlah</th>
                            <th>Persentase</th>
                        </tr>
                        <?php
                        $total_status = array_sum($status_counts);
                        foreach (['Pending', 'Proses', 'Selesai'] as $s):
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

    <!-- TOP 5 SISWA -->
    <?php if (!empty($top_siswa)): ?>
        <div class="sec">TOP 5 SISWA AKUMULASI POIN PELANGGARAN</div>
        <table class="top-tbl">
            <tr>
                <?php foreach ($top_siswa as $idx => $ts):
                    $color = totalPoinColor((int)$ts['total_poin']);
                    $rnk   = ['#1', '#2', '#3', '#4', '#5'][$idx];
                ?>
                    <td>
                        <div class="t-rank"><?= $rnk ?></div>
                        <div class="t-name"><?= htmlspecialchars($ts['nama_lengkap']) ?></div>
                        <div class="t-kelas"><?= $ts['kelas'] ?> <?= $ts['jurusan'] ?></div>
                        <div class="t-poin" style="color:<?= $color['hex'] ?>"><?= $ts['total_poin'] ?> <span style="font-size:8px;font-weight:normal;color:#6B7280">poin</span></div>
                        <div class="t-info"><?= $color['label'] ?> &bull; <?= $ts['jumlah'] ?> kasus</div>
                    </td>
                <?php endforeach; ?>
                <?php for ($i = count($top_siswa); $i < 5; $i++): ?>
                    <td style="border:1px dashed #E5E7EB;background:#F9FAFB"></td>
                <?php endfor; ?>
            </tr>
        </table>
    <?php endif; ?>

    <!-- DETAIL PELANGGARAN -->
    <div class="sec">DETAIL PELANGGARAN</div>
    <?php if (count($data) > 0): ?>
        <table class="dt">
            <tr>
                <th style="width:28px">No</th>
                <th style="width:58px">Tanggal</th>
                <th style="width:65px">NIS</th>
                <th>Nama Siswa</th>
                <th style="width:52px">Kelas</th>
                <th style="width:44px">Jenis</th>
                <th>Deskripsi</th>
                <th style="width:28px">Poin</th>
                <th style="width:65px">Total Poin</th>
                <th style="width:48px">Status</th>
            </tr>
            <?php foreach ($data as $no => $rec):
                $js    = $jenisStyle[$rec['jenis_pelanggaran']] ?? ['bg' => '#F3F4F6', 'text' => '#374151', 'border' => '#D1D5DB'];
                $ss    = $statusStyle[$rec['status']] ?? ['bg' => '#F3F4F6', 'text' => '#374151'];
                $tp    = min((int)$rec['total_poin'], 100);
                $color = totalPoinColor((int)$rec['total_poin']);
            ?>
                <tr>
                    <td class="tc"><?= $no + 1 ?></td>
                    <td class="tc"><?= date('d/m/Y', strtotime($rec['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($rec['nis']) ?></td>
                    <td><?= htmlspecialchars($rec['nama_lengkap']) ?></td>
                    <td class="tc"><?= $rec['kelas'] . ' ' . $rec['jurusan'] ?></td>
                    <td class="tc">
                        <span class="badge" style="background:<?= $js['bg'] ?>;color:<?= $js['text'] ?>;border:1px solid <?= $js['border'] ?>">
                            <?= $rec['jenis_pelanggaran'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($rec['deskripsi']) ?></td>
                    <td class="tc" style="font-weight:bold;color:<?= $js['text'] ?>"><?= $rec['poin'] ?></td>
                    <td class="tc">
                        <span style="font-weight:bold;color:<?= $color['hex'] ?>"><?= $rec['total_poin'] ?></span>
                        <div class="bar-wrap">
                            <div class="bar-fill" style="background:<?= $color['hex'] ?>;width:<?= $tp ?>%"></div>
                        </div>
                    </td>
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
            Tidak ada data pelanggaran untuk filter yang dipilih.
        </p>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="footer">
        <p>Lebaksiu, <?= date('d F Y') ?></p>
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

$dompdf->stream("Laporan_Pelanggaran_" . date('Y-m-d') . ".pdf", ["Attachment" => true]);
exit;
