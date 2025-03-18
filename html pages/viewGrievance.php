<?php
session_start();
if (isset($_SESSION["logged_in"]) !== true) {
    header("Location: login.php");
    exit;
}

include 'db.php';
require_once '../send_mail.php';

// Get grievance ID from URL parameter
$grievance_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch grievance details from database
$grievance = null;
if ($grievance_id) {
    $query = "SELECT g.*, l.lab_name, d.device_name 
              FROM grievances g 
              LEFT JOIN labs l ON g.lab_id = l.lab_id 
              LEFT JOIN devices d ON g.device_id = d.device_id 
              WHERE g.grievance_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $grievance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $grievance = $result->fetch_assoc();
    } else {
        // Redirect if grievance not found
        header("Location: grievance.php");
        exit;
    }
    $stmt->close();
}

// Process status update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $admin_note = $_POST['status_note'];

    $update_query = "UPDATE grievances SET status = ?, admin_note = ? WHERE grievance_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $new_status, $admin_note, $grievance_id);

    if ($stmt->execute()) {
        // Send email notification to student
        $stud_email = $grievance['stud_email'];
        $sender_name = isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator';
        $grievance_id_formatted = $grievance_id;

        // Send email notification
        sendGrievanceEmail(
            $stud_email,
            $sender_name,
            $admin_note,
            $new_status,
            $grievance_id_formatted
        );

        // Refresh page to show updated data
        header("Location: viewGrievance.php?id=$grievance_id&updated=1");
        exit;
    }
    $stmt->close();
}

// Process grievance deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_grievance'])) {
    $reason = $_POST['delete_reason'];

    // Send deletion notification email to student first
    $stud_email = $grievance['stud_email'];
    $sender_name = isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator';
    $grievance_id_formatted = $grievance_id;

    // Format message for deletion email
    $message = "Your grievance (ID: $grievance_id_formatted) has been deleted for the following reason:\n\n$reason";

    // Send email notification
    sendGrievanceEmail(
        $stud_email,
        $sender_name,
        $message,
        "Deleted",
        $grievance_id_formatted
    );

    // Delete the grievance
    $delete_query = "DELETE FROM grievances WHERE grievance_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $grievance_id);

    if ($stmt->execute()) {
        // Redirect to grievance list
        header("Location: grievance.php?deleted=1");
        exit;
    }
    $stmt->close();
}

// Helper function to format status class
function getStatusClass($status)
{
    switch ($status) {
        case 'Submitted':
            return 'grievance-new';
        case 'Under Review':
            return 'greivance-in-progress';
        case 'In Progress':
            return 'greivance-in-progress';
        case 'Resolved':
        case 'Closed':
            return 'grievance-resolved';
        default:
            return '';
    }
}

// Helper function to get status icon
function getStatusIcon($status)
{
    switch ($status) {
        case 'Submitted':
            return '<i class="fas fa-paper-plane"></i>';
        case 'Under Review':
            return '<i class="fas fa-search"></i>';
        case 'In Progress':
            return '<i class="fas fa-spinner"></i>';
        case 'Resolved':
            return '<i class="fas fa-check-circle"></i>';
        case 'Closed':
            return '<i class="fas fa-lock"></i>';
        default:
            return '';
    }
}

// Format grievance ID
$grievance_id_formatted = $grievance_id;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lab Monitoring System - View Grievance</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js"
        integrity="sha512-L0Shl7nXXzIlBSUUPpxrokqq4ojqgZFQczTYlGjzONGTDAcLremjwaWv5A+EDLnxhQzY5xUZPWLOLqYRkY0Cbw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Include Sparkline Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js"></script>

    <link rel="stylesheet" href="../public/css/style.css" />
    <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,200,0,200&icon_names=dns" />
    <link
        href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span>
            </div>
            <hr class="solid" />
            <ul class="menu">
                <li class="menu-title">Menu</li>
                <!-- Dashboard Link -->
                <li>
                    <a href="index.php">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="labs.php">
                        <i class="fa-solid fa-network-wired"></i>
                        Labs
                    </a>
                </li>
                <li>
                    <a href="addLab.php">
                        <i class="fa-solid fa-plus"></i></i><span>Add Lab</span>
                    </a>
                </li>
                <li>
                    <a href="addDevice.php">
                        <i class="fa-solid fa-plus"></i></i><span>Add Devices</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php">
                        <i class="fa-solid fa-warehouse"></i> Inventory
                    </a>
                </li>
                <li>
                    <a href="grievance.php">
                        <i class="fa-solid fa-paper-plane"></i> Grievance
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <!-- User Info Section -->
                <div class="sub-heading">
                    <span>View Grievance </span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale">
                        <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator'; ?>
                    </span>
                </div>
            </div>

            <?php if ($grievance): ?>
                <!-- Success message if status was updated -->
                <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Grievance status updated successfully.
                    </div>
                <?php endif; ?>

                <!-- Grievance Details Section -->
                <div class="view-header">
                    <span class="top-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-alert-circle">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                    </span>
                    <h2>Grievance Details</h2>
                    <span class="grievance-status <?php echo getStatusClass($grievance['status']); ?> float-right">
                        <?php echo getStatusIcon($grievance['status']); ?> <?php echo $grievance['status']; ?>
                    </span>
                </div>

                <!-- Grievance Overview Card -->
                <div class="view-card">
                    <div class="details-header">
                        <span class="top-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-clipboard-list">
                                <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                <path d="M12 11h4" />
                                <path d="M12 16h4" />
                                <path d="M8 11h.01" />
                                <path d="M8 16h.01" />
                            </svg>
                        </span>
                        <h3>Grievance Overview</h3>
                    </div>
                    <div class="details-content">
                        <div class="input-group">
                            <label class="detail-label">Grievance ID</label>
                            <div class="label-value"><?php echo $grievance_id_formatted; ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Student Name</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['submitted_by']); ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Student Enrollment</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['stud_enrollment']); ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Device ID</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['device_id']); ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Device Name</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['device_name']); ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Device Category</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['device_category']); ?></div>
                        </div>
                        <div>
                            <label class="detail-label">Lab</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['lab_id']); ?>
                                <?php echo !empty($grievance['lab_name']) ? '- ' . htmlspecialchars($grievance['lab_name']) : ''; ?>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Class</label>
                            <div class="label-value"><?php echo htmlspecialchars($grievance['class']); ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Submission Date</label>
                            <div class="label-value"><?php echo date('d-m-Y', strtotime($grievance['submission_date'])); ?></div>
                        </div>
                        <div class="input-group">
                            <label class="detail-label">Status</label>
                            <div class="label-value grievance-status <?php echo getStatusClass($grievance['status']); ?>"><?php echo $grievance['status']; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Grievance Description Card -->
                <div class="view-card">
                    <div class="details-header">
                        <span class="top-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-file-text">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <line x1="10" y1="9" x2="8" y2="9" />
                            </svg>
                        </span>
                        <h3>Grievance Description</h3>
                    </div>
                    <div class="grievance-description">
                        <p>
                            <?php echo nl2br(htmlspecialchars($grievance['grievance_desc'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Admin Note Card - Only shown if there is an admin note -->
                <?php if (!empty($grievance['admin_note'])): ?>
                    <div class="view-card">
                        <div class="details-header">
                            <span class="top-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    stroke="#3498db" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-file-cog">
                                    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                    <path d="m3.2 12.9-.9-.4" />
                                    <path d="m3.2 15.1-.9.4" />
                                    <path d="M4.677 21.5a2 2 0 0 0 1.313.5H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v2.5" />
                                    <path d="m4.9 11.2-.4-.9" />
                                    <path d="m4.9 16.8-.4.9" />
                                    <path d="m7.5 10.3-.4.9" />
                                    <path d="m7.5 17.7-.4-.9" />
                                    <path d="m9.7 12.5-.9.4" />
                                    <path d="m9.7 15.5-.9-.4" />
                                    <circle cx="6" cy="14" r="3" />
                                </svg>
                            </span>
                            <h3>Admin/Lab In-Charge Grievance Note</h3>
                        </div>
                        <div class="grievance-description">
                            <p>
                                <?php echo nl2br(htmlspecialchars($grievance['admin_note'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions Card -->
                <div class="view-card">
                    <div class="details-header">
                        <span class="top-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 24 24" fill="none"
                                stroke="#3498db" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-arrow-up-right">
                                <path d="M7 7h10v10" />
                                <path d="M7 17 17 7" />
                            </svg>
                        </span>
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="action-content">
                        <div class="quick-action">
                            <button class="btnPopup btnPopup-edit btnDetails" id="edit-status-trigger">
                                <span class="top-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-pencil">
                                        <path
                                            d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />
                                        <path d="m15 5 4 4" />
                                    </svg>
                                </span>
                                Edit Status & Note
                            </button>
                        </div>
                        <div class="quick-action">
                            <button class="btnPopup btnPopup-delete btnDetails" id="delete-trigger">
                                <span class="top-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="white" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-trash-2">
                                        <path d="M3 6h18" />
                                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                                        <line x1="10" y1="11" x2="10" y2="17" />
                                        <line x1="14" y1="11" x2="14" y2="17" />
                                    </svg>
                                </span>
                                Delete Grievance
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Grievance not found.
                </div>
                <div class="back-button">
                    <a href="grievance.php" class="btnPopup btnPopup-primary">Return to Grievance List</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- POP UP FOR DELETE -->
        <div id="delete-popup" class="popupDelte-up-overlay">
            <div class="popupDelte">
                <div class="popupDelte-header">
                    <div class="popupDelte-icon">
                        <i class="fa-solid fa-trash"></i>
                    </div>
                    <h2 class="popupDelte-title">Confirm Deletion</h2>
                </div>

                <form method="POST" action="">
                    <div class="popupDelte-body">
                        <p class="delete-text">Are you sure you want to delete this grievance?</p>
                        <div class="item-name" id="item-name">
                            <?php if ($grievance): ?>
                                <?php echo htmlspecialchars($grievance['device_name']); ?> - <?php echo $grievance_id_formatted; ?>
                            <?php endif; ?>
                        </div>

                        <p class="delete-warning padding-bottom-1">This action cannot be undone. The grievance will be
                            permanently removed from the system.</p>
                        <textarea name="delete_reason" required
                            placeholder="Enter the reason here (this will be shared with the student)"
                            class="form-input"></textarea>
                    </div>

                    <div class="popupDelte-footer">
                        <button type="button" class="btnPopup btnPopup-cancel" id="cancel-btnPopup">Cancel</button>
                        <button type="submit" name="delete_grievance" class="btnPopup btnPopup-delete" id="confirm-delete-btnPopup">Delete</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- pop up for status change -->
        <div id="edit-status-modal" class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <div class="modal-icon icon-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <h2 class="modal-title">Update Grievance Status</h2>
                </div>

                <form method="POST" action="">
                    <div class="modal-body">
                        <p class="status-text margin-bottom-half">Update the status for grievance:</p>
                        <div class="item-name-new-new margin-bottom-half" id="grievance-id">
                            <?php echo $grievance_id_formatted; ?>
                        </div>

                        <div class="status-selection">
                            <label for="status-select" class="status-label">Select Status</label>
                            <select id="status-select" name="status" class="status-select" required>
                                <option value="">Select Status</option>
                                <option value="Submitted" <?php echo ($grievance && $grievance['status'] == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                                <option value="Under Review" <?php echo ($grievance && $grievance['status'] == 'Under Review') ? 'selected' : ''; ?>>Under Review</option>
                                <option value="In Progress" <?php echo ($grievance && $grievance['status'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Resolved" <?php echo ($grievance && $grievance['status'] == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                                <option value="Closed" <?php echo ($grievance && $grievance['status'] == 'Closed') ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>

                        <div class="status-note">
                            <label for="status-note" class="note-label">Status Note</label>
                            <textarea id="status-note" name="status_note" class="note-textarea" required
                                placeholder="Add details about this status change... (this will be shared with the student as update)"><?php echo ($grievance && !empty($grievance['admin_note'])) ? htmlspecialchars($grievance['admin_note']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btnModal btnModal-secondary" id="cancel-btnModal">Cancel</button>
                        <button type="submit" name="update_status" class="btnModal btnModal-primary btnModal-blue" id="save-status-btnModal">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        // Edit Status Modal
        const editStatusTrigger = document.getElementById('edit-status-trigger');
        const editStatusModal = document.getElementById('edit-status-modal');
        const editCancelBtnModal = document.getElementById('cancel-btnModal');

        if (editStatusTrigger && editStatusModal) {
            editStatusTrigger.addEventListener('click', function() {
                editStatusModal.style.display = 'flex';
            });

            if (editCancelBtnModal) {
                editCancelBtnModal.addEventListener('click', function() {
                    editStatusModal.style.display = 'none';
                });
            }

            editStatusModal.addEventListener('click', function(e) {
                if (e.target === editStatusModal) {
                    editStatusModal.style.display = 'none';
                }
            });
        }

        // Delete Confirmation Modal
        const deleteBtnPopup = document.getElementById('delete-trigger');
        const popupDelete = document.getElementById('delete-popup');
        const cancelBtnPopup = document.getElementById('cancel-btnPopup');

        if (deleteBtnPopup && popupDelete) {
            deleteBtnPopup.addEventListener('click', function() {
                popupDelete.style.display = 'flex';
            });

            if (cancelBtnPopup) {
                cancelBtnPopup.addEventListener('click', function() {
                    popupDelete.style.display = 'none';
                });
            }

            popupDelete.addEventListener('click', function(e) {
                if (e.target === popupDelete) {
                    popupDelete.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>