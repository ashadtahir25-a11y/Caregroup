<?php
// includes/notify.php - In-app notifications (the bell on every dashboard).
// Loaded from functions.php, so every page can call notify().

// Only links to our own pages are stored, e.g. "slip.php?id=12"
function safe_internal_link(string $link): string
{
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%\-]*)?$/', $link) ? $link : '';
}

// Create one notification. A failure is logged and never breaks the page.
function notify(PDO $pdo, int $userId, string $type, string $title, string $body = '', string $link = ''): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $type, substr($title, 0, 150), substr($body, 0, 255), safe_internal_link($link)]);
    } catch (PDOException $e) {
        error_log('notify() failed (run database/update_part5.sql): ' . $e->getMessage());
    }
}

// Notify every admin account (used for new contact messages)
function notify_admins(PDO $pdo, string $type, string $title, string $body = '', string $link = ''): void
{
    try {
        foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            notify($pdo, (int) $adminId, $type, $title, $body, $link);
        }
    } catch (PDOException $e) {
        error_log('notify_admins() failed: ' . $e->getMessage());
    }
}

// Load the people and time of an appointment, for writing notification text
function appointment_parties(PDO $pdo, int $appointmentId): ?array
{
    $stmt = $pdo->prepare('SELECT a.id, a.appointment_date, a.time_slot, a.status,
                                  p.user_id AS patient_user, p.name AS patient_name,
                                  d.user_id AS doctor_user, d.name AS doctor_name
                             FROM appointments a
                             JOIN patients p ON p.id = a.patient_id
                             JOIN doctors d ON d.id = a.doctor_id
                            WHERE a.id = ?');
    $stmt->execute([$appointmentId]);
    return $stmt->fetch() ?: null;
}

// One call for the common appointment events.
// $event: booked | confirmed | cancelled_by_doctor | cancelled_by_patient | cancelled_by_admin
//         | completed_by_admin | rescheduled | prescription
function notify_appointment(PDO $pdo, int $appointmentId, string $event): void
{
    $a = appointment_parties($pdo, $appointmentId);
    if (!$a) {
        return;
    }
    $when    = date('D, j M', strtotime($a['appointment_date'])) . ' at ' . $a['time_slot'];
    $doctor  = 'Dr. ' . preg_replace('/^(dr\.?\s+)/i', '', $a['doctor_name']);
    $patient = $a['patient_name'];

    switch ($event) {
        case 'booked':
            notify($pdo, (int) $a['doctor_user'], 'request', 'New appointment request', "$patient asked for $when.", 'doctor_dashboard.php?view=appointments&tab=pending');
            break;
        case 'confirmed':
            notify($pdo, (int) $a['patient_user'], 'confirmed', 'Appointment confirmed', "$doctor confirmed your visit on $when.", 'slip.php?id=' . $a['id']);
            break;
        case 'cancelled_by_doctor':
            notify($pdo, (int) $a['patient_user'], 'cancelled', 'Appointment cancelled', "$doctor cancelled your visit on $when. You can book another time.", 'patient_dashboard.php?view=book');
            break;
        case 'cancelled_by_admin':
            notify($pdo, (int) $a['patient_user'], 'cancelled', 'Appointment cancelled', "CARE Group cancelled your visit with $doctor on $when.", 'patient_dashboard.php?view=book');
            notify($pdo, (int) $a['doctor_user'], 'cancelled', 'Appointment cancelled', "The admin cancelled $patient's visit on $when.", 'doctor_dashboard.php?view=appointments&tab=cancelled');
            break;
        case 'cancelled_by_patient':
            notify($pdo, (int) $a['doctor_user'], 'cancelled', 'Patient cancelled', "$patient cancelled the visit on $when. The slot is free again.", 'doctor_dashboard.php?view=appointments&tab=cancelled');
            break;
        case 'completed_by_admin':
            notify($pdo, (int) $a['patient_user'], 'completed', 'Visit marked as completed', "Your visit with $doctor on $when is complete.", 'patient_dashboard.php?view=history&tab=past');
            break;
        case 'rescheduled':
            notify($pdo, (int) $a['doctor_user'], 'request', 'Appointment moved', "$patient moved their visit to $when. Please confirm the new time.", 'doctor_dashboard.php?view=appointments&tab=pending');
            break;
        case 'prescription':
            notify($pdo, (int) $a['patient_user'], 'prescription', 'Your prescription is ready', "$doctor wrote your prescription for the visit on $when.", 'slip.php?id=' . $a['id']);
            break;
    }
}

// Bell data for the signed-in user
function notification_summary(PDO $pdo, int $userId, int $limit = 8): array
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $unread = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit");
        $stmt->execute([$userId]);
        return ['unread' => $unread, 'items' => $stmt->fetchAll()];
    } catch (PDOException $e) {
        return ['unread' => 0, 'items' => []];
    }
}

// "5 min ago", "2 h ago", "3 Oct"
function time_ago(string $datetime): string
{
    $s = time() - strtotime($datetime);
    if ($s < 60) return 'Just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 604800) return floor($s / 86400) . ' d ago';
    return date('j M', strtotime($datetime));
}

function notification_icon(string $type): string
{
    $icons = [
        'request' => 'fa-calendar-plus', 'confirmed' => 'fa-calendar-check', 'cancelled' => 'fa-calendar-xmark',
        'completed' => 'fa-circle-check', 'prescription' => 'fa-prescription', 'review' => 'fa-star',
        'message' => 'fa-envelope', 'moderation' => 'fa-eye-slash',
    ];
    return $icons[$type] ?? 'fa-bell';
}
