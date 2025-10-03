<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['client','therapist','admin'])) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
require_once 'connect.php';

$therapistId = isset($_GET['therapist_id']) ? (int)$_GET['therapist_id'] : 0;
if (!$therapistId) { echo json_encode(['success'=>false,'error'=>'therapist_id required']); exit(); }

$data = [];
$sql = "SELECT day_of_week, start_time, end_time, is_available FROM doctor_availability WHERE therapist_id = ? AND is_available = 1 ORDER BY FIELD(day_of_week,'monday','tuesday','wednesday','thursday','friday','saturday','sunday'), start_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $therapistId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $day = $row['day_of_week'];
    if (!isset($data[$day])) $data[$day] = [];
    // For next 28 days, include each matching weekday
    $today = new DateTime('today');
    for ($i=0; $i<28; $i++) {
        $d = (clone $today)->modify("+{$i} day");
        if (strtolower($d->format('l')) === $day) {
            $data[$day][] = [
                'date' => $d->format('Y-m-d'),
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'start_time_label' => date('g:i A', strtotime($row['start_time'])),
                'end_time_label' => date('g:i A', strtotime($row['end_time']))
            ];
        }
    }
}
$stmt->close();
$conn->close();
echo json_encode(['success'=>true,'data'=>$data]);
?>


