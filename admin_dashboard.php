<?php
// admin_dashboard.php - Complete PHP Administration Panel with all CRUD Functions
require_once 'db.php';
checkRole('admin');

$success = '';
$error = '';
$view = $_GET['view'] ?? 'overview';

// HANDLING POST / OPERATION ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Add City
    if (isset($_POST['action_add_city'])) {
        $name = trim($_POST['city_name'] ?? '');
        $country = trim($_POST['city_country'] ?? 'Pakistan');
        if (empty($name)) {
            $error = "City name cannot be empty.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO cities (name, country) VALUES (?, ?)");
                $stmt->execute([$name, $country]);
                $success = "City '$name' added successfully!";
            } catch (Exception $e) {
                $error = "Error adding city: " . $e->getMessage();
            }
        }
    }

    // B. Add Doctor (Creates login + doctor record)
    if (isset($_POST['action_add_doctor'])) {
        $username = trim($_POST['doc_username'] ?? '');
        $password = trim($_POST['doc_password'] ?? '');
        $name = trim($_POST['doc_name'] ?? '');
        $specialty = trim($_POST['doc_specialty'] ?? '');
        $city_id = intval($_POST['doc_city_id'] ?? 0);
        $phone = trim($_POST['doc_phone'] ?? '');
        $email = trim($_POST['doc_email'] ?? '');
        $address = trim($_POST['doc_address'] ?? '');
        $bio = trim($_POST['doc_bio'] ?? '');
        $experience_years = intval($_POST['doc_exp'] ?? 0);
        $consultation_fee = floatval($_POST['doc_fee'] ?? 0);

        if (empty($username) || empty($password) || empty($name) || empty($phone) || empty($email) || empty($specialty) || empty($city_id)) {
            $error = "All mandatory fields must be completed to add a doctor.";
        } else {
            try {
                // Check if username taken
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtCheck->execute([$username]);
                if ($stmtCheck->rowCount() > 0) {
                    $error = "The User ID '$username' is already taken.";
                } else {
                    $pdo->beginTransaction();
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Create login
                    $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'doctor', ?)");
                    $stmtUser->execute([$username, $hashedPassword, $email]);
                    $newUserId = $pdo->lastInsertId();
                    
                    // Create doctor profile
                    $stmtDoc = $pdo->prepare("INSERT INTO doctors (user_id, name, specialty, city_id, address, phone, email, bio, experience_years, consultation_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtDoc->execute([$newUserId, $name, $specialty, $city_id, $address, $phone, $email, $bio, $experience_years, $consultation_fee]);
                    
                    $pdo->commit();
                    $success = "Doctor '$name' and login account added successfully!";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Error adding doctor: " . $e->getMessage();
            }
        }
    }

    // C. Edit Doctor Details
    if (isset($_POST['action_edit_doctor'])) {
        $doctor_id = intval($_POST['edit_doctor_id']);
        $name = trim($_POST['doc_name'] ?? '');
        $specialty = trim($_POST['doc_specialty'] ?? '');
        $city_id = intval($_POST['doc_city_id'] ?? 0);
        $phone = trim($_POST['doc_phone'] ?? '');
        $email = trim($_POST['doc_email'] ?? '');
        $address = trim($_POST['doc_address'] ?? '');
        $bio = trim($_POST['doc_bio'] ?? '');
        $experience_years = intval($_POST['doc_exp'] ?? 0);
        $consultation_fee = floatval($_POST['doc_fee'] ?? 0);

        if (empty($name) || empty($phone) || empty($email) || empty($specialty) || empty($city_id)) {
            $error = "Name, Phone, Email, Specialty, and City are mandatory.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE doctors SET name=?, specialty=?, city_id=?, address=?, phone=?, email=?, bio=?, experience_years=?, consultation_fee=? WHERE id=?");
                $stmt->execute([$name, $specialty, $city_id, $address, $phone, $email, $bio, $experience_years, $consultation_fee, $doctor_id]);
                $success = "Doctor details updated successfully!";
            } catch (Exception $e) {
                $error = "Error updating doctor: " . $e->getMessage();
            }
        }
    }

    // D. Edit Patient Details
    if (isset($_POST['action_edit_patient'])) {
        $patient_id = intval($_POST['edit_patient_id']);
        $name = trim($_POST['pat_name'] ?? '');
        $email = trim($_POST['pat_email'] ?? '');
        $phone = trim($_POST['pat_phone'] ?? '');
        $address = trim($_POST['pat_address'] ?? '');

        if (empty($name) || empty($email) || empty($phone) || empty($address)) {
            $error = "All Patient details (Name, Email, Phone, Address) are mandatory.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE patients SET name=?, email=?, phone=?, address=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $address, $patient_id]);
                $success = "Patient record updated successfully!";
            } catch (Exception $e) {
                $error = "Error updating patient: " . $e->getMessage();
            }
        }
    }

    // E. Add Disease
    if (isset($_POST['action_add_disease'])) {
        $name = trim($_POST['dis_name'] ?? '');
        $description = trim($_POST['dis_desc'] ?? '');
        $symptoms = trim($_POST['dis_symptoms'] ?? '');
        $preventions = trim($_POST['dis_preventions'] ?? '');
        $cures = trim($_POST['dis_cures'] ?? '');

        if (empty($name) || empty($description)) {
            $error = "Disease Name and Description are mandatory.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO diseases (name, description, symptoms, preventions, cures) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $symptoms, $preventions, $cures]);
                $success = "Disease '$name' added to handbook!";
            } catch (Exception $e) {
                $error = "Error adding disease: " . $e->getMessage();
            }
        }
    }

    // F. Add Medical News
    if (isset($_POST['action_add_news'])) {
        $title = trim($_POST['news_title'] ?? '');
        $summary = trim($_POST['news_summary'] ?? '');
        $content = trim($_POST['news_content'] ?? '');
        $category = trim($_POST['news_category'] ?? 'News');
        $author = trim($_POST['news_author'] ?? 'CARE Group Administration');

        if (empty($title) || empty($summary) || empty($content)) {
            $error = "Title, Summary, and Content are required for medical news.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO medical_news (title, summary, content, category, published_date, author) VALUES (?, ?, ?, ?, CURDATE(), ?)");
                $stmt->execute([$title, $summary, $content, $category, $author]);
                $success = "Medical article '$title' published!";
            } catch (Exception $e) {
                $error = "Error listing news: " . $e->getMessage();
            }
        }
    }
}

// HANDLING GET ACTIONS (DELETE OPERATIONS + AJAX EDIT SOURCE GATHERING)
if (isset($_GET['delete_city'])) {
    $city_id = intval($_GET['delete_city']);
    try {
        $stmt = $pdo->prepare("DELETE FROM cities WHERE id = ?");
        $stmt->execute([$city_id]);
        $success = "City deleted successfully.";
    } catch (Exception $e) {
        $error = "Unable to delete city. A doctor might be registered there.";
    }
}

if (isset($_GET['delete_doc'])) {
    $doc_id = intval($_GET['delete_doc']);
    try {
        // Fetch user id first to clean login logs
        $stmtFind = $pdo->prepare("SELECT user_id FROM doctors WHERE id = ?");
        $stmtFind->execute([$doc_id]);
        $uId = $stmtFind->fetchColumn();
        
        $pdo->beginTransaction();
        if ($uId) {
            $stmtDelUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDelUser->execute([$uId]);
        } else {
            $stmtDelDoc = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
            $stmtDelDoc->execute([$doc_id]);
        }
        $pdo->commit();
        $success = "Doctor and companion login deleted successfully.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error removing doctor: " . $e->getMessage();
    }
}

if (isset($_GET['delete_patient'])) {
    $pat_id = intval($_GET['delete_patient']);
    try {
        // Fetch user id first
        $stmtFind = $pdo->prepare("SELECT user_id FROM patients WHERE id = ?");
        $stmtFind->execute([$pat_id]);
        $uId = $stmtFind->fetchColumn();
        
        $pdo->beginTransaction();
        if ($uId) {
            $stmtDelUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDelUser->execute([$uId]);
        } else {
            $stmtDelPat = $pdo->prepare("DELETE FROM patients WHERE id = ?");
            $stmtDelPat->execute([$pat_id]);
        }
        $pdo->commit();
        $success = "Patient and companion login deleted successfully.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error removing patient: " . $e->getMessage();
    }
}

if (isset($_GET['delete_disease'])) {
    $dis_id = intval($_GET['delete_disease']);
    try {
        $stmt = $pdo->prepare("DELETE FROM diseases WHERE id = ?");
        $stmt->execute([$dis_id]);
        $success = "Disease handbook entry deleted.";
    } catch (Exception $e) {
        $error = "Error deleting disease: " . $e->getMessage();
    }
}

if (isset($_GET['delete_news'])) {
    $news_id = intval($_GET['delete_news']);
    try {
        $stmt = $pdo->prepare("DELETE FROM medical_news WHERE id = ?");
        $stmt->execute([$news_id]);
        $success = "News article deleted.";
    } catch (Exception $e) {
        $error = "Error deleting news: " . $e->getMessage();
    }
}

// DATA RETRIEVAL FOR PANELS
$cities = $pdo->query("SELECT * FROM cities ORDER BY name ASC")->fetchAll();
$doctors = $pdo->query("SELECT d.*, c.name as city_name, u.username FROM doctors d JOIN cities c ON d.city_id = c.id JOIN users u ON d.user_id = u.id")->fetchAll();
$patients = $pdo->query("SELECT p.*, u.username FROM patients p JOIN users u ON p.user_id = u.id")->fetchAll();
$diseases = $pdo->query("SELECT * FROM diseases ORDER BY name ASC")->fetchAll();
$news_list = $pdo->query("SELECT * FROM medical_news ORDER BY published_date DESC")->fetchAll();
$appointments = $pdo->query("SELECT a.*, d.name as doc_name, p.name as pat_name FROM appointments a JOIN doctors d ON a.doctor_id = d.id JOIN patients p ON a.patient_id = p.id ORDER BY a.appointment_date DESC LIMIT 10")->fetchAll();

// Quick statistics counters
$cntCities = count($cities);
$cntDoctors = count($doctors);
$cntPatients = count($patients);
$cntAppointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

// Status Class Mapping
$statusMap = [
    'Pending' => 'bg-warning-subtle text-warning border border-warning border-opacity-25',
    'Confirmed' => 'bg-info-subtle text-info border border-info border-opacity-25',
    'Completed' => 'bg-success-subtle text-success border border-success border-opacity-25',
    'Cancelled' => 'bg-danger-subtle text-danger border border-danger border-opacity-25'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARE Group - Administrator Panel</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font-Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Dynamic Marquee Announcement Top Bar -->
    <div class="top-bar-marquee">
        <div class="container">
            <marquee behavior="scroll" direction="left" scrollamount="4" class="m-0 text-white">
                📢 <strong>Aptech eProject:</strong> Welcome to the fully synchronized CARE Group Medical Services Portal. Access dynamic specialist booking, comprehensive pre-treatment guides, and medical Innovations. 🔑 Default Admin login: <strong>admin</strong> / <strong>admin123</strong>.
            </marquee>
        </div>
    </div>

    <!-- Gorgeous Sticky Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-nav py-3" style="top: 31px;">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                <i class="fa-solid fa-heart-pulse text-teal me-2"></i>
                <div class="d-flex flex-column lh-1">
                    <span style="font-size:1.35rem; color:#ffffff;">CARE Group</span>
                    <span style="font-size:0.65rem; color:#94a3b8; letter-spacing:1px; text-transform:uppercase;">Medical Services</span>
                </div>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link text-white fw-bold active"><i class="fa-solid fa-user-shield text-teal me-1"></i> Admin Panel</span>
                    </li>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <a class="btn btn-main py-2 px-3 btn-sm" href="logout.php"><i class="fa-solid fa-sign-out-alt me-1"></i> Sign Out</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Wrapper of Administrator Panel -->
    <div class="container" style="margin-top: 130px; margin-bottom: 70px;">
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success border-0 shadow-sm text-center small mb-4 py-2" style="border-radius:10px;">
                <i class="fa-solid fa-circle-check me-1"></i> <?php echo h($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 shadow-sm text-center small mb-4 py-2" style="border-radius:10px;">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <!-- Side Navigation Column -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm p-3 bg-white" style="border-radius:20px; border: 1.5px solid #e2e8f0 !important;">
                    <div class="text-center py-3 border-bottom mb-3" style="border-color:#f1f5f9 !important;">
                        <div class="bg-teal-soft text-teal d-inline-flex align-items-center justify-content-center rounded-circle mb-2 shadow-sm" style="width: 65px; height: 65px; font-size: 1.6rem;">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <h6 class="fw-bold mb-0 text-dark">Portal Administrator</h6>
                        <span class="text-muted xsmall" style="font-weight: 500;">Core Management Engine</span>
                    </div>
                    
                    <div class="list-group list-group-flush gap-1">
                        <a href="admin_dashboard.php?view=overview" class="list-group-item list-group-item-action border-0 py-2.5 px-3 rounded-3 d-flex align-items-center justify-content-between <?php echo $view === 'overview' ? 'bg-teal-soft text-teal fw-bold' : 'text-dark small'; ?>">
                            <span><i class="fa-solid fa-chart-pie me-2 width-15"></i> Stats Overview</span>
                            <span class="badge rounded-pill bg-dark text-white font-monospace" style="font-size:0.75rem;"><?php echo $cntAppointments; ?></span>
                        </a>
                        <a href="admin_dashboard.php?view=cities" class="list-group-item list-group-item-action border-0 py-2.5 px-3 rounded-3 d-flex align-items-center justify-content-between <?php echo $view === 'cities' ? 'bg-teal-soft text-teal fw-bold' : 'text-dark small'; ?>">
                            <span><i class="fa-solid fa-map-location-dot me-2 width-15"></i> Cities Master</span>
                            <span class="badge rounded-pill bg-dark text-white font-monospace" style="font-size:0.75rem;"><?php echo $cntCities; ?></span>
                        </a>
                        <a href="admin_dashboard.php?view=doctors" class="list-group-item list-group-item-action border-0 py-2.5 px-3 rounded-3 d-flex align-items-center justify-content-between <?php echo $view === 'doctors' ? 'bg-teal-soft text-teal fw-bold' : 'text-dark small'; ?>">
                            <span><i class="fa-solid fa-user-md me-2 width-15"></i> Doctors Roster</span>
                            <span class="badge rounded-pill bg-dark text-white font-monospace" style="font-size:0.75rem;"><?php echo $cntDoctors; ?></span>
                        </a>
                        <a href="admin_dashboard.php?view=patients" class="list-group-item list-group-item-action border-0 py-2.5 px-3 rounded-3 d-flex align-items-center justify-content-between <?php echo $view === 'patients' ? 'bg-teal-soft text-teal fw-bold' : 'text-dark small'; ?>">
                            <span><i class="fa-solid fa-hospital-user me-2 width-15"></i> Patients Directory</span>
                            <span class="badge rounded-pill bg-dark text-white font-monospace" style="font-size:0.75rem;"><?php echo $cntPatients; ?></span>
                        </a>
                        <a href="admin_dashboard.php?view=diseases" class="list-group-item list-group-item-action border-0 py-2.5 px-3 rounded-3 d-flex align-items-center justify-content-between <?php echo $view === 'diseases' ? 'bg-teal-soft text-teal fw-bold' : 'text-dark small'; ?>">
                            <span><i class="fa-solid fa-book-medical me-2 width-15"></i> Handbook Editor</span>
                            <span class="badge rounded-pill bg-dark text-white font-monospace" style="font-size:0.75rem;"><?php echo count($diseases); ?></span>
                        </a>
                        <a href="admin_dashboard.php?view=news" class="list-group-item list-group-item-action border-0 py-2.5 px-3 rounded-3 d-flex align-items-center justify-content-between <?php echo $view === 'news' ? 'bg-teal-soft text-teal fw-bold' : 'text-dark small'; ?>">
                            <span><i class="fa-solid fa-rss me-2 width-15"></i> Research Blog</span>
                            <span class="badge rounded-pill bg-dark text-white font-monospace" style="font-size:0.75rem;"><?php echo count($news_list); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dynamic Content Area Column -->
            <div class="col-lg-9">
                
                <!-- VIEW A: OVERVIEW / STATS -->
                <?php if ($view === 'overview'): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-gauge-high text-teal fs-4"></i>
                        <h4 class="fw-bold mb-0 text-dark">System Metrics & Overview</h4>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="card border-0 shadow-sm p-4 bg-white text-center h-100" style="border-radius:15px; border: 1.5px solid #e2e8f0 !important;">
                                <i class="fa-solid fa-user-md text-teal mb-2 fs-3"></i>
                                <span class="text-muted small d-block mb-1">Registered Doctors</span>
                                <h3 class="fw-bold text-dark m-0"><?php echo $cntDoctors; ?></h3>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card border-0 shadow-sm p-4 bg-white text-center h-100" style="border-radius:15px; border: 1.5px solid #e2e8f0 !important;">
                                <i class="fa-solid fa-hospital-user mb-2 fs-3 text-primary" style="color: #6366f1 !important;"></i>
                                <span class="text-muted small d-block mb-1">Active Patients</span>
                                <h3 class="fw-bold text-dark m-0"><?php echo $cntPatients; ?></h3>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card border-0 shadow-sm p-4 bg-white text-center h-100" style="border-radius:15px; border: 1.5px solid #e2e8f0 !important;">
                                <i class="fa-solid fa-circle-nodes text-warning mb-2 fs-3"></i>
                                <span class="text-muted small d-block mb-1">Locations Listed</span>
                                <h3 class="fw-bold text-dark m-0"><?php echo $cntCities; ?></h3>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card border-0 shadow-sm p-4 bg-white text-center h-100" style="border-radius:15px; border: 1.5px solid #e2e8f0 !important;">
                                <i class="fa-solid fa-calendar-check text-success mb-2 fs-3"></i>
                                <span class="text-muted small d-block mb-1">Total Appointments</span>
                                <h3 class="fw-bold text-dark m-0"><?php echo $cntAppointments; ?></h3>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark mt-4 d-flex align-items-center gap-2"><i class="fa-solid fa-table-list text-teal"></i> Recent Booking Log</h5>
                    <div class="table-responsive">
                        <table class="table custom-table align-middle text-start w-100">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Schedule Date</th>
                                    <th>Time Slot</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($appointments)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-secondary">No clinic appointments recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach($appointments as $app): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><i class="fa-solid fa-user-injured me-1 text-secondary"></i> <?php echo h($app['pat_name']); ?></div>
                                            </td>
                                            <td>
                                                <div class="text-secondary"><i class="fa-solid fa-user-md me-1 text-teal"></i> Dr. <?php echo h($app['doc_name']); ?></div>
                                            </td>
                                            <td>
                                                <span class="small font-monospace"><i class="fa-regular fa-calendar me-1"></i> <?php echo h($app['appointment_date']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark font-monospace border py-1.5"><i class="fa-regular fa-clock me-1 text-secondary"></i> <?php echo h($app['time_slot']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill px-2.5 py-1.5 <?php echo $statusMap[$app['status']] ?? 'bg-secondary-subtle text-secondary'; ?>"><?php echo h($app['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>


                <!-- VIEW B: CITIES MASTER CRUD -->
                <?php if ($view === 'cities'): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-map-location-dot text-teal fs-4"></i>
                        <h4 class="fw-bold mb-0 text-dark">Cities & Directions</h4>
                    </div>
                    
                    <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4" style="border: 1.5px solid #e2e8f0 !important;">
                        <h6 class="fw-bold mb-3 text-teal"><i class="fa-solid fa-plus-circle me-1"></i> Add New Destination City</h6>
                        <form action="admin_dashboard.php?view=cities" method="POST" class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0" style="border-radius:10px 0 0 10px;"><i class="fa-solid fa-city"></i></span>
                                    <input type="text" name="city_name" class="form-control border-start-0" placeholder="e.g. Peshawar" required style="border-radius:0 10px 10px 0;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0" style="border-radius:10px 0 0 10px;"><i class="fa-solid fa-globe"></i></span>
                                    <input type="text" name="city_country" class="form-control border-start-0" placeholder="Country" value="Pakistan" required style="border-radius:0 10px 10px 0;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="action_add_city" class="btn btn-main w-100 py-2 fs-6 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Location</button>
                            </div>
                        </form>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark mt-4"><i class="fa-solid fa-database text-teal me-1"></i> Active Cities database</h5>
                    <div class="table-responsive">
                        <table class="table custom-table align-middle text-start w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>City Name</th>
                                    <th>Country</th>
                                    <th class="text-end">Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cities as $ct): ?>
                                    <tr>
                                        <td><code class="font-monospace">#<?php echo $ct['id']; ?></code></td>
                                        <td><strong class="text-dark"><i class="fa-solid fa-location-dot text-teal me-1"></i> <?php echo h($ct['name']); ?></strong></td>
                                        <td><?php echo h($ct['country']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_dashboard.php?view=cities&delete_city=<?php echo $ct['id']; ?>" class="btn btn-outline-danger btn-sm border-2 fw-bold px-3 py-1.5" onclick="return confirm('Are you sure you want to delete this city? Clicking OK will delete associated doctors too.');" style="border-radius:8px;"><i class="fa-solid fa-trash-can me-1"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>


                <!-- VIEW C: DOCTORS ROSTER CRUD -->
                <?php if ($view === 'doctors'): ?>
                    <?php if (isset($_GET['edit_doc_id'])): 
                        // Fetch doctor for edit
                        $edit_id = intval($_GET['edit_doc_id']);
                        $stmtEd = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
                        $stmtEd->execute([$edit_id]);
                        $edit_doc = $stmtEd->fetch();
                    ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fa-solid fa-user-edit text-teal fs-4"></i>
                            <h4 class="fw-bold mb-0 text-dark">Modify Doctor Details</h4>
                        </div>
                        
                        <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4" style="border: 1.5px solid #e2e8f0 !important;">
                            <form action="admin_dashboard.php?view=doctors" method="POST">
                                <input type="hidden" name="edit_doctor_id" value="<?php echo $edit_doc['id']; ?>">
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Full Name *</label>
                                        <input type="text" name="doc_name" class="form-control" value="<?php echo h($edit_doc['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Specialty Area/Category *</label>
                                        <input type="text" name="doc_specialty" class="form-control" value="<?php echo h($edit_doc['specialty']); ?>" required>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Clinic City Residence *</label>
                                        <select name="doc_city_id" class="form-select">
                                            <?php foreach($cities as $ct): ?>
                                                <option value="<?php echo $ct['id']; ?>" <?php echo $ct['id'] == $edit_doc['city_id'] ? 'selected' : ''; ?>>
                                                    <?php echo h($ct['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Contact Phone *</label>
                                        <input type="text" name="doc_phone" class="form-control" value="<?php echo h($edit_doc['phone']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Doctor Business Email *</label>
                                        <input type="email" name="doc_email" class="form-control" value="<?php echo h($edit_doc['email']); ?>" required>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Practice Experience Years *</label>
                                        <input type="number" name="doc_exp" class="form-control" value="<?php echo h($edit_doc['experience_years']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Consultation Session Fee (Rs.) *</label>
                                        <input type="number" step="100" name="doc_fee" class="form-control" value="<?php echo h($edit_doc['consultation_fee']); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Clinical Chamber Address *</label>
                                    <textarea name="doc_address" class="form-control" rows="3" required><?php echo h($edit_doc['address']); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-dark">Clinical Profile CV/Bio</label>
                                    <textarea name="doc_bio" class="form-control" rows="4"><?php echo h($edit_doc['bio']); ?></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="action_edit_doctor" class="btn btn-main px-4 py-2.5 fs-6"><i class="fa-solid fa-lock-open me-1"></i> Save Updates</button>
                                    <a href="admin_dashboard.php?view=doctors" class="btn btn-outline-secondary px-4 py-2.5 fs-6 fw-bold border-2" style="border-radius:8px;">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Standard Doctors Listing + Add Form Link -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-user-md text-teal fs-4"></i>
                                <h4 class="fw-bold mb-0 text-dark">Doctors Roster & Logins</h4>
                            </div>
                            <button onclick="document.getElementById('add-doctor-panel').style.display='block'; window.scrollTo({top: document.getElementById('add-doctor-panel').offsetTop - 100, behavior: 'smooth'});" class="btn btn-main btn-sm py-2 px-3 fw-bold shadow-sm d-flex align-items-center gap-2">
                                <i class="fa-solid fa-user-plus"></i> Register Doctor
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table custom-table align-middle text-start w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Credentials</th>
                                        <th>Specialty</th>
                                        <th>City Location</th>
                                        <th>Contacts</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($doctors)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-secondary">No doctors listed. Select Register to insert your medical roster.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($doctors as $doc): ?>
                                            <tr>
                                                <td><code class="font-monospace">DOC-<?php echo $doc['id']; ?></code></td>
                                                <td>
                                                    <div class="fw-bold text-dark">Dr. <?php echo h($doc['name']); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark font-monospace border border-secondary border-opacity-10 py-1.5 px-2">Account: @<?php echo h($doc['username']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-teal-soft text-teal px-2.5 py-1.5" style="border-radius:6px;"><?php echo h($doc['specialty']); ?></span>
                                                </td>
                                                <td>
                                                    <span><i class="fa-solid fa-location-dot text-secondary small me-1"></i> <?php echo h($doc['city_name']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="small text-secondary font-monospace"><?php echo h($doc['phone']); ?></div>
                                                    <div class="xsmall text-muted font-monospace"><?php echo h($doc['email']); ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <a href="admin_dashboard.php?view=doctors&edit_doc_id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary border-2 fw-bold px-2 py-1.5" style="border-radius:8px;"><i class="fa-solid fa-edit"></i> Edit</a>
                                                        <a href="admin_dashboard.php?view=doctors&delete_doc=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-danger border-2 fw-bold px-2 py-1.5" onclick="return confirm('Click OK to delete doctor and wipe associated session credentials permanently.');" style="border-radius:8px;"><i class="fa-solid fa-trash-can"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Doctor Panel (Toggled via JS) -->
                        <div id="add-doctor-panel" style="display:none;" class="card border-0 shadow-sm p-4 bg-white mt-4 rounded-4" style="border: 1.5px solid #e2e8f0 !important;">
                            <h5 class="fw-bold mb-3 text-teal border-bottom pb-2"><i class="fa-solid fa-user-plus me-1"></i> Register New Doctor Profile & System Login</h5>
                            <form action="admin_dashboard.php?view=doctors" method="POST">
                                
                                <h6 class="text-dark fw-bold mb-3 mt-2"><i class="fa-solid fa-key me-1 text-teal"></i> Step 1: User Login Configuration</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">System User ID (Username) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted border-end-0" style="border-radius:10px 0 0 10px;"><i class="fa-solid fa-at"></i></span>
                                            <input type="text" name="doc_username" class="form-control border-start-0" placeholder="e.g. drasif99" required style="border-radius:0 10px 10px 0;">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Access Password *</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted border-end-0" style="border-radius:10px 0 0 10px;"><i class="fa-solid fa-lock"></i></span>
                                            <input type="password" name="doc_password" class="form-control border-start-0" placeholder="••••••••" required style="border-radius:0 10px 10px 0;">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="text-dark fw-bold mb-3"><i class="fa-solid fa-user-md me-1 text-teal"></i> Step 2: Clinic Roster Information</h6>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Full Name *</label>
                                        <input type="text" name="doc_name" class="form-control" placeholder="Dr. Asif Kamal" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Clinic Specialty Area *</label>
                                        <input type="text" name="doc_specialty" class="form-control" placeholder="e.g. Cardiologist" required>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Clinical Office City *</label>
                                        <select name="doc_city_id" class="form-select" required>
                                            <option value="">-- Select City --</option>
                                            <?php foreach($cities as $ct): ?>
                                                <option value="<?php echo $ct['id']; ?>"><?php echo h($ct['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Personal Contact No *</label>
                                        <input type="text" name="doc_phone" class="form-control" placeholder="+92 300 1234567" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold text-dark">Professional Email Address *</label>
                                        <input type="email" name="doc_email" class="form-control" placeholder="asif@care.com" required>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Years of Clinical Experience</label>
                                        <input type="number" name="doc_exp" value="5" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Standard Session Fee (Rs.)</label>
                                        <input type="number" name="doc_fee" value="1500" step="100" class="form-control">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Chamber Room / Office Address *</label>
                                    <textarea name="doc_address" class="form-control" rows="3" placeholder="Enter clinical office chambers address details..." required></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-dark">Short Resume Bio Summary</label>
                                    <textarea name="doc_bio" class="form-control" rows="3" placeholder="Consultant internal medicine..."></textarea>
                                </div>

                                <button type="submit" name="action_add_doctor" class="btn btn-main py-2.5 w-100 fs-6"><i class="fa-solid fa-circle-check me-1"></i> Finalize Doctor Registration</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>


                <!-- VIEW D: PATIENTS DIRECTORY CRUD -->
                <?php if ($view === 'patients'): ?>
                    <?php if (isset($_GET['edit_pat_id'])): 
                        $edit_id = intval($_GET['edit_pat_id']);
                        $stmtP = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
                        $stmtP->execute([$edit_id]);
                        $edit_pat = $stmtP->fetch();
                    ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="fa-solid fa-hospital-user text-teal fs-4"></i>
                            <h4 class="fw-bold mb-0 text-dark">Modify Patient Directory Roster Sheet</h4>
                        </div>
                        
                        <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4" style="border: 1.5px solid #e2e8f0 !important;">
                            <form action="admin_dashboard.php?view=patients" method="POST">
                                <input type="hidden" name="edit_patient_id" value="<?php echo $edit_pat['id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">Patient Full Name *</label>
                                    <input type="text" name="pat_name" class="form-control" value="<?php echo h($edit_pat['name']); ?>" required>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Active Email Address *</label>
                                        <input type="email" name="pat_email" class="form-control" value="<?php echo h($edit_pat['email']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-dark">Primary Phone Contact *</label>
                                        <input type="text" name="pat_phone" class="form-control" value="<?php echo h($edit_pat['phone']); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-dark">Home Postal Address *</label>
                                    <textarea name="pat_address" class="form-control" rows="3" required><?php echo h($edit_pat['address']); ?></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="action_edit_patient" class="btn btn-main px-4 py-2.5 fs-6"><i class="fa-solid fa-check-circle me-1"></i> Save Patient Directory Updates</button>
                                    <a href="admin_dashboard.php?view=patients" class="btn btn-outline-secondary px-4 py-2.5 fs-6 fw-bold border-2" style="border-radius:8px;">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <i class="fa-solid fa-hospital-user text-teal fs-4"></i>
                            <h4 class="fw-bold mb-0 text-dark">Patients Master Directory</h4>
                        </div>

                        <div class="table-responsive">
                            <table class="table custom-table align-middle text-start w-100">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Username ID</th>
                                        <th>Full Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Registered Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($patients)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-secondary">No patients registered in the clinic system yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($patients as $pat): ?>
                                            <tr>
                                                <td><code class="font-monospace">PAT-<?php echo $pat['id']; ?></code></td>
                                                <td><strong class="text-teal">@<?php echo h($pat['username']); ?></strong></td>
                                                <td><strong class="text-dark"><?php echo h($pat['name']); ?></strong></td>
                                                <td><code class="font-monospace text-secondary"><?php echo h($pat['phone']); ?></code></td>
                                                <td><?php echo h($pat['email']); ?></td>
                                                <td><span class="xsmall font-monospace text-muted"><?php echo h($pat['registered_date']); ?></span></td>
                                                <td class="text-end">
                                                    <div class="d-inline-flex gap-1">
                                                        <a href="admin_dashboard.php?view=patients&edit_pat_id=<?php echo $pat['id']; ?>" class="btn btn-sm btn-outline-primary border-2 fw-bold px-2 py-1.5" style="border-radius:8px;"><i class="fa-solid fa-edit"></i> Edit</a>
                                                        <a href="admin_dashboard.php?view=patients&delete_patient=<?php echo $pat['id']; ?>" class="btn btn-sm btn-outline-danger border-2 fw-bold px-2 py-1.5" onclick="return confirm('Click OK to delete patient profile and credentials forever.');" style="border-radius:8px;"><i class="fa-solid fa-trash-can"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>


                <!-- VIEW E: DISEASES HANDBOOK EDITOR -->
                <?php if ($view === 'diseases'): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-book-medical text-teal fs-4"></i>
                        <h4 class="fw-bold mb-0 text-dark">Clinic Diseases Handbook Editor</h4>
                    </div>

                    <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4" style="border: 1.5px solid #e2e8f0 !important;">
                        <h6 class="fw-bold mb-3 text-teal"><i class="fa-solid fa-file-medical-alt me-1"></i> Publish Disease & Pre-Treatment Advice Reference</h6>
                        <form action="admin_dashboard.php?view=diseases" method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Disease Name *</label>
                                <input type="text" name="dis_name" class="form-control" placeholder="e.g. Malaria" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Description / Medical Overview *</label>
                                <textarea name="dis_desc" class="form-control" rows="3" placeholder="Provide clinical descriptions..." required></textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Symptoms (Comma-separated list)</label>
                                    <input type="text" name="dis_symptoms" class="form-control" placeholder="fever, chills, shivering, headache">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Preventions (Comma-separated list)</label>
                                    <input type="text" name="dis_preventions" class="form-control" placeholder="using repellent sprays, insect bed nets">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-dark">Clinical Cures / Remedial Medicine</label>
                                <input type="text" name="dis_cures" class="form-control" placeholder="Antimalarial drug regimen, expert consultancy">
                            </div>
                            <button type="submit" name="action_add_disease" class="btn btn-main py-2 px-4 shadow-sm fw-bold"><i class="fa-solid fa-broadcast-tower me-1"></i> Publish Disease Intel</button>
                        </form>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark mt-4"><i class="fa-solid fa-folder-open text-teal"></i> Published Disease Reference Entries</h5>
                    <div class="table-responsive">
                        <table class="table custom-table align-middle text-start w-100">
                            <thead>
                                <tr>
                                    <th>Disease Name</th>
                                    <th>Symptoms</th>
                                    <th>Preventions</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($diseases as $dis): ?>
                                    <tr>
                                        <td><strong><i class="fa-solid fa-viruses text-danger me-1"></i> <?php echo h($dis['name']); ?></strong></td>
                                        <td class="small text-secondary"><?php echo h($dis['symptoms']); ?></td>
                                        <td class="small text-secondary"><?php echo h($dis['preventions']); ?></td>
                                        <td class="text-end">
                                            <a href="admin_dashboard.php?view=diseases&delete_disease=<?php echo $dis['id']; ?>" class="btn btn-outline-danger btn-sm border-2 fw-bold px-3 py-1.5" onclick="return confirm('Ensure wipe disease from dictionary?');" style="border-radius:8px;"><i class="fa-solid fa-trash-can me-1"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>


                <!-- VIEW F: NEWS BLOG EDITOR -->
                <?php if ($view === 'news'): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-rss text-teal fs-4"></i>
                        <h4 class="fw-bold mb-0 text-dark">Medical News & Inventions Console</h4>
                    </div>

                    <div class="card border-0 shadow-sm p-4 bg-white mb-4 rounded-4" style="border: 1.5px solid #e2e8f0 !important;">
                        <h6 class="fw-bold mb-3 text-teal"><i class="fa-solid fa-newspaper me-1"></i> Broadcast Research Invention / Clinical Breakthrough</h6>
                        <form action="admin_dashboard.php?view=news" method="POST">
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold text-dark">Article Title *</label>
                                    <input type="text" name="news_title" class="form-control" placeholder="e.g. New Insulin Sensitizer FDA Approved" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-dark">Article Category *</label>
                                    <select name="news_category" class="form-select">
                                        <option value="News">News</option>
                                        <option value="Invention">Invention</option>
                                        <option value="Research">Research</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Short Summary *</label>
                                <textarea name="news_summary" class="form-control" rows="2" placeholder="Summarize findings in 2 lines..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Main Articles Web Content *</label>
                                <textarea name="news_content" class="form-control" rows="5" placeholder="Detailed research content or invention documentation writeup..." required></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-dark">Author Credit Name *</label>
                                <input type="text" name="news_author" class="form-control" value="CARE Group Editor" required>
                            </div>
                            <button type="submit" name="action_add_news" class="btn btn-main py-2 px-4 shadow-sm fw-bold"><i class="fa-solid fa-bullhorn me-1"></i> Broadcast Article</button>
                        </form>
                    </div>

                    <h5 class="fw-bold mb-3 text-dark mt-4"><i class="fa-solid fa-history text-teal"></i> Published Broadcasting Registry Logs</h5>
                    <div class="table-responsive">
                        <table class="table custom-table align-middle text-start w-100">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Title</th>
                                    <th>Author Credit</th>
                                    <th>Published Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($news_list as $news): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="fa-solid fa-tags text-teal small me-1"></i> <?php echo h($news['category']); ?></span>
                                        </td>
                                        <td><strong><?php echo h($news['title']); ?></strong></td>
                                        <td><code class="font-monospace text-secondary"><?php echo h($news['author']); ?></code></td>
                                        <td><span class="small font-monospace text-muted"><?php echo h($news['published_date']); ?></span></td>
                                        <td class="text-end">
                                            <a href="admin_dashboard.php?view=news&delete_news=<?php echo $news['id']; ?>" class="btn btn-outline-danger btn-sm border-2 fw-bold px-3 py-1.5" onclick="return confirm('Wipe article?');" style="border-radius:8px;"><i class="fa-solid fa-trash-can me-1"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

    <!-- Fine Footer Alignment -->
    <footer class="text-white pt-5 pb-4 bg-dark">
        <div class="container text-md-start text-center">
            <div class="row g-4">
                <div class="col-md-8">
                    <h5 class="fw-bold text-light"><i class="fa-solid fa-heart-pulse text-teal me-1"></i> CARE Group Medical Services</h5>
                    <p class="text-secondary small max-width-600 mb-0 mt-2">Accredited clinics roster matched with digital schedule management databases. Developed for Aptech IT Computer Science portals.</p>
                </div>
                <div class="col-md-4 text-md-end text-center mt-3 mt-md-0">
                    <a href="index.php" class="btn btn-sm btn-outline-light px-3 py-2"><i class="fa-solid fa-arrow-left me-1"></i> Go Back Home</a>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <p class="text-secondary xsmall m-0 text-center text-md-start">&copy; 2026 CARE Group Medical Services. Developed in compliance with Aptech eProject guidelines.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
