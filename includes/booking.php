<?php
// includes/booking.php - Appointment rules shared by the patient, doctor and admin dashboards.
require_once __DIR__ . '/components.php';

const WEEK_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Time slots a doctor can choose from.
const SLOT_OPTIONS = [
    '09:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '02:00 PM',
    '03:00 PM', '04:00 PM', '05:00 PM', '06:00 PM', '07:00 PM',
];

const BOOKING_DAYS_AHEAD = 60;   // patients can book up to 60 days in advance

// SQL that sorts "02:00 PM" after "11:00 AM" (plain text sorting gets this wrong).
const SLOT_ORDER_SQL = "STR_TO_DATE(a.time_slot, '%h:%i %p')";

// Sort an array of "hh:mm AM" strings by real time.
function sort_slots(array $slots): array
{
    usort($slots, function ($a, $b) { return strtotime($a) <=> strtotime($b); });
    return $slots;
}

function csv_list(?string $value): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
}

// Which status changes each role may make.
function can_change_status(string $role, string $from, string $to): bool
{
    $rules = [
        'doctor'  => ['Pending' => ['Confirmed', 'Cancelled'], 'Confirmed' => ['Completed', 'Cancelled']],
        'patient' => ['Pending' => ['Cancelled'], 'Confirmed' => ['Cancelled']],
        'admin'   => ['Pending' => ['Confirmed', 'Cancelled'], 'Confirmed' => ['Completed', 'Cancelled']],
    ];
    return in_array($to, $rules[$role][$from] ?? [], true);
}

// Badge class for a status.
function status_badge(string $status): string
{
    return '<span class="badge badge--' . h(strtolower($status)) . '">' . h($status) . '</span>';
}

// Validates a Y-m-d date for booking. Returns an error message or '' if fine.
function booking_date_error(string $date, array $doctorDays): string
{
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return 'Choose a valid date.';
    }
    $today = new DateTime('today');
    if ($d < $today) {
        return 'That date has passed. Choose today or a later date.';
    }
    if ($d > (clone $today)->modify('+' . BOOKING_DAYS_AHEAD . ' days')) {
        return 'You can book up to ' . BOOKING_DAYS_AHEAD . ' days ahead.';
    }
    if (!in_array($d->format('l'), $doctorDays, true)) {
        return 'The doctor has no clinic on ' . $d->format('l') . '. Clinic days: ' . implode(', ', $doctorDays) . '.';
    }
    return '';
}

// Slots already taken (not cancelled) for a doctor on a date.
function taken_slots(PDO $pdo, int $doctorId, string $date): array
{
    $stmt = $pdo->prepare("SELECT time_slot FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status <> 'Cancelled'");
    $stmt->execute([$doctorId, $date]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// "Tomorrow", "Today", or "Mon, 5 Oct"
function friendly_date(string $date): string
{
    $d = new DateTime($date);
    $diff = (int) (new DateTime('today'))->diff($d)->format('%r%a');
    if ($diff === 0) return 'Today';
    if ($diff === 1) return 'Tomorrow';
    return $d->format('D, j M Y');
}