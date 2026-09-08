<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$subjectId = (int) ($_GET['subject_id'] ?? 0);
$dateFilter = $_GET['date'] ?? '';
if ($dateFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $dateFilter = '';
}

$stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ? AND teacher_id = ? AND is_deleted = 0');
$stmt->execute([$subjectId, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if (!$subject) {
    http_response_code(404);
    exit('Subject not found.');
}

$sql = 'SELECT student_number, surname, scanned_at FROM attendance WHERE subject_id = ?';
$params = [$subjectId];
if ($dateFilter !== '') {
    $sql .= ' AND DATE(scanned_at) = ?';
    $params[] = $dateFilter;
}
$sql .= ' ORDER BY scanned_at ASC';

$records = $pdo->prepare($sql);
$records->execute($params);
$rows = $records->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance');

$titleSuffix = $dateFilter !== '' ? ' on ' . date('F j, Y', strtotime($dateFilter)) : '';
$sheet->setCellValue('A1', 'Attendance for ' . $subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['section'] . ')' . $titleSuffix);
$sheet->mergeCells('A1:D1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$sheet->setCellValue('A3', 'No.');
$sheet->setCellValue('B3', 'Student Number');
$sheet->setCellValue('C3', 'Surname');
$sheet->setCellValue('D3', 'Timestamp');
$sheet->getStyle('A3:D3')->getFont()->setBold(true);

$row = 4;
$no = 1;
foreach ($rows as $r) {
    $sheet->setCellValue("A$row", $no);
    $sheet->setCellValueExplicit("B$row", $r['student_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue("C$row", $r['surname']);
    $sheet->setCellValue("D$row", $r['scanned_at']);
    $row++;
    $no++;
}

foreach (['A', 'B', 'C', 'D'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$fileNameDate = $dateFilter !== '' ? $dateFilter : date('Y-m-d');
$fileNameParts = [$subject['subject_code'], $subject['subject_name'], $subject['section'], 'attendance', $fileNameDate];
$fileName = implode('_', array_map(function ($part) {
    return preg_replace('/[^A-Za-z0-9]+/', '_', trim($part));
}, $fileNameParts)) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
