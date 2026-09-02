<?php
require __DIR__ . '/../../includes/auth.php';
require_login('teacher', '../../index.php');
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$subjectId = (int) ($_GET['subject_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ? AND teacher_id = ?');
$stmt->execute([$subjectId, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if (!$subject) {
    http_response_code(404);
    exit('Subject not found.');
}

$records = $pdo->prepare('SELECT student_number, surname, scanned_at FROM attendance WHERE subject_id = ? ORDER BY scanned_at ASC');
$records->execute([$subjectId]);
$rows = $records->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance');

$sheet->setCellValue('A1', 'Attendance for ' . $subject['subject_code'] . ' - ' . $subject['subject_name'] . ' (' . $subject['section'] . ')');
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

$fileName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $subject['subject_code']) . '_attendance.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
