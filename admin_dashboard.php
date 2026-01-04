<?php

/**
 * Admin Dashboard
 * Manage users, assign privileges, create accounts
 * 🔒 ONLY ACCESSIBLE TO ADMIN USERS
 */

// 🔒 Require login
require_once 'includes/auth.php';
requireLogin();

require_once 'config/database.php';
require_once 'includes/audit.php';

// Get current user
$user = getCurrentUser();

// 🔒 Check if user is admin
if (!$user || $user['role'] !== 'admin') {
    header('Location: index.php?error=Unauthorized: Only administrators can access this area');
    exit;
}

// Include header
include 'includes/header.php';

// Handle form submissions
$message = '';
$messageType = '';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'operador';

        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            $message = 'Please fill all required fields';
            $messageType = 'danger';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters';
            $messageType = 'danger';
        } else {
            try {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Username already exists';
                    $messageType = 'danger';
                } else {
                    // Create password hash
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

                    // Insert new user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, full_name, password_hash, role, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, 1, NOW())
                    ");
                    $stmt->execute([$username, $email, $full_name, $password_hash, $role]);

                    $newUserId = $pdo->lastInsertId();

                    // Log audit
                    logAction('user_created', 'users', $newUserId, 'Admin created new user account: ' . $username . ' (' . $role . ')', $username);

                    $message = '✅ User "' . htmlspecialchars($username) . '" created successfully';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'Error creating user: ' . $e->getMessage();
                $messageType = 'danger';
                error_log("[ADMIN] Error creating user: " . $e->getMessage());
            }
        }
    } elseif ($action === 'update_privilege') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_role = $_POST['role'] ?? 'operador';

        // Prevent updating own role
        if ($user_id == $user['id']) {
            $message = 'You cannot change your own privilege level';
            $messageType = 'warning';
        } else {
            try {
                // Get old role for comparison
                $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);

                // Update role
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);

                // Log audit
                logAction('privilege_changed', 'users', $user_id, 'Privilege changed from ' . $oldUser['role'] . ' to ' . $new_role, $oldUser['username']);

                $message = '✅ User privilege updated successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error updating privilege: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'reset_password') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $new_password = $_POST['password'] ?? '';

        if (strlen($new_password) < 6) {
            $message = 'Password must be at least 6 characters';
            $messageType = 'danger';
        } else {
            try {
                // Get username
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                $password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);

                // Log audit
                logAction('password_reset', 'users', $user_id, 'Password reset by admin', $userData['username']);

                $message = '✅ Password reset successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error resetting password: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'deactivate_user') {
        $user_id = intval($_POST['user_id'] ?? 0);

        // Prevent deactivating own account
        if ($user_id == $user['id']) {
            $message = 'You cannot deactivate your own account';
            $messageType = 'warning';
        } else {
            try {
                // Get username
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                $stmt->execute([$user_id]);

                // Log audit
                logAction('user_deactivated', 'users', $user_id, 'User account deactivated', $userData['username']);

                $message = '✅ User deactivated successfully';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error deactivating user: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get all users
try {
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[ADMIN] Error fetching users: " . $e->getMessage());
    $allUsers = [];
}

?>

<div class="container-fluid py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-cog me-2"></i>Admin Dashboard</h2>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Home
                </a>
            </div>
        </div>
    </div>





    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Create New User Form -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Create New User</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_user">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <small class="text-muted">Min 3 characters, no spaces</small>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Min 6 characters</small>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Privilege Level <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="visualizacao">👁️ Visualization (View-only)</option>
                                <option value="operador" selected>⚙️ Operator (Create/Edit own)</option>
                                <option value="supervisor">📋 Supervisor (Edit all)</option>
                                <option value="admin">🔑 Admin (Full access)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Create User
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>System Users (<?php echo count($allUsers); ?>)</h5>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <?php if (empty($allUsers)): ?>
                        <p class="text-muted">No users found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allUsers as $u): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                                <?php if ($u['id'] == $user['id']): ?>
                                                    <span class="badge bg-success ms-1">(You)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($u['email']); ?></small></td>
                                            <td>
                                                <?php
                                                $roleEmoji = [
                                                    'admin' => '🔑 Admin',
                                                    'supervisor' => '📋 Supervisor',
                                                    'operador' => '⚙️ Operator',
                                                    'visualizacao' => '👁️ View-only'
                                                ];
                                                echo $roleEmoji[$u['role']] ?? ucfirst($u['role']);
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($u['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="setUserData(<?php echo htmlspecialchars(json_encode(['id' => $u['id'], 'username' => $u['username'], 'role' => $u['role']])); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#passwordModal" onclick="setUserId(<?php echo $u['id']; ?>)">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <?php if ($u['id'] != $user['id']): ?>
                                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateModal" onclick="setUserId(<?php echo $u['id']; ?>)">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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
    </div>

    <!-- Privilege Level Info -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm bg-light">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Privilege Levels</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <h6><span class="badge bg-danger">🔑 ADMIN</span></h6>
                            <ul class="small">
                                <li>Create/manage users</li>
                                <li>Assign privileges</li>
                                <li>Delete any report</li>
                                <li>View all reports</li>
                                <li>Full system access</li>
                            </ul>
                        </div>
                        <div class="col-md-3 mb-3">
                            <h6><span class="badge bg-warning">📋 SUPERVISOR</span></h6>
                            <ul class="small">
                                <li>Edit all reports</li>
                                <li>Approve/review</li>
                                <li>View all reports</li>
                                <li>Cannot delete</li>
                                <li>No user management</li>
                            </ul>
                        </div>
                        <div class="col-md-3 mb-3">
                            <h6><span class="badge bg-primary">⚙️ OPERATOR</span></h6>
                            <ul class="small">
                                <li>Create reports</li>
                                <li>Edit own reports</li>
                                <li>View own reports</li>
                                <li>Cannot delete</li>
                                <li>Limited access</li>
                            </ul>
                        </div>
                        <div class="col-md-3 mb-3">
                            <h6><span class="badge bg-secondary">👁️ VIEW-ONLY</span></h6>
                            <ul class="small">
                                <li>View only</li>
                                <li>Cannot create</li>
                                <li>Cannot edit</li>
                                <li>Cannot delete</li>
                                <li>Read-only access</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Modal: Cable Brand -->
<div class="modal fade" id="cblBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cblBrandModalTitle">Add Cable Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="cblBrandForm">
                <input type="hidden" id="cblBrandId" value="">
                <div class="modal-body">
                    <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cblBrandName" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cable Model -->
<div class="modal fade" id="cblModelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cblModelModalTitle">Add Cable Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="cblModelForm">
                <input type="hidden" id="cblModelId" value="">
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label">Brand <span class="text-danger">*</span></label>
                            <select id="cblModelBrandId" class="form-select" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model Name <span class="text-danger">*</span></label>
                            <input type="text" id="cblModelName" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Section</label>
                            <input type="text" id="cblSection" class="form-control" placeholder="e.g., 16 mm²">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Voltage rating</label>
                            <input type="text" id="cblVoltage" class="form-control" placeholder="e.g., 0.6/1 kV">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Temperature rating</label>
                            <input type="text" id="cblTemp" class="form-control" placeholder="e.g., 90°C">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label">Conductor material</label>
                            <input type="text" id="cblMaterial" class="form-control" placeholder="e.g., Copper/Aluminium">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Insulation type</label>
                            <input type="text" id="cblInsulation" class="form-control" placeholder="e.g., XLPE">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Characteristics</label>
                        <textarea id="cblCharacteristics" class="form-control" rows="3" placeholder="Armouring, sheath, standard, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal: Update Role -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Privilege Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_privilege">
                <input type="hidden" name="user_id" id="roleUserId">
                <div class="modal-body">
                    <p>Update privilege for: <strong id="roleUsername"></strong></p>
                    <div class="mb-3">
                        <label for="roleSelect" class="form-label">New Role</label>
                        <select class="form-select" id="roleSelect" name="role" required>
                            <option value="visualizacao">👁️ Visualization (View-only)</option>
                            <option value="operador">⚙️ Operator (Create/Edit own)</option>
                            <option value="supervisor">📋 Supervisor (Edit all)</option>
                            <option value="admin">🔑 Admin (Full access)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reset Password -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="passwordUserId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Enter a new password for this user
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" name="password" required>
                        <small class="text-muted">Min 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Deactivate User -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Deactivate User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="deactivate_user">
                <input type="hidden" name="user_id" id="deactivateUserId">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning!</strong> This user will not be able to login until re-activated.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Deactivate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Data Management Tabs -->
<style>
    /* Tabs styling: darker blue active, white text */
    #dataTabs .nav-link {
        color: #1570EF;
        /* slightly deeper blue for inactive */
        font-weight: 600;
        border-radius: .6rem;
    }

    #dataTabs .nav-link.active {
        background-color: #0A58CA;
        /* darker bootstrap primary */
        color: #fff !important;
        box-shadow: 0 2px 6px rgba(10, 88, 202, 0.35);
    }

    #dataTabs .nav-link:hover:not(.active) {
        color: #0B5ED7;
    }
</style>
<div class="row mt-5">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <ul class="nav nav-pills" id="dataTabs">
                    <li class="nav-item"><button type="button" class="nav-link active" data-section="section-responsibles" aria-selected="true" onclick="activateDataTab('section-responsibles', this)"><i class="fas fa-users-cog me-1"></i>Commissioning Responsibles</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-epcs" aria-selected="false" onclick="activateDataTab('section-epcs', this)"><i class="fas fa-sitemap me-1"></i>Companies & Representatives</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-pvmodules" aria-selected="false" onclick="activateDataTab('section-pvmodules', this)"><i class="fas fa-solar-panel me-1"></i>PV Modules</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-inverters" aria-selected="false" onclick="activateDataTab('section-inverters', this)"><i class="fas fa-plug-circle-bolt me-1"></i>Inverters</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-cables" aria-selected="false" onclick="activateDataTab('section-cables', this)"><i class="fas fa-cable-car me-1"></i>Cables (PV Board / POI)</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-protection" aria-selected="false" onclick="activateDataTab('section-protection', this)"><i class="fas fa-bolt me-1"></i>Circuit Protection</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-communications" aria-selected="false" onclick="activateDataTab('section-communications', this)"><i class="fas fa-broadcast-tower me-1"></i>Communications</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-smartmeters" aria-selected="false" onclick="activateDataTab('section-smartmeters', this)"><i class="fas fa-tachometer-alt me-1"></i>Smart Meters</button></li>
                    <li class="nav-item"><button type="button" class="nav-link" data-section="section-energymeters" aria-selected="false" onclick="activateDataTab('section-energymeters', this)"><i class="fas fa-bolt me-1"></i>Energy Meters</button></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Commissioning Responsibles Section -->
<div id="section-responsibles" class="row mt-3 data-section">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>Commissioning Responsibles</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="respSearchInput" class="form-control form-control-sm"
                            placeholder="🔍 Search by name, email or department...">
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-sm btn-success" id="addResponsibleBtn">
                            <i class="fas fa-plus me-1"></i>Add New Responsible
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover" id="responsiblesTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 20%;">Name</th>
                                <th style="width: 25%;">Email</th>
                                <th style="width: 15%;">Phone</th>
                                <th style="width: 20%;">Department</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="responsiblesTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Circuit Protection (Breakers & Differentials) -->
<div id="section-protection" class="row mt-3 data-section d-none">
    <div class="col-12 mb-2">
        <div class="d-flex gap-2 align-items-center">
            <label class="fw-semibold">Device type:</label>
            <select id="protDeviceType" class="form-select form-select-sm" style="width: 260px;">
                <option value="circuit_breaker">Circuit Breaker</option>
                <option value="differential">Differential</option>
            </select>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-shield-halved me-2"></i>Protection Brands</h5>
                <button class="btn btn-sm btn-light" id="protAddBrandBtn"><i class="fas fa-plus me-1"></i>Add Brand</button>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="protBrandSearch" placeholder="🔍 Search brands...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="protBrandsTbody">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Protection Models</h5>
                <div class="d-flex gap-2">
                    <select id="protModelsBrandFilter" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— All brands —</option>
                    </select>
                    <button class="btn btn-sm btn-light" id="protAddModelBtn"><i class="fas fa-plus me-1"></i>Add Model</button>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="protModelSearch" placeholder="🔍 Search models...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Brand</th>
                                <th>Characteristics</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="protModelsTbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Communications (Equipment & Models) -->
<div id="section-communications" class="row mt-3 data-section d-none">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-broadcast-tower me-2"></i>Equipment Types</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody id="commEquipTbody">
                            <tr>
                                <td class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-signal me-2"></i>Communication Models</h5>
                <div class="d-flex gap-2">
                    <select id="commModelsEquipFilter" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— All equipment —</option>
                    </select>
                    <button class="btn btn-sm btn-light" id="commAddModelBtn"><i class="fas fa-plus me-1"></i>Add Model</button>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="commModelSearch" placeholder="🔍 Search models...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Equipment</th>
                                <th>Protocols</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="commModelsTbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Protection Brand -->
<div class="modal fade" id="protBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="protBrandModalTitle">Add Brand</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="protBrandForm">
                <input type="hidden" id="protBrandId" value="">
                <div class="modal-body">
                    <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="protBrandName" required>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Protection Model -->
<div class="modal fade" id="protModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="protModelModalTitle">Add Model</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="protModelForm">
                <input type="hidden" id="protModelId" value="">
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Brand <span class="text-danger">*</span></label><select id="protModelBrandId" class="form-select" required></select></div>
                    <div class="mb-2"><label class="form-label">Model Name <span class="text-danger">*</span></label><input type="text" id="protModelName" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Characteristics</label><textarea id="protModelCharacteristics" class="form-control" rows="3" placeholder="Current rating, poles, curve, etc."></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Communications Model -->
<div class="modal fade" id="commModelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commModelModalTitle">Add Communication Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="commModelForm">
                <input type="hidden" id="commModelId" value="">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Equipment <span class="text-danger">*</span></label>
                        <select id="commModelEquipment" class="form-select" required>
                            <option value="HUB">HUB</option>
                            <option value="RUT">RUT</option>
                            <option value="Logger">Logger</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Model Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="commModelName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" class="form-control" id="commManufacturer">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Communication Protocols</label>
                        <input type="text" class="form-control" id="commProtocols" placeholder="e.g., Modbus TCP, RS485, Ethernet, etc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Power Supply</label>
                        <input type="text" class="form-control" id="commPowerSupply" placeholder="e.g., 230V AC, 48V DC, etc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Characteristics</label>
                        <textarea class="form-control" id="commCharacteristics" rows="3" placeholder="Additional specifications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Inverters (Brands & Models) -->
<div id="section-inverters" class="row mt-3 data-section d-none">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-industry me-2"></i>Inverter Brands</h5>
                <button class="btn btn-sm btn-light" id="invAddBrandBtn"><i class="fas fa-plus me-1"></i>Add Brand</button>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="invBrandSearch" placeholder="🔍 Search brands...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="invBrandsTbody">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-microchip me-2"></i>Inverter Models</h5>
                <div class="d-flex gap-2">
                    <select id="invModelsBrandFilter" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— All brands —</option>
                    </select>
                    <button class="btn btn-sm btn-light" id="invAddModelBtn"><i class="fas fa-plus me-1"></i>Add Model</button>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="invModelSearch" placeholder="🔍 Search models...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Brand</th>
                                <th>Nominal Power</th>
                                <th>MPPTs</th>
                                <th>Strings/MPPT</th>
                                <th style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="invModelsTbody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Smart Meters (Brands & Models) -->
<div id="section-smartmeters" class="row mt-3 data-section d-none">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Smart Meter Manufacturers</h5>
                <button class="btn btn-sm btn-light" id="smAddBrandBtn"><i class="fas fa-plus me-1"></i>Add Manufacturer</button>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="smBrandSearch" placeholder="🔍 Search manufacturers...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="smBrandsTbody">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-microchip me-2"></i>Smart Meter Models</h5>
                <div class="d-flex gap-2">
                    <select id="smModelsBrandFilter" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— All manufacturers —</option>
                    </select>
                    <button class="btn btn-sm btn-light" id="smAddModelBtn"><i class="fas fa-plus me-1"></i>Add Model</button>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="smModelSearch" placeholder="🔍 Search models...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Manufacturer</th>
                                <th>Protocols</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="smModelsTbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals: Brand & Model -->
    <div class="modal fade" id="smBrandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="smBrandModalTitle">Add Manufacturer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="smBrandForm">
                    <input type="hidden" id="smBrandId" value="">
                    <div class="modal-body">
                        <label class="form-label">Manufacturer <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="smBrandName" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="smModelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="smModelModalTitle">Add Model</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="smModelForm">
                    <input type="hidden" id="smModelId" value="">
                    <div class="modal-body">
                        <div class="mb-2"><label class="form-label">Manufacturer <span class="text-danger">*</span></label><select id="smModelBrandId" class="form-select" required></select></div>
                        <div class="mb-2"><label class="form-label">Model Name <span class="text-danger">*</span></label><input type="text" id="smModelName" class="form-control" required></div>
                        <div class="mb-2"><label class="form-label">Communication Protocols</label><input type="text" id="smModelProtocols" class="form-control" placeholder="e.g., Modbus TCP, RS485" /></div>
                        <div class="mb-2"><label class="form-label">Power Supply</label><input type="text" id="smModelPower" class="form-control" placeholder="e.g., 230V AC" /></div>
                        <div class="mb-2"><label class="form-label">Characteristics</label><textarea id="smModelChars" class="form-control" rows="3"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Energy Meters (Brands & Models) -->
<div id="section-energymeters" class="row mt-3 data-section d-none">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Energy Meter Brands</h5>
                <button class="btn btn-sm btn-light" id="emAddBrandBtn"><i class="fas fa-plus me-1"></i>Add Brand</button>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="emBrandSearch" placeholder="🔍 Search brands...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="emBrandsTbody">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-microchip me-2"></i>Energy Meter Models</h5>
                <div class="d-flex gap-2">
                    <select id="emModelsBrandFilter" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— All brands —</option>
                    </select>
                    <button class="btn btn-sm btn-light" id="emAddModelBtn"><i class="fas fa-plus me-1"></i>Add Model</button>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="emModelSearch" placeholder="🔍 Search models...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Brand</th>
                                <th>Protocol</th>
                                <th style="width:110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="emModelsTbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals: Brand & Model -->
    <div class="modal fade" id="emBrandModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="emBrandModalTitle">Add Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="emBrandForm">
                    <input type="hidden" id="emBrandId" value="">
                    <div class="modal-body">
                        <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="emBrandName" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="emModelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="emModelModalTitle">Add Model</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="emModelForm">
                    <input type="hidden" id="emModelId" value="">
                    <div class="modal-body">
                        <div class="mb-2"><label class="form-label">Brand <span class="text-danger">*</span></label><select id="emModelBrandId" class="form-select" required></select></div>
                        <div class="mb-2"><label class="form-label">Model Name <span class="text-danger">*</span></label><input type="text" id="emModelName" class="form-control" required></div>
                        <div class="row g-2">
                            <div class="col-md-4"><label class="form-label">Protocol</label><input type="text" id="emModelProtocol" class="form-control" placeholder="e.g., RS485 / Modbus"></div>
                            <div class="col-md-4"><label class="form-label">Voltage Range</label><input type="text" id="emModelVoltage" class="form-control" placeholder="e.g., 3x230/400V"></div>
                            <div class="col-md-4"><label class="form-label">Current Range</label><input type="text" id="emModelCurrent" class="form-control" placeholder="e.g., 5-60A"></div>
                        </div>
                        <div class="mt-2"><label class="form-label">Characteristics</label><textarea id="emModelChars" class="form-control" rows="3"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Modal: Inverter Brand -->
<div class="modal fade" id="invBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invBrandModalTitle">Add Inverter Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="invBrandForm">
                <input type="hidden" id="invBrandId" value="">
                <div class="modal-body">
                    <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="invBrandName" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Inverter Model -->
<div class="modal fade" id="invModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invModelModalTitle">Add Inverter Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="invModelForm">
                <input type="hidden" id="invModelId" value="">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Brand <span class="text-danger">*</span></label>
                        <select id="invModelBrandId" class="form-select" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Model Name <span class="text-danger">*</span></label>
                        <input type="text" id="invModelName" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label class="form-label">Nominal Power (kW)</label>
                            <input type="number" step="0.01" id="invNominalPower" class="form-control" placeholder="e.g., 110">
                        </div>
                        <div class="col">
                            <label class="form-label">Max Output Current (A)</label>
                            <input type="number" step="0.01" id="invMaxCurrent" class="form-control" placeholder="e.g., 180">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col">
                            <label class="form-label">MPPTs</label>
                            <input type="number" id="invMppts" class="form-control" placeholder="e.g., 10">
                        </div>
                        <div class="col">
                            <label class="form-label">Strings per MPPT</label>
                            <input type="number" id="invStringsPerMppt" class="form-control" placeholder="e.g., 3">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Datasheet URL/Path</label>
                        <input type="text" id="invDatasheet" class="form-control" placeholder="https://... or /docs/file.pdf">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- PV Modules (Brands & Models) -->
<div id="section-pvmodules" class="row mt-3 data-section d-none">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i>PV Module Brands</h5>
                <button class="btn btn-sm btn-light" id="addPvBrandBtn"><i class="fas fa-plus me-1"></i>Add Brand</button>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="pvBrandSearch" placeholder="🔍 Search brands...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pvBrandsTbody">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-boxes-stacked me-2"></i>PV Module Models</h5>
                <div class="d-flex gap-2">
                    <select id="pvModelsBrandFilter" class="form-select form-select-sm" style="min-width:220px;">
                        <option value="">— All brands —</option>
                    </select>
                    <button class="btn btn-sm btn-light" id="addPvModelBtn"><i class="fas fa-plus me-1"></i>Add Model</button>
                </div>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="pvModelSearch" placeholder="🔍 Search models...">
                <div class="table-responsive" style="max-height: 450px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Model</th>
                                <th>Brand</th>
                                <th>Power Options</th>
                                <th style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pvModelsTbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: PV Brand -->
<div class="modal fade" id="pvBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pvBrandModalTitle">Add PV Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="pvBrandForm">
                <input type="hidden" id="pvBrandId" value="">
                <div class="modal-body">
                    <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pvBrandName" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: PV Model -->
<div class="modal fade" id="pvModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pvModelModalTitle">Add PV Model</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="pvModelForm">
                <input type="hidden" id="pvModelId" value="">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Brand <span class="text-danger">*</span></label>
                        <select id="pvModelBrandId" class="form-select" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Model Name <span class="text-danger">*</span></label>
                        <input type="text" id="pvModelName" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Power Options (comma-separated) <span class="text-danger">*</span></label>
                        <input type="text" id="pvModelPowerOptions" class="form-control" placeholder="e.g., 450, 455, 460" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Characteristics</label>
                        <textarea id="pvModelCharacteristics" class="form-control" rows="3" placeholder="Voc, Isc, Vmpp, Impp, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- EPC Companies + Representatives Combined Section -->
<div id="section-epcs" class="row mt-3 data-section d-none">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>EPC Companies</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="epcSearchInput" class="form-control form-control-sm"
                            placeholder="🔍 Search by name, email or phone...">
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-sm btn-warning" id="addEpcBtn">
                            <i class="fas fa-plus me-1"></i>Add New Company
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover" id="epcsTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 20%;">Company Name</th>
                                <th style="width: 20%;">Email</th>
                                <th style="width: 15%;">Phone</th>
                                <th style="width: 25%;">Address</th>
                                <th style="width: 20%;">Website</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="epcsTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Representatives Section (paired with EPCs) -->
<div id="section-reps" class="row mt-3 data-section d-none">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0 d-flex align-items-center justify-content-between">
                    <span class="d-flex align-items-center gap-2">
                        <i class="fas fa-user-tie me-2"></i>Representatives
                        <small id="repFilterBadge" class="ms-2 d-none">
                            <span class="badge bg-light text-dark">Filtered by: <span id="repActiveCompanyName"></span></span>
                        </small>
                    </span>
                    <span class="d-flex align-items-center gap-2">
                        <select id="epcCompanyFilter" class="form-select form-select-sm" style="min-width: 260px;">
                            <option value="">— Filter representatives by company —</option>
                        </select>
                        <button class="btn btn-sm btn-outline-light" id="clearEpcFilterBtn">Clear</button>
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="repSearchInput" class="form-control form-control-sm"
                            placeholder="🔍 Search by name, email or company...">
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-sm btn-success" id="addRepBtn">
                            <i class="fas fa-plus me-1"></i>Add New Representative
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-hover" id="repsTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 20%;">Name</th>
                                <th style="width: 20%;">Phone</th>
                                <th style="width: 25%;">Email</th>
                                <th style="width: 25%;">EPC Company</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="repsTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cables (PV Board / Point of Injection) -->
<div id="section-cables" class="row mt-3 data-section d-none">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-industry me-2"></i>Cable Brands</h5>
                <button class="btn btn-sm btn-light" id="cblAddBrandBtn"><i class="fas fa-plus me-1"></i>Add Brand</button>
            </div>
            <div class="card-body">
                <input type="text" class="form-control form-control-sm mb-3" id="cblBrandSearch" placeholder="🔍 Search brands...">
                <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cblBrandsTbody">
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Database Health removed by request -->

<!-- Modal: Add/Edit Responsible -->
<div class="modal fade" id="responsibleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="responsibleModalTitle">Add Responsible</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="responsibleForm">
                <input type="hidden" id="responsibleId" name="id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="respName" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="respName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="respEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="respEmail" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="respPhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="respPhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="respDepartment" class="form-label">Department</label>
                        <input type="text" class="form-control" id="respDepartment" name="department"
                            placeholder="e.g., Engineering, Installation, etc.">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="respActive" name="active" value="1" checked>
                        <label class="form-check-label" for="respActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add/Edit EPC Company -->
<div class="modal fade" id="epcModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="epcModalTitle">Add EPC Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="epcForm">
                <input type="hidden" id="epcId" name="id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="epcName" class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="epcName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="epcEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="epcEmail" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="epcPhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="epcPhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="epcAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="epcAddress" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="epcWebsite" class="form-label">Website</label>
                        <input type="url" class="form-control" id="epcWebsite" name="website" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add/Edit Representative -->
<div class="modal fade" id="repModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="repModalTitle">Add Representative</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="repForm">
                <input type="hidden" id="repId" name="id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="repName" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="repName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="repPhone" class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="repPhone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="repEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="repEmail" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="repEpcId" class="form-label">EPC Company</label>
                        <select class="form-select" id="repEpcId" name="epc_id">
                            <option value="">-- Select Company --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setUserData(userData) {
        document.getElementById('roleUserId').value = userData.id;
        document.getElementById('roleUsername').textContent = userData.username;
        document.getElementById('roleSelect').value = userData.role;
    }

    function setUserId(userId) {
        document.getElementById('passwordUserId').value = userId;
        document.getElementById('deactivateUserId').value = userId;
    }

    // ========== COMMISSIONING RESPONSIBLES MANAGEMENT ==========
    let responsibleModal; // Will be initialized after Bootstrap loads
    let allResponsibles = [];

    // Load responsibles on page load
    document.addEventListener('DOMContentLoaded', function() {
        // **First: Register tab switching (must be early)**
        try {
            const tabs = document.querySelectorAll('#dataTabs .nav-link');
            if (tabs.length > 0) {
                tabs.forEach(btn => {
                    btn.addEventListener('click', function() {
                        tabs.forEach(b => {
                            b.classList.remove('active');
                            b.setAttribute('aria-selected', 'false');
                        });
                        this.classList.add('active');
                        this.setAttribute('aria-selected', 'true');
                        document.querySelectorAll('.data-section').forEach(sec => sec.classList.add('d-none'));
                        const target = this.getAttribute('data-section');
                        const el = document.getElementById(target);
                        if (el) el.classList.remove('d-none');
                        if (target === 'section-epcs') {
                            const reps = document.getElementById('section-reps');
                            if (reps) reps.classList.remove('d-none');
                        }
                    });
                });
            }
        } catch (e) {
            console.error('Tab registration error:', e);
        }

        // Initialize Bootstrap modal
        responsibleModal = new bootstrap.Modal(document.getElementById('responsibleModal'));

        loadResponsibles();

        // Add new button
        document.getElementById('addResponsibleBtn').addEventListener('click', function() {
            document.getElementById('responsibleForm').reset();
            document.getElementById('responsibleId').value = '';
            document.getElementById('responsibleModalTitle').textContent = 'Add Responsible';
            document.getElementById('respActive').checked = true;
            responsibleModal.show();
        });

        // Form submit
        document.getElementById('responsibleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveResponsible();
        });

        // Search filter
        document.getElementById('respSearchInput').addEventListener('keyup', filterResponsibles);
    });

    function loadResponsibles() {
        console.log('📋 Loading responsibles...');
        fetch('ajax/manage_responsibles.php?action=list', {
                credentials: 'include'
            })
            .then(r => {
                console.log('Response status:', r.status);
                if (!r.ok) {
                    throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                }
                return r.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    allResponsibles = data.data || [];
                    renderResponsibles(allResponsibles);
                } else {
                    console.error('API Error:', data.error);
                    alert('Error loading responsibles: ' + data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load error:', err);
                const tbody = document.getElementById('responsiblesTableBody');
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function renderResponsibles(list) {
        const tbody = document.getElementById('responsiblesTableBody');
        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No responsibles found</td></tr>';
            return;
        }

        tbody.innerHTML = list.map(resp => `
            <tr>
                <td><strong>${escapeHtml(resp.name)}</strong></td>
                <td><small>${resp.email ? escapeHtml(resp.email) : '-'}</small></td>
                <td><small>${resp.phone ? escapeHtml(resp.phone) : '-'}</small></td>
                <td><small>${resp.department ? escapeHtml(resp.department) : '-'}</small></td>
                <td>
                    ${resp.active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}
                </td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="editResponsible(${resp.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteResponsible(${resp.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function filterResponsibles() {
        const query = document.getElementById('respSearchInput').value.toLowerCase();
        const filtered = allResponsibles.filter(r =>
            r.name.toLowerCase().includes(query) ||
            (r.email && r.email.toLowerCase().includes(query)) ||
            (r.department && r.department.toLowerCase().includes(query))
        );
        renderResponsibles(filtered);
    }

    function editResponsible(id) {
        const resp = allResponsibles.find(r => r.id == id);
        if (!resp) return;

        document.getElementById('responsibleId').value = resp.id;
        document.getElementById('respName').value = resp.name;
        document.getElementById('respEmail').value = resp.email || '';
        document.getElementById('respPhone').value = resp.phone || '';
        document.getElementById('respDepartment').value = resp.department || '';
        document.getElementById('respActive').checked = resp.active == 1;
        document.getElementById('responsibleModalTitle').textContent = 'Edit Responsible';
        responsibleModal.show();
    }

    function saveResponsible() {
        const id = document.getElementById('responsibleId').value;
        const data = new FormData();
        data.append('action', id ? 'update' : 'create');
        data.append('name', document.getElementById('respName').value);
        data.append('email', document.getElementById('respEmail').value);
        data.append('phone', document.getElementById('respPhone').value);
        data.append('department', document.getElementById('respDepartment').value);
        data.append('active', document.getElementById('respActive').checked ? 1 : 0);
        if (id) data.append('id', id);

        fetch('ajax/manage_responsibles.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    responsibleModal.hide();
                    loadResponsibles();
                    showToast(data.message, 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => console.error('Save error:', err));
    }

    function deleteResponsible(id) {
        if (!confirm('Delete this responsible? This action cannot be undone.')) return;

        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);

        fetch('ajax/manage_responsibles.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadResponsibles();
                    showToast(data.message, 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => console.error('Delete error:', err));
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-info';
        const toast = document.createElement('div');
        toast.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    // Robust tab switching helper (used by inline onclick on tabs)
    function activateDataTab(sectionId, btn) {
        try {
            const tabs = document.querySelectorAll('#dataTabs .nav-link');
            tabs.forEach(b => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            if (btn) {
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
            }

            document.querySelectorAll('.data-section').forEach(sec => sec.classList.add('d-none'));
            const el = document.getElementById(sectionId);
            if (el) el.classList.remove('d-none');

            if (sectionId === 'section-epcs') {
                document.getElementById('section-reps')?.classList.remove('d-none');
            }
        } catch (e) {
            console.error('Tab switch error:', e);
        }
    }

    // Generic confirm dialog (Bootstrap modal) to avoid native browser confirm()
    function confirmDialog(message, okText = 'Confirm', okBtnClass = 'btn-primary', title = 'Please confirm') {
        return new Promise((resolve) => {
            let modalEl = document.getElementById('genericConfirmModal');
            if (!modalEl) {
                modalEl = document.createElement('div');
                modalEl.id = 'genericConfirmModal';
                modalEl.className = 'modal fade';
                modalEl.tabIndex = -1;
                modalEl.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Please confirm</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0"></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn confirm-ok btn-primary">Confirm</button>
                            </div>
                        </div>
                    </div>`;
                document.body.appendChild(modalEl);
            }
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modalEl.querySelector('.modal-title').textContent = title;
            modalEl.querySelector('.modal-body p').textContent = message;
            const okBtn = modalEl.querySelector('.confirm-ok');
            okBtn.className = 'btn confirm-ok ' + okBtnClass;
            okBtn.textContent = okText;

            const onOk = () => {
                cleanup();
                resolve(true);
            };
            const onHide = () => {
                cleanup();
                resolve(false);
            };

            function cleanup() {
                okBtn.removeEventListener('click', onOk);
                modalEl.removeEventListener('hidden.bs.modal', onHide);
            }

            okBtn.addEventListener('click', onOk);
            modalEl.addEventListener('hidden.bs.modal', onHide, {
                once: true
            });

            modal.show();
        });
    }

    // ========== EPC COMPANIES MANAGEMENT ==========
    let epcModal;
    let allEpcs = [];

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        epcModal = new bootstrap.Modal(document.getElementById('epcModal'));
        repModal = new bootstrap.Modal(document.getElementById('repModal'));


        // EPC buttons and events
        document.getElementById('addEpcBtn').addEventListener('click', function() {
            document.getElementById('epcForm').reset();
            document.getElementById('epcId').value = '';
            document.getElementById('epcModalTitle').textContent = 'Add EPC Company';
            epcModal.show();
        });

        document.getElementById('epcForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveEpc();
        });

        document.getElementById('epcSearchInput').addEventListener('keyup', filterEpcs);
        // Populate combined company filter once EPCs load completes

        // Representatives buttons and events
        document.getElementById('addRepBtn').addEventListener('click', function() {
            document.getElementById('repForm').reset();
            document.getElementById('repId').value = '';
            document.getElementById('repModalTitle').textContent = 'Add Representative';
            loadEpcsDropdown();
            repModal.show();
        });

        document.getElementById('repForm').addEventListener('submit', function(e) {
            e.preventDefault();
            saveRep();
        });

        document.getElementById('repSearchInput').addEventListener('keyup', filterReps);

        // Load data
        loadEpcs();
        loadReps();

        // DB Health UI removed
        const epcFilter = document.getElementById('epcCompanyFilter');
        const epcFilterClear = document.getElementById('clearEpcFilterBtn');
        if (epcFilter) {
            epcFilter.addEventListener('change', function() {
                applyRepCompanyFilter(this.value);
            });
        }
        if (epcFilterClear) {
            epcFilterClear.addEventListener('click', function() {
                if (epcFilter) epcFilter.value = '';
                applyRepCompanyFilter('');
            });
        }

        // ===== Initialize Inverters UI =====
        invBrandModal = new bootstrap.Modal(document.getElementById('invBrandModal'));
        invModelModal = new bootstrap.Modal(document.getElementById('invModelModal'));
        // Initial load
        invLoadBrands();
        invLoadModels();

        const invAddBrandBtn = document.getElementById('invAddBrandBtn');
        if (invAddBrandBtn) invAddBrandBtn.addEventListener('click', () => {
            document.getElementById('invBrandForm').reset();
            document.getElementById('invBrandId').value = '';
            document.getElementById('invBrandModalTitle').textContent = 'Add Inverter Brand';
            invBrandModal.show();
        });

        const invBrandFormEl = document.getElementById('invBrandForm');
        if (invBrandFormEl) invBrandFormEl.addEventListener('submit', function(e) {
            e.preventDefault();
            invSaveBrand();
        });
        const invBrandSearch = document.getElementById('invBrandSearch');
        if (invBrandSearch) invBrandSearch.addEventListener('keyup', debounce(() => invLoadBrands(invBrandSearch.value), 200));

        const invAddModelBtn = document.getElementById('invAddModelBtn');
        if (invAddModelBtn) invAddModelBtn.addEventListener('click', () => {
            document.getElementById('invModelForm').reset();
            document.getElementById('invModelId').value = '';
            document.getElementById('invModelModalTitle').textContent = 'Add Inverter Model';
            invPopulateModelBrandSelect();
            const currentFilter = document.getElementById('invModelsBrandFilter')?.value || '';
            if (currentFilter) document.getElementById('invModelBrandId').value = currentFilter;
            invModelModal.show();
        });

        const invModelFormEl = document.getElementById('invModelForm');
        if (invModelFormEl) invModelFormEl.addEventListener('submit', function(e) {
            e.preventDefault();
            invSaveModel();
        });
        const invModelSearch = document.getElementById('invModelSearch');
        if (invModelSearch) invModelSearch.addEventListener('keyup', debounce(() => invLoadModels(), 200));
        const invBrandFilter = document.getElementById('invModelsBrandFilter');
        if (invBrandFilter) invBrandFilter.addEventListener('change', () => invLoadModels());

        // ===== Initialize Protection UI =====
        protBrandModal = new bootstrap.Modal(document.getElementById('protBrandModal'));
        protModelModal = new bootstrap.Modal(document.getElementById('protModelModal'));
        protLoadBrands();
        protLoadModels();
        const devSel = document.getElementById('protDeviceType');
        if (devSel) devSel.addEventListener('change', () => {
            protLoadBrands(document.getElementById('protBrandSearch').value);
            protLoadModels();
        });
        const protAddBrandBtn = document.getElementById('protAddBrandBtn');
        if (protAddBrandBtn) protAddBrandBtn.addEventListener('click', () => {
            document.getElementById('protBrandForm').reset();
            document.getElementById('protBrandId').value = '';
            document.getElementById('protBrandModalTitle').textContent = 'Add Brand';
            protBrandModal.show();
        });
        const protBrandFormEl = document.getElementById('protBrandForm');
        if (protBrandFormEl) protBrandFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            protSaveBrand();
        });
        const protBrandSearch = document.getElementById('protBrandSearch');
        if (protBrandSearch) protBrandSearch.addEventListener('keyup', debounce(() => protLoadBrands(protBrandSearch.value), 200));

        const protAddModelBtn = document.getElementById('protAddModelBtn');
        if (protAddModelBtn) protAddModelBtn.addEventListener('click', () => {
            document.getElementById('protModelForm').reset();
            document.getElementById('protModelId').value = '';
            document.getElementById('protModelModalTitle').textContent = 'Add Model';
            protPopulateModelBrandSelect();
            const currentFilter = document.getElementById('protModelsBrandFilter')?.value || '';
            if (currentFilter) document.getElementById('protModelBrandId').value = currentFilter;
            protModelModal.show();
        });
        const protModelFormEl = document.getElementById('protModelForm');
        if (protModelFormEl) protModelFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            protSaveModel();
        });
        const protModelSearch = document.getElementById('protModelSearch');
        if (protModelSearch) protModelSearch.addEventListener('keyup', debounce(() => protLoadModels(), 200));
        const protBrandFilter = document.getElementById('protModelsBrandFilter');
        if (protBrandFilter) protBrandFilter.addEventListener('change', () => protLoadModels());

        // ===== Initialize Cables UI =====
        cblBrandModal = new bootstrap.Modal(document.getElementById('cblBrandModal'));
        cblModelModal = new bootstrap.Modal(document.getElementById('cblModelModal'));
        cblLoadBrands();
        cblLoadModels();
        const cblAddBrandBtn = document.getElementById('cblAddBrandBtn');
        if (cblAddBrandBtn) cblAddBrandBtn.addEventListener('click', () => {
            document.getElementById('cblBrandForm').reset();
            document.getElementById('cblBrandId').value = '';
            document.getElementById('cblBrandModalTitle').textContent = 'Add Cable Brand';
            cblBrandModal.show();
        });
        const cblBrandFormEl = document.getElementById('cblBrandForm');
        if (cblBrandFormEl) cblBrandFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            cblSaveBrand();
        });
        const cblBrandSearch = document.getElementById('cblBrandSearch');
        if (cblBrandSearch) cblBrandSearch.addEventListener('keyup', debounce(() => cblLoadBrands(cblBrandSearch.value), 200));
        const cblAddModelBtn = document.getElementById('cblAddModelBtn');
        if (cblAddModelBtn) cblAddModelBtn.addEventListener('click', () => {
            document.getElementById('cblModelForm').reset();
            document.getElementById('cblModelId').value = '';
            document.getElementById('cblModelModalTitle').textContent = 'Add Cable Model';
            cblPopulateModelBrandSelect();
            const currentFilter = document.getElementById('cblModelsBrandFilter')?.value || '';
            if (currentFilter) document.getElementById('cblModelBrandId').value = currentFilter;
            cblModelModal.show();
        });
        const cblModelFormEl = document.getElementById('cblModelForm');
        if (cblModelFormEl) cblModelFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            cblSaveModel();
        });
        const cblModelSearch = document.getElementById('cblModelSearch');
        if (cblModelSearch) cblModelSearch.addEventListener('keyup', debounce(() => cblLoadModels(), 200));
        const cblBrandFilter = document.getElementById('cblModelsBrandFilter');
        if (cblBrandFilter) cblBrandFilter.addEventListener('change', () => cblLoadModels());

        // ===== Initialize Communications UI =====
        commModelModal = new bootstrap.Modal(document.getElementById('commModelModal'));
        // Initial loads
        commLoadEquipment();
        commLoadModels();

        const commAddModelBtn = document.getElementById('commAddModelBtn');
        if (commAddModelBtn) commAddModelBtn.addEventListener('click', () => {
            document.getElementById('commModelForm').reset();
            document.getElementById('commModelId').value = '';
            document.getElementById('commModelModalTitle').textContent = 'Add Communication Model';
            // preselect equipment filter
            const current = document.getElementById('commModelsEquipFilter')?.value || '';
            if (current) document.getElementById('commModelEquipment').value = current;
            commModelModal.show();
        });

        const commModelFormEl = document.getElementById('commModelForm');
        if (commModelFormEl) commModelFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            commSaveModel();
        });

        const commModelSearch = document.getElementById('commModelSearch');
        if (commModelSearch) commModelSearch.addEventListener('keyup', debounce(() => commLoadModels(), 200));

        const commEquipFilter = document.getElementById('commModelsEquipFilter');
        if (commEquipFilter) commEquipFilter.addEventListener('change', () => commLoadModels());
    });

    // ===== EPC FUNCTIONS =====
    function loadEpcs() {
        console.log('🏢 Loading EPC companies...');
        fetch('ajax/manage_epcs.php?action=list', {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    allEpcs = data.data || [];
                    renderEpcs(allEpcs);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load error:', err);
                document.getElementById('epcsTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function renderEpcs(list) {
        const tbody = document.getElementById('epcsTableBody');
        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No EPC companies found</td></tr>';
            return;
        }

        tbody.innerHTML = list.map(epc => `
            <tr>
                <td><strong><a href="#" class="text-decoration-none" title="Show representatives" onclick="filterRepsByCompany(${epc.id}); return false;">${escapeHtml(epc.name)}</a></strong></td>
                <td><small>${epc.email ? escapeHtml(epc.email) : '-'}</small></td>
                <td><small>${epc.phone ? escapeHtml(epc.phone) : '-'}</small></td>
                <td><small>${epc.address ? escapeHtml(epc.address).substring(0, 30) + '...' : '-'}</small></td>
                <td><small>${epc.website ? '<a href="' + escapeHtml(epc.website) + '" target="_blank">Link</a>' : '-'}</small></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="editEpc(${epc.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteEpc(${epc.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        // Populate EPC filter dropdown
        const epcFilter = document.getElementById('epcCompanyFilter');
        if (epcFilter) {
            const current = epcFilter.value;
            epcFilter.innerHTML = '<option value="">— Filter representatives by company —</option>';
            list.forEach(e => {
                epcFilter.innerHTML += `<option value="${e.id}">${escapeHtml(e.name)}</option>`;
            });
            // preserve selection if still valid
            if ([...epcFilter.options].some(o => o.value === current)) {
                epcFilter.value = current;
            }
        }
    }

    function filterEpcs() {
        const query = document.getElementById('epcSearchInput').value.toLowerCase();
        const filtered = allEpcs.filter(e =>
            e.name.toLowerCase().includes(query) ||
            (e.email && e.email.toLowerCase().includes(query)) ||
            (e.phone && e.phone.includes(query))
        );
        renderEpcs(filtered);
    }

    function editEpc(id) {
        const epc = allEpcs.find(e => e.id == id);
        if (!epc) return;

        document.getElementById('epcId').value = epc.id;
        document.getElementById('epcName').value = epc.name;
        document.getElementById('epcEmail').value = epc.email || '';
        document.getElementById('epcPhone').value = epc.phone || '';
        document.getElementById('epcAddress').value = epc.address || '';
        document.getElementById('epcWebsite').value = epc.website || '';
        document.getElementById('epcModalTitle').textContent = 'Edit EPC Company';
        epcModal.show();
    }

    function saveEpc() {
        const id = document.getElementById('epcId').value;
        const data = new FormData();
        data.append('action', id ? 'update' : 'create');
        data.append('name', document.getElementById('epcName').value);
        data.append('email', document.getElementById('epcEmail').value);
        data.append('phone', document.getElementById('epcPhone').value);
        data.append('address', document.getElementById('epcAddress').value);
        data.append('website', document.getElementById('epcWebsite').value);
        if (id) data.append('id', id);

        fetch('ajax/manage_epcs.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    epcModal.hide();
                    loadEpcs();
                    showToast(data.message, 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => console.error('Save error:', err));
    }

    function deleteEpc(id) {
        if (!confirm('Delete this company? Representatives linked to it will be affected.')) return;
        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);

        fetch('ajax/manage_epcs.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadEpcs();
                    loadReps();
                    showToast(data.message, 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => console.error('Delete error:', err));
    }

    // ===== REPRESENTATIVES FUNCTIONS =====
    let repModal;
    let allReps = [];

    function loadReps() {
        console.log('👔 Loading representatives...');
        fetch('ajax/manage_representatives.php?action=list', {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    allReps = data.data || [];
                    renderReps(allReps);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load error:', err);
                document.getElementById('repsTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function loadEpcsDropdown() {
        fetch('ajax/manage_representatives.php?action=list_epcs', {
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('repEpcId');
                    const current = select.value;
                    select.innerHTML = '<option value="">-- Select Company --</option>';
                    data.data.forEach(epc => {
                        select.innerHTML += `<option value="${epc.id}">${escapeHtml(epc.name)}</option>`;
                    });
                    select.value = current;
                }
            });
    }

    function renderReps(list) {
        const tbody = document.getElementById('repsTableBody');
        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No representatives found</td></tr>';
            return;
        }

        // Helper to get consistent badge colors per company
        const palette = [{
                bg: 'bg-primary',
                text: 'text-white'
            },
            {
                bg: 'bg-success',
                text: 'text-white'
            },
            {
                bg: 'bg-danger',
                text: 'text-white'
            },
            {
                bg: 'bg-warning',
                text: 'text-dark'
            },
            {
                bg: 'bg-info',
                text: 'text-dark'
            },
            {
                bg: 'bg-secondary',
                text: 'text-white'
            },
            {
                bg: 'bg-dark',
                text: 'text-white'
            }
        ];

        function companyBadge(rep) {
            const idx = Number(rep.epc_id) || 0;
            const cls = palette[idx % palette.length];
            const name = rep.epc_name ? escapeHtml(rep.epc_name) : 'N/A';
            return `<span class="badge rounded-pill ${cls.bg} ${cls.text} px-2 py-1">${name}</span>`;
        }

        tbody.innerHTML = list.map(rep => {
            return `
            <tr>
                <td><strong>${escapeHtml(rep.name)}</strong></td>
                <td><small>${escapeHtml(rep.phone)}</small></td>
                <td><small>${rep.email ? escapeHtml(rep.email) : '-'}</small></td>
                <td>${companyBadge(rep)}</td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="editRep(${rep.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteRep(${rep.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        // Attach event listeners to delete buttons to avoid inline JS issues
        try {
            const deleteButtons = document.querySelectorAll('.pv-delete-btn');
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.modelId || 0;
                    const brandId = this.dataset.brandId || 0;
                    const modelName = this.dataset.modelName ? decodeURIComponent(this.dataset.modelName) : '';
                    deletePvModel(this, id, brandId, modelName);
                });
            });
        } catch (e) {
            console.warn('[PV] attach delete listeners failed', e);
        }
    }

    function applyRepCompanyFilter(epcId) {
        const badgeWrap = document.getElementById('repFilterBadge');
        const badgeName = document.getElementById('repActiveCompanyName');
        if (!epcId) {
            renderReps(allReps);
            if (badgeWrap) badgeWrap.classList.add('d-none');
            return;
        }
        const filtered = allReps.filter(r => String(r.epc_id) === String(epcId));
        renderReps(filtered);
        // Update badge with company name
        const epc = allEpcs.find(e => String(e.id) === String(epcId));
        if (epc && badgeName && badgeWrap) {
            badgeName.textContent = epc.name;
            badgeWrap.classList.remove('d-none');
        }
    }

    function filterRepsByCompany(epcId) {
        const epcFilter = document.getElementById('epcCompanyFilter');
        if (epcFilter) epcFilter.value = String(epcId);
        applyRepCompanyFilter(epcId);
        const repsSection = document.getElementById('section-reps');
        if (repsSection) repsSection.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function filterReps() {
        const query = document.getElementById('repSearchInput').value.toLowerCase();
        const filtered = allReps.filter(r =>
            r.name.toLowerCase().includes(query) ||
            (r.email && r.email.toLowerCase().includes(query)) ||
            (r.epc_name && r.epc_name.toLowerCase().includes(query))
        );
        renderReps(filtered);
    }

    function editRep(id) {
        const rep = allReps.find(r => r.id == id);
        if (!rep) return;

        document.getElementById('repId').value = rep.id;
        document.getElementById('repName').value = rep.name;
        document.getElementById('repPhone').value = rep.phone;
        document.getElementById('repEmail').value = rep.email || '';
        document.getElementById('repEpcId').value = rep.epc_id;
        document.getElementById('repModalTitle').textContent = 'Edit Representative';
        repModal.show();
    }

    function saveRep() {
        const id = document.getElementById('repId').value;
        const data = new FormData();
        data.append('action', id ? 'update' : 'create');
        data.append('name', document.getElementById('repName').value);
        data.append('phone', document.getElementById('repPhone').value);
        data.append('email', document.getElementById('repEmail').value);
        data.append('epc_id', document.getElementById('repEpcId').value);
        if (id) data.append('id', id);

        fetch('ajax/manage_representatives.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    repModal.hide();
                    loadReps();
                    showToast(data.message, 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => console.error('Save error:', err));
    }

    function deleteRep(id) {
        if (!confirm('Delete this representative? This action cannot be undone.')) return;

        const data = new FormData();
        data.append('action', 'delete');
        data.append('id', id);

        fetch('ajax/manage_representatives.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    loadReps();
                    showToast(data.message, 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => console.error('Delete error:', err));
    }

    // Database Health logic removed

    // ========== INVERTERS MANAGEMENT ==========
    let invBrandModal, invModelModal;
    let invBrands = [];
    let invModels = [];

    function invLoadBrands(q = '') {
        let url = 'ajax/manage_inverters.php?action=list_brands';
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    invBrands = data.data || [];
                    invRenderBrands(invBrands);
                    invPopulateBrandFilter();
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load inverter brands error:', err);
                const tbody = document.getElementById('invBrandsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function invRenderBrands(list) {
        const tbody = document.getElementById('invBrandsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No brands found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(b => `
            <tr>
                <td><strong><a href="#" class="text-decoration-none" onclick="invFilterModelsByBrand(${b.id}); return false;">${escapeHtml(b.brand_name)}</a></strong></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="invEditBrand(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="invDeleteBrand(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function invEditBrand(id) {
        const b = invBrands.find(x => String(x.id) === String(id));
        if (!b) return;
        document.getElementById('invBrandId').value = b.id;
        document.getElementById('invBrandName').value = b.brand_name;
        document.getElementById('invBrandModalTitle').textContent = 'Edit Inverter Brand';
        invBrandModal.show();
    }

    function invSaveBrand() {
        const id = document.getElementById('invBrandId').value.trim();
        const name = document.getElementById('invBrandName').value.trim();
        if (!name) {
            alert('Brand name is required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_brand' : 'create_brand');
        if (id) data.append('id', id);
        data.append('brand_name', name);
        fetch('ajax/manage_inverters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    invBrandModal.hide();
                    invLoadBrands(document.getElementById('invBrandSearch').value);
                    invPopulateBrandFilter();
                    invPopulateModelBrandSelect();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save brand error:', err));
    }

    function invDeleteBrand(id) {
        if (!confirm('Delete this brand? All its models will also be removed.')) return;
        const data = new FormData();
        data.append('action', 'delete_brand');
        data.append('id', id);
        fetch('ajax/manage_inverters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    invLoadBrands(document.getElementById('invBrandSearch').value);
                    invLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete brand error:', err));
    }

    function invPopulateBrandFilter() {
        const sel = document.getElementById('invModelsBrandFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">— All brands —</option>';
        invBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function invPopulateModelBrandSelect() {
        const sel = document.getElementById('invModelBrandId');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '';
        invBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function invFilterModelsByBrand(brandId) {
        const sel = document.getElementById('invModelsBrandFilter');
        if (sel) sel.value = String(brandId);
        invLoadModels();
        document.getElementById('section-inverters').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function invLoadModels() {
        const brandSel = document.getElementById('invModelsBrandFilter');
        const brandId = brandSel ? brandSel.value : '';
        const q = document.getElementById('invModelSearch')?.value || '';
        let url = 'ajax/manage_inverters.php?action=list_models';
        if (brandId) url += '&brand_id=' + encodeURIComponent(brandId);
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    invModels = data.data || [];
                    invRenderModels(invModels);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load inverter models error:', err);
                const tbody = document.getElementById('invModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function invRenderModels(list) {
        const tbody = document.getElementById('invModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => {
            const brandName = m.brand_name ? m.brand_name : (invBrands.find(b => String(b.id) === String(m.brand_id))?.brand_name || '');
            const power = (m.nominal_power !== null && m.nominal_power !== undefined && m.nominal_power !== '') ? `${m.nominal_power} kW` : '-';
            const mppts = (m.mppts ?? '') || '-';
            const strings = (m.strings_per_mppt ?? '') || '-';
            return `
                <tr>
                    <td><strong>${escapeHtml(m.model_name)}</strong></td>
                    <td><small>${escapeHtml(brandName)}</small></td>
                    <td><small>${escapeHtml(power)}</small></td>
                    <td><small>${escapeHtml(String(mppts))}</small></td>
                    <td><small>${escapeHtml(String(strings))}</small></td>
                    <td class="text-nowrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="invEditModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="invDeleteModel(${m.id})" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    function invEditModel(id) {
        const m = invModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('invModelId').value = m.id;
        document.getElementById('invModelName').value = m.model_name || '';
        document.getElementById('invNominalPower').value = (m.nominal_power ?? '');
        document.getElementById('invMaxCurrent').value = (m.max_output_current ?? '');
        document.getElementById('invMppts').value = (m.mppts ?? '');
        document.getElementById('invStringsPerMppt').value = (m.strings_per_mppt ?? '');
        document.getElementById('invDatasheet').value = (m.datasheet_path ?? '');
        invPopulateModelBrandSelect();
        document.getElementById('invModelBrandId').value = m.brand_id;
        document.getElementById('invModelModalTitle').textContent = 'Edit Inverter Model';
        invModelModal.show();
    }

    function invSaveModel() {
        const id = document.getElementById('invModelId').value.trim();
        const brandId = document.getElementById('invModelBrandId').value;
        const name = document.getElementById('invModelName').value.trim();
        const nominal = document.getElementById('invNominalPower').value.trim();
        const maxI = document.getElementById('invMaxCurrent').value.trim();
        const mppts = document.getElementById('invMppts').value.trim();
        const strings = document.getElementById('invStringsPerMppt').value.trim();
        const datasheet = document.getElementById('invDatasheet').value.trim();
        if (!brandId || !name) {
            alert('Brand and model are required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('brand_id', brandId);
        data.append('model_name', name);
        data.append('nominal_power', nominal);
        data.append('max_output_current', maxI);
        data.append('mppts', mppts);
        data.append('strings_per_mppt', strings);
        data.append('datasheet_path', datasheet);
        fetch('ajax/manage_inverters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    invModelModal.hide();
                    invLoadModels();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save model error:', err));
    }

    function invDeleteModel(id) {
        if (!confirm('Delete this model?')) return;
        const data = new FormData();
        data.append('action', 'delete_model');
        data.append('id', id);
        fetch('ajax/manage_inverters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    invLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete model error:', err));
    }

    // ========== PV MODULES MANAGEMENT ==========
    let pvBrandModal, pvModelModal;
    let pvBrands = [];
    let pvModels = [];

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize PV modals
        pvBrandModal = new bootstrap.Modal(document.getElementById('pvBrandModal'));
        pvModelModal = new bootstrap.Modal(document.getElementById('pvModelModal'));

        // Load brands and models initially
        loadPvBrands();
        loadPvModels();

        // Buttons and forms
        const addBrandBtn = document.getElementById('addPvBrandBtn');
        if (addBrandBtn) addBrandBtn.addEventListener('click', () => {
            document.getElementById('pvBrandForm').reset();
            document.getElementById('pvBrandId').value = '';
            document.getElementById('pvBrandModalTitle').textContent = 'Add PV Brand';
            pvBrandModal.show();
        });

        const brandForm = document.getElementById('pvBrandForm');
        if (brandForm) brandForm.addEventListener('submit', function(e) {
            e.preventDefault();
            savePvBrand();
        });

        const brandSearch = document.getElementById('pvBrandSearch');
        if (brandSearch) brandSearch.addEventListener('keyup', debounce(() => loadPvBrands(brandSearch.value), 200));

        const addModelBtn = document.getElementById('addPvModelBtn');
        if (addModelBtn) addModelBtn.addEventListener('click', () => {
            document.getElementById('pvModelForm').reset();
            document.getElementById('pvModelId').value = '';
            document.getElementById('pvModelModalTitle').textContent = 'Add PV Model';
            populateModelBrandSelect();
            // Preselect current filter if any
            const currentFilter = document.getElementById('pvModelsBrandFilter')?.value || '';
            if (currentFilter) document.getElementById('pvModelBrandId').value = currentFilter;
            pvModelModal.show();
        });

        const modelForm = document.getElementById('pvModelForm');
        if (modelForm) modelForm.addEventListener('submit', function(e) {
            e.preventDefault();
            savePvModel();
        });

        const modelSearch = document.getElementById('pvModelSearch');
        if (modelSearch) modelSearch.addEventListener('keyup', debounce(() => loadPvModels(), 200));

        const brandFilter = document.getElementById('pvModelsBrandFilter');
        if (brandFilter) brandFilter.addEventListener('change', () => loadPvModels());
    });

    function debounce(fn, ms) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    // ---- Brands ----
    function loadPvBrands(q = '') {
        let url = 'ajax/manage_pv_modules.php?action=list_brands';
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    pvBrands = data.data || [];
                    renderPvBrands(pvBrands);
                    populateBrandFilter();
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load brands error:', err);
                const tbody = document.getElementById('pvBrandsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function renderPvBrands(list) {
        const tbody = document.getElementById('pvBrandsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No brands found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(b => `
            <tr>
                <td>
                    <strong><a href="#" class="text-decoration-none" onclick="filterModelsByBrand(${b.id}); return false;">${escapeHtml(b.brand_name)}</a></strong>
                </td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="editPvBrand(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="deletePvBrand(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function savePvBrand() {
        const id = document.getElementById('pvBrandId').value.trim();
        const name = document.getElementById('pvBrandName').value.trim();
        if (!name) {
            alert('Brand name is required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_brand' : 'create_brand');
        showToast('Error: ' + (resp.error || 'Operation failed'));
        data.append('brand_name', name);

        fetch('ajax/manage_pv_modules.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    pvBrandModal.hide();
                    loadPvBrands(document.getElementById('pvBrandSearch').value);
                    // Refresh models' brand selects
                    populateBrandFilter();
                    populateModelBrandSelect();
                    showToast(resp.message || 'Saved', 'success');
                    // Notify other tabs/pages that a brand was added/updated
                    try {
                        const payload = JSON.stringify({
                            type: 'pv_module',
                            brand_id: resp.id || resp.brand_id || null,
                            brand_name: name,
                            t: Date.now()
                        });
                        localStorage.setItem('cw_brands_updated', payload);
                        try {
                            window.dispatchEvent(new StorageEvent('storage', {
                                key: 'cw_brands_updated',
                                newValue: payload
                            }));
                        } catch (e) {}
                        try {
                            if (window.__cwModelsChannel && typeof window.__cwModelsChannel.postMessage === 'function') {
                                window.__cwModelsChannel.postMessage(JSON.parse(payload));
                            } else if (typeof BroadcastChannel !== 'undefined') {
                                try {
                                    window.__cwModelsChannel = new BroadcastChannel('cw_models');
                                    window.__cwModelsChannel.postMessage(JSON.parse(payload));
                                } catch (e) {}
                            }
                        } catch (e) {
                            /* ignore */
                        }
                    } catch (e) {
                        console.warn('cw: failed to write brand_updated', e);
                    }
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save brand error:', err));
    }

    function deletePvBrand(id) {
        if (!confirm('Delete this brand? All its models will also be removed.')) return;
        const data = new FormData();
        data.append('action', 'delete_brand');
        data.append('id', id);
        fetch('ajax/manage_pv_modules.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    loadPvBrands(document.getElementById('pvBrandSearch').value);
                    loadPvModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete brand error:', err));
    }

    function populateBrandFilter() {
        const sel = document.getElementById('pvModelsBrandFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">— All brands —</option>';
        pvBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function populateModelBrandSelect() {
        const sel = document.getElementById('pvModelBrandId');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '';
        pvBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function filterModelsByBrand(brandId) {
        const sel = document.getElementById('pvModelsBrandFilter');
        if (sel) sel.value = String(brandId);
        loadPvModels();
        document.getElementById('section-pvmodules').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    // ---- Models ----
    function loadPvModels() {
        const brandSel = document.getElementById('pvModelsBrandFilter');
        const brandId = brandSel ? brandSel.value : '';
        const q = document.getElementById('pvModelSearch')?.value || '';
        let url = 'ajax/manage_pv_modules.php?action=list_models';
        if (brandId) url += '&brand_id=' + encodeURIComponent(brandId);
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    pvModels = data.data || [];
                    renderPvModels(pvModels);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load models error:', err);
                const tbody = document.getElementById('pvModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function renderPvModels(list) {
        const tbody = document.getElementById('pvModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => {
            const brandName = m.brand_name ? m.brand_name : (pvBrands.find(b => String(b.id) === String(m.brand_id))?.brand_name || '');
            const power = (m.power_options || '').split(',').map(s => s.trim()).filter(Boolean).join(', ');
            return `
                <tr>
                    <td><strong>${escapeHtml(m.model_name)}</strong></td>
                    <td><small>${escapeHtml(brandName)}</small></td>
                    <td><small>${escapeHtml(power)}</small></td>
                    <td class="text-nowrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="editPvModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger pv-delete-btn" data-model-id="${m.id}" data-brand-id="${m.brand_id}" data-model-name="${encodeURIComponent(m.model_name)}" title="Delete" onclick="deletePvModel(this, this.dataset.modelId, this.dataset.brandId, this.dataset.modelName)"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        // Ensure delete listeners are attached (use delegated listener to survive re-renders)
        attachPvDeleteListeners();
    }

    // Attach a delegated click listener to the PV models tbody so delete buttons always work
    function attachPvDeleteListeners() {
        if (window.pvDeleteListenerAttached) return;
        const tbody = document.getElementById('pvModelsTbody');
        if (!tbody) return;

        tbody.addEventListener('click', function(ev) {
            const btn = ev.target.closest && ev.target.closest('.pv-delete-btn');
            if (!btn) return;
            // Prevent default accidental form submits
            ev.preventDefault();

            const id = btn.dataset.modelId || 0;
            const brandId = btn.dataset.brandId || 0;
            let modelName = btn.dataset.modelName || '';
            if (modelName) {
                try {
                    modelName = decodeURIComponent(modelName);
                } catch (e) {}
            }

            deletePvModel(btn, id, brandId, modelName);
        });

        window.pvDeleteListenerAttached = true;
    }

    function editPvModel(id) {
        const m = pvModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('pvModelId').value = m.id;
        document.getElementById('pvModelName').value = m.model_name || '';
        document.getElementById('pvModelPowerOptions').value = (m.power_options || '').split(',').map(s => s.trim()).filter(Boolean).join(', ');
        document.getElementById('pvModelCharacteristics').value = m.characteristics || '';
        populateModelBrandSelect();
        document.getElementById('pvModelBrandId').value = m.brand_id;
        document.getElementById('pvModelModalTitle').textContent = 'Edit PV Model';
        pvModelModal.show();
    }

    function savePvModel() {
        const id = document.getElementById('pvModelId').value.trim();
        const brandId = document.getElementById('pvModelBrandId').value;
        const name = document.getElementById('pvModelName').value.trim();
        const power = document.getElementById('pvModelPowerOptions').value.trim();
        const characteristics = document.getElementById('pvModelCharacteristics').value.trim();
        if (!brandId || !name || !power) {
            alert('Brand, model and power options are required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('brand_id', brandId);
        data.append('model_name', name);
        data.append('power_options', power);
        data.append('characteristics', characteristics);

        fetch('ajax/manage_pv_modules.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    pvModelModal.hide();
                    loadPvModels();
                    showToast(resp.message || 'Saved', 'success');

                    // Notify other tabs/pages that models were updated so selects can reload
                    try {
                        const payload = JSON.stringify({
                            type: 'pv_module',
                            brand_id: brandId,
                            model_id: resp.id || null,
                            model_name: name,
                            t: Date.now()
                        });
                        localStorage.setItem('cw_models_updated', payload);
                        // Also dispatch a storage event programmatically for same-tab listeners (some browsers don't fire storage for same window)
                        try {
                            window.dispatchEvent(new StorageEvent('storage', {
                                key: 'cw_models_updated',
                                newValue: payload
                            }));
                        } catch (e) {
                            /* ignore */
                        }

                        // Also broadcast via BroadcastChannel if available (faster and reliable)
                        try {
                            if (window.__cwModelsChannel && typeof window.__cwModelsChannel.postMessage === 'function') {
                                window.__cwModelsChannel.postMessage(JSON.parse(payload));
                            } else if (typeof BroadcastChannel !== 'undefined') {
                                try {
                                    window.__cwModelsChannel = new BroadcastChannel('cw_models');
                                    window.__cwModelsChannel.postMessage(JSON.parse(payload));
                                } catch (e) {}
                            }
                        } catch (e) {
                            /* ignore */
                        }
                    } catch (e) {
                        console.warn('cw: failed to write models_updated', e);
                    }
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save model error:', err));
    }

    function deletePvModel(elOrId, maybeId, maybeBrandId) {
        // Support both: deletePvModel(id, brandId) and deletePvModel(el, id, brandId)
        let el = null;
        let id = 0;
        let brandId = 0;
        let modelName = '';

        if (typeof elOrId === 'object' && elOrId !== null && elOrId.tagName) {
            el = elOrId;
            id = maybeId || 0;
            brandId = maybeBrandId || 0;
            modelName = el.dataset.modelName || '';
            if (modelName) {
                try {
                    modelName = decodeURIComponent(modelName);
                } catch (e) {}
            }
        } else {
            id = elOrId || 0;
            brandId = maybeId || 0;
            modelName = maybeBrandId || '';
            if (modelName) {
                try {
                    modelName = decodeURIComponent(modelName);
                } catch (e) {}
            }
        }

        console.log('[PV] deletePvModel called:', {
            id,
            brandId,
            modelName
        });

        // Prevent double invocation: if element provided and already deleting, ignore
        if (el && el.dataset && el.dataset.deleting === '1') {
            console.warn('[PV] delete already in progress for element', el);
            return;
        }

        if (el && el.tagName) {
            // mark as deleting and disable button to avoid duplicate clicks
            try {
                el.dataset.deleting = '1';
                el.disabled = true;
            } catch (e) {}
        }

        if (!confirm('Delete this model?')) {
            if (el && el.tagName) {
                try {
                    el.dataset.deleting = '0';
                    el.disabled = false;
                } catch (e) {}
            }
            return;
        }
        const data = new FormData();
        data.append('action', 'delete_model');
        data.append('id', id);
        // If id is not valid, include brand + model name as fallback
        if (!id || Number(id) <= 0) {
            if (brandId) data.append('brand_id', brandId);
            if (modelName) data.append('model_name', modelName);
        }
        fetch('ajax/manage_pv_modules.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    loadPvModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => {
                console.error('Delete model error:', err);
            })
            .finally(() => {
                if (el && el.tagName) {
                    try {
                        el.dataset.deleting = '0';
                        el.disabled = false;
                    } catch (e) {}
                }
            });
    }

    // ========== CABLES MANAGEMENT ==========
    let cblBrandModal, cblModelModal;
    let cblBrands = [];
    let cblModels = [];

    function cblLoadBrands(q = '') {
        let url = 'ajax/manage_cables.php?action=list_brands';
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    cblBrands = data.data || [];
                    cblRenderBrands(cblBrands);
                    cblPopulateBrandFilter();
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load cable brands error:', err);
                const tbody = document.getElementById('cblBrandsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function cblRenderBrands(list) {
        const tbody = document.getElementById('cblBrandsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No brands found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(b => `
            <tr>
                <td><strong><a href="#" class="text-decoration-none" onclick="cblFilterModelsByBrand(${b.id}); return false;">${escapeHtml(b.brand_name)}</a></strong></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="cblEditBrand(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="cblDeleteBrand(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function cblEditBrand(id) {
        const b = cblBrands.find(x => String(x.id) === String(id));
        if (!b) return;
        document.getElementById('cblBrandId').value = b.id;
        document.getElementById('cblBrandName').value = b.brand_name;
        document.getElementById('cblBrandModalTitle').textContent = 'Edit Cable Brand';
        cblBrandModal.show();
    }

    function cblSaveBrand() {
        const id = document.getElementById('cblBrandId').value.trim();
        const name = document.getElementById('cblBrandName').value.trim();
        if (!name) {
            alert('Brand name is required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_brand' : 'create_brand');
        if (id) data.append('id', id);
        data.append('brand_name', name);
        fetch('ajax/manage_cables.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    cblBrandModal.hide();
                    cblLoadBrands(document.getElementById('cblBrandSearch').value);
                    cblPopulateBrandFilter();
                    cblPopulateModelBrandSelect();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save brand error:', err));
    }

    function cblDeleteBrand(id) {
        if (!confirm('Delete this brand? All its models will also be removed.')) return;
        const data = new FormData();
        data.append('action', 'delete_brand');
        data.append('id', id);
        fetch('ajax/manage_cables.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    cblLoadBrands(document.getElementById('cblBrandSearch').value);
                    cblLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete brand error:', err));
    }

    function cblPopulateBrandFilter() {
        const sel = document.getElementById('cblModelsBrandFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">— All brands —</option>';
        cblBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function cblPopulateModelBrandSelect() {
        const sel = document.getElementById('cblModelBrandId');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '';
        cblBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function cblFilterModelsByBrand(brandId) {
        const sel = document.getElementById('cblModelsBrandFilter');
        if (sel) sel.value = String(brandId);
        cblLoadModels();
        const section = document.getElementById('section-cables');
        if (section && section.scrollIntoView) section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function cblLoadModels() {
        const brandSel = document.getElementById('cblModelsBrandFilter');
        const brandId = brandSel ? brandSel.value : '';
        const q = document.getElementById('cblModelSearch')?.value || '';
        let url = 'ajax/manage_cables.php?action=list_models';
        if (brandId) url += '&brand_id=' + encodeURIComponent(brandId);
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    cblModels = data.data || [];
                    cblRenderModels(cblModels);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load cable models error:', err);
                const tbody = document.getElementById('cblModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function cblRenderModels(list) {
        const tbody = document.getElementById('cblModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => {
            const brandName = m.brand_name ? m.brand_name : (cblBrands.find(b => String(b.id) === String(m.brand_id))?.brand_name || '');
            return `
                <tr>
                    <td><strong>${escapeHtml(m.model_name)}</strong></td>
                    <td><small>${escapeHtml(brandName)}</small></td>
                    <td><small>${escapeHtml(m.cable_section || '-')}</small></td>
                    <td><small>${escapeHtml(m.voltage_rating || '-')}</small></td>
                    <td><small>${escapeHtml(m.temperature_rating || '-')}</small></td>
                    <td class="text-nowrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="cblEditModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="cblDeleteModel(${m.id})" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    function cblEditModel(id) {
        const m = cblModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('cblModelId').value = m.id;
        document.getElementById('cblModelName').value = m.model_name || '';
        document.getElementById('cblSection').value = m.cable_section || '';
        document.getElementById('cblVoltage').value = m.voltage_rating || '';
        document.getElementById('cblTemp').value = m.temperature_rating || '';
        document.getElementById('cblMaterial').value = m.conductor_material || '';
        document.getElementById('cblInsulation').value = m.insulation_type || '';
        document.getElementById('cblCharacteristics').value = m.characteristics || '';
        cblPopulateModelBrandSelect();
        document.getElementById('cblModelBrandId').value = m.brand_id;
        document.getElementById('cblModelModalTitle').textContent = 'Edit Cable Model';
        cblModelModal.show();
    }

    function cblSaveModel() {
        const id = document.getElementById('cblModelId').value.trim();
        const brandId = document.getElementById('cblModelBrandId').value;
        const name = document.getElementById('cblModelName').value.trim();
        const section = document.getElementById('cblSection').value.trim();
        const voltage = document.getElementById('cblVoltage').value.trim();
        const temp = document.getElementById('cblTemp').value.trim();
        const material = document.getElementById('cblMaterial').value.trim();
        const insulation = document.getElementById('cblInsulation').value.trim();
        const characteristics = document.getElementById('cblCharacteristics').value.trim();
        if (!brandId || !name) {
            alert('Brand and model are required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('brand_id', brandId);
        data.append('model_name', name);
        data.append('cable_section', section);
        data.append('voltage_rating', voltage);
        data.append('temperature_rating', temp);
        data.append('conductor_material', material);
        data.append('insulation_type', insulation);
        data.append('characteristics', characteristics);
        fetch('ajax/manage_cables.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    cblModelModal.hide();
                    cblLoadModels();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save model error:', err));
    }

    function cblDeleteModel(id) {
        if (!confirm('Delete this model?')) return;
        const data = new FormData();
        data.append('action', 'delete_model');
        data.append('id', id);
        fetch('ajax/manage_cables.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    cblLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete model error:', err));
    }
    // ========== PROTECTION (CIRCUIT BREAKER / DIFFERENTIAL) MANAGEMENT ==========
    let protBrandModal, protModelModal;
    let protBrands = [];
    let protModels = [];

    function protDevice() {
        return document.getElementById('protDeviceType')?.value || 'circuit_breaker';
    }

    function protLoadBrands(q = '') {
        let url = 'ajax/manage_circuit_protection.php?action=list_brands&device=' + encodeURIComponent(protDevice());
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    protBrands = data.data || [];
                    protRenderBrands(protBrands);
                    protPopulateBrandFilter();
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load protection brands error:', err);
                const tbody = document.getElementById('protBrandsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function protRenderBrands(list) {
        const tbody = document.getElementById('protBrandsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No brands found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(b => `
            <tr>
                <td><strong><a href="#" class="text-decoration-none" onclick="protFilterModelsByBrand(${b.id}); return false;">${escapeHtml(b.brand_name)}</a></strong></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="protEditBrand(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="protDeleteBrand(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function protEditBrand(id) {
        const b = protBrands.find(x => String(x.id) === String(id));
        if (!b) return;
        document.getElementById('protBrandId').value = b.id;
        document.getElementById('protBrandName').value = b.brand_name;
        document.getElementById('protBrandModalTitle').textContent = 'Edit Brand';
        protBrandModal.show();
    }

    function protSaveBrand() {
        const id = document.getElementById('protBrandId').value.trim();
        const name = document.getElementById('protBrandName').value.trim();
        if (!name) {
            alert('Brand name is required');
            return;
        }
        const data = new FormData();
        data.append('device', protDevice());
        data.append('action', id ? 'update_brand' : 'create_brand');
        if (id) data.append('id', id);
        data.append('brand_name', name);
        fetch('ajax/manage_circuit_protection.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    protBrandModal.hide();
                    protLoadBrands(document.getElementById('protBrandSearch').value);
                    protPopulateBrandFilter();
                    protPopulateModelBrandSelect();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save brand error:', err));
    }

    function protDeleteBrand(id) {
        if (!confirm('Delete this brand? All its models will also be removed.')) return;
        const data = new FormData();
        data.append('device', protDevice());
        data.append('action', 'delete_brand');
        data.append('id', id);
        fetch('ajax/manage_circuit_protection.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    protLoadBrands(document.getElementById('protBrandSearch').value);
                    protLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete brand error:', err));
    }

    function protPopulateBrandFilter() {
        const sel = document.getElementById('protModelsBrandFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">— All brands —</option>';
        protBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function protPopulateModelBrandSelect() {
        const sel = document.getElementById('protModelBrandId');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '';
        protBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function protFilterModelsByBrand(brandId) {
        const sel = document.getElementById('protModelsBrandFilter');
        if (sel) sel.value = String(brandId);
        protLoadModels();
        document.getElementById('section-protection').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function protLoadModels() {
        const brandSel = document.getElementById('protModelsBrandFilter');
        const brandId = brandSel ? brandSel.value : '';
        const q = document.getElementById('protModelSearch')?.value || '';
        let url = 'ajax/manage_circuit_protection.php?action=list_models&device=' + encodeURIComponent(protDevice());
        if (brandId) url += '&brand_id=' + encodeURIComponent(brandId);
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    protModels = data.data || [];
                    protRenderModels(protModels);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load protection models error:', err);
                const tbody = document.getElementById('protModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function protRenderModels(list) {
        const tbody = document.getElementById('protModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => {
            const brandName = m.brand_name ? m.brand_name : (protBrands.find(b => String(b.id) === String(m.brand_id))?.brand_name || '');
            const ch = (m.characteristics || '').trim();
            return `
                <tr>
                    <td><strong>${escapeHtml(m.model_name)}</strong></td>
                    <td><small>${escapeHtml(brandName)}</small></td>
                    <td><small>${escapeHtml(ch)}</small></td>
                    <td class="text-nowrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="protEditModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="protDeleteModel(${m.id})" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    function protEditModel(id) {
        const m = protModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('protModelId').value = m.id;
        document.getElementById('protModelName').value = m.model_name || '';
        document.getElementById('protModelCharacteristics').value = m.characteristics || '';
        protPopulateModelBrandSelect();
        document.getElementById('protModelBrandId').value = m.brand_id;
        document.getElementById('protModelModalTitle').textContent = 'Edit Model';
        protModelModal.show();
    }

    function protSaveModel() {
        const id = document.getElementById('protModelId').value.trim();
        const brandId = document.getElementById('protModelBrandId').value;
        const name = document.getElementById('protModelName').value.trim();
        const characteristics = document.getElementById('protModelCharacteristics').value.trim();
        if (!brandId || !name) {
            alert('Brand and model are required');
            return;
        }
        const data = new FormData();
        data.append('device', protDevice());
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('brand_id', brandId);
        data.append('model_name', name);
        data.append('characteristics', characteristics);
        fetch('ajax/manage_circuit_protection.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    protModelModal.hide();
                    protLoadModels();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save model error:', err));
    }

    function protDeleteModel(id) {
        if (!confirm('Delete this model?')) return;
        const data = new FormData();
        data.append('device', protDevice());
        data.append('action', 'delete_model');
        data.append('id', id);
        fetch('ajax/manage_circuit_protection.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    protLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete model error:', err));
    }

    // ============ COMMUNICATIONS FUNCTIONS ============
    const COMM_EQUIP_TYPES = ['HUB', 'RUT', 'Logger'];
    let commModels = [];
    let commModelModal;

    function commLoadEquipment() {
        // render left list
        const tbody = document.getElementById('commEquipTbody');
        if (tbody) {
            tbody.innerHTML = COMM_EQUIP_TYPES.map(eq => `
                <tr>
                    <td><strong><a href="#" class="text-decoration-none" onclick="commFilterModelsByEquip('${eq}'); return false;">${eq}</a></strong></td>
                </tr>`).join('');
        }
        commPopulateEquipFilter();
        commPopulateModelEquipSelect();
    }

    function commPopulateEquipFilter() {
        const sel = document.getElementById('commModelsEquipFilter');
        if (!sel) return;
        const current = sel.value || '';
        sel.innerHTML = '<option value="">— All equipment —</option>' + COMM_EQUIP_TYPES.map(eq => `<option value="${eq}">${eq}</option>`).join('');
        if (current) sel.value = current;
    }

    function commPopulateModelEquipSelect() {
        const sel = document.getElementById('commModelEquipment');
        if (!sel) return;
        sel.innerHTML = COMM_EQUIP_TYPES.map(eq => `<option value="${eq}">${eq}</option>`).join('');
    }

    function commFilterModelsByEquip(eq) {
        const sel = document.getElementById('commModelsEquipFilter');
        if (sel) sel.value = eq;
        commLoadModels();
        document.getElementById('section-communications').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function commLoadModels() {
        let url = 'ajax/manage_communications.php?action=list_models';
        const q = document.getElementById('commModelSearch')?.value || '';
        const equip = document.getElementById('commModelsEquipFilter')?.value || '';
        if (q) url += '&search=' + encodeURIComponent(q);
        if (equip) url += '&equipment=' + encodeURIComponent(equip);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    commModels = data.data || [];
                    commRenderModels(commModels);
                } else {
                    console.error('API Error:', data.error || data.message);
                }
            })
            .catch(err => {
                console.error('❌ Load communications models error:', err);
                const tbody = document.getElementById('commModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function commRenderModels(list) {
        const tbody = document.getElementById('commModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => `
            <tr>
                <td><strong>${escapeHtml(m.model_name)}</strong></td>
                <td>${escapeHtml(m.equipment_type || '')}</td>
                <td><small>${escapeHtml(m.communication_protocols || '—')}</small></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="commEditModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="commDeleteModel(${m.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function commEditModel(id) {
        const m = commModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('commModelId').value = m.id;
        document.getElementById('commModelName').value = m.model_name;
        document.getElementById('commModelEquipment').value = m.equipment_type || '';
        document.getElementById('commManufacturer').value = m.manufacturer || '';
        document.getElementById('commProtocols').value = m.communication_protocols || '';
        document.getElementById('commPowerSupply').value = m.power_supply || '';
        document.getElementById('commCharacteristics').value = m.characteristics || '';
        document.getElementById('commModelModalTitle').textContent = 'Edit Communication Model';
        commModelModal.show();
    }

    function commSaveModel() {
        const id = document.getElementById('commModelId').value.trim();
        const model_name = document.getElementById('commModelName').value.trim();
        const equipment_type = document.getElementById('commModelEquipment').value;
        const manufacturer = document.getElementById('commManufacturer').value.trim();
        const communication_protocols = document.getElementById('commProtocols').value.trim();
        const power_supply = document.getElementById('commPowerSupply').value.trim();
        const characteristics = document.getElementById('commCharacteristics').value.trim();

        if (!model_name || !equipment_type) {
            alert('Model name and equipment type are required');
            return;
        }

        const data = new FormData();
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('model_name', model_name);
        data.append('equipment_type', equipment_type);
        data.append('manufacturer', manufacturer);
        data.append('communication_protocols', communication_protocols);
        data.append('power_supply', power_supply);
        data.append('characteristics', characteristics);

        fetch('ajax/manage_communications.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    commModelModal.hide();
                    commLoadModels();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + (resp.error || resp.message));
                }
            })
            .catch(err => console.error('Save model error:', err));
    }

    function commDeleteModel(id) {
        if (!confirm('Delete this model?')) return;
        const data = new FormData();
        data.append('action', 'delete_model');
        data.append('id', id);
        fetch('ajax/manage_communications.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    commLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + (resp.error || resp.message));
                }
            })
            .catch(err => console.error('Delete model error:', err));
    }

    // ========== SMART METERS MANAGEMENT ==========
    let smBrandModal, smModelModal;
    let smBrands = [];
    let smModels = [];

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const smBrandModalEl = document.getElementById('smBrandModal');
        const smModelModalEl = document.getElementById('smModelModal');
        if (smBrandModalEl) smBrandModal = new bootstrap.Modal(smBrandModalEl);
        if (smModelModalEl) smModelModal = new bootstrap.Modal(smModelModalEl);

        // Initial load
        smLoadBrands();
        smLoadModels();

        // Brand events
        const smAddBrandBtn = document.getElementById('smAddBrandBtn');
        if (smAddBrandBtn) smAddBrandBtn.addEventListener('click', () => {
            document.getElementById('smBrandForm').reset();
            document.getElementById('smBrandId').value = '';
            document.getElementById('smBrandModalTitle').textContent = 'Add Manufacturer';
            smBrandModal.show();
        });
        const smBrandForm = document.getElementById('smBrandForm');
        if (smBrandForm) smBrandForm.addEventListener('submit', (e) => {
            e.preventDefault();
            smSaveBrand();
        });
        const smBrandSearch = document.getElementById('smBrandSearch');
        if (smBrandSearch) smBrandSearch.addEventListener('keyup', debounce(() => smLoadBrands(smBrandSearch.value), 200));

        // Model events
        const smAddModelBtn = document.getElementById('smAddModelBtn');
        if (smAddModelBtn) smAddModelBtn.addEventListener('click', () => {
            document.getElementById('smModelForm').reset();
            document.getElementById('smModelId').value = '';
            document.getElementById('smModelModalTitle').textContent = 'Add Model';
            smPopulateModelBrandSelect();
            const currentFilter = document.getElementById('smModelsBrandFilter')?.value || '';
            if (currentFilter) document.getElementById('smModelBrandId').value = currentFilter;
            smModelModal.show();
        });
        const smModelForm = document.getElementById('smModelForm');
        if (smModelForm) smModelForm.addEventListener('submit', (e) => {
            e.preventDefault();
            smSaveModel();
        });
        const smModelSearch = document.getElementById('smModelSearch');
        if (smModelSearch) smModelSearch.addEventListener('keyup', debounce(() => smLoadModels(), 200));
        const smBrandFilter = document.getElementById('smModelsBrandFilter');
        if (smBrandFilter) smBrandFilter.addEventListener('change', () => smLoadModels());
    });

    // ---- Smart Meter Brands ----
    function smLoadBrands(q = '') {
        let url = 'ajax/manage_smart_meters.php?action=list_brands';
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    smBrands = data.data || [];
                    smRenderBrands(smBrands);
                    smPopulateBrandFilter();
                    smPopulateModelBrandSelect();
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load smart meter brands error:', err);
                const tbody = document.getElementById('smBrandsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function smRenderBrands(list) {
        const tbody = document.getElementById('smBrandsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No manufacturers found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(b => `
            <tr>
                <td><strong><a href="#" class="text-decoration-none" onclick="smFilterModelsByBrand(${b.id}); return false;">${escapeHtml(b.brand_name)}</a></strong></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="smEditBrand(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="smDeleteBrand(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function smSaveBrand() {
        const id = document.getElementById('smBrandId').value.trim();
        const name = document.getElementById('smBrandName').value.trim();
        if (!name) {
            showToast('Error: ' + (resp.error || 'Operation failed'));
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_brand' : 'create_brand');
        if (id) data.append('id', id);
        data.append('brand_name', name);
        fetch('ajax/manage_smart_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    smBrandModal.hide();
                    smLoadBrands(document.getElementById('smBrandSearch').value);
                    smPopulateBrandFilter();
                    smPopulateModelBrandSelect();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save brand error:', err));
    }

    function smDeleteBrand(id) {
        if (!confirm('Delete this manufacturer? All its models will also be removed.')) return;
        const data = new FormData();
        data.append('action', 'delete_brand');
        data.append('id', id);
        fetch('ajax/manage_smart_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    smLoadBrands(document.getElementById('smBrandSearch').value);
                    smLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete brand error:', err));
    }

    function smPopulateBrandFilter() {
        const sel = document.getElementById('smModelsBrandFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">— All manufacturers —</option>';
        smBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function smPopulateModelBrandSelect() {
        const sel = document.getElementById('smModelBrandId');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '';
        smBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function smFilterModelsByBrand(brandId) {
        const sel = document.getElementById('smModelsBrandFilter');
        if (sel) sel.value = String(brandId);
        smLoadModels();
        const section = document.getElementById('section-smartmeters');
        if (section && section.scrollIntoView) section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    // ---- Smart Meter Models ----
    function smLoadModels() {
        const brandSel = document.getElementById('smModelsBrandFilter');
        const brandId = brandSel ? brandSel.value : '';
        const q = document.getElementById('smModelSearch')?.value || '';
        let url = 'ajax/manage_smart_meters.php?action=list_models';
        if (brandId) url += '&brand_id=' + encodeURIComponent(brandId);
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    smModels = data.data || [];
                    smRenderModels(smModels);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load smart meter models error:', err);
                const tbody = document.getElementById('smModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function smRenderModels(list) {
        const tbody = document.getElementById('smModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => `
            <tr>
                <td><strong>${escapeHtml(m.model_name)}</strong></td>
                <td><small>${escapeHtml(m.brand_name || '')}</small></td>
                <td><small>${escapeHtml(m.communication_protocols || '—')}</small></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="smEditModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="smDeleteModel(${m.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function smEditModel(id) {
        const m = smModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('smModelId').value = m.id;
        document.getElementById('smModelName').value = m.model_name || '';
        document.getElementById('smModelProtocols').value = m.communication_protocols || '';
        document.getElementById('smModelPower').value = m.power_supply || '';
        document.getElementById('smModelChars').value = m.characteristics || '';
        smPopulateModelBrandSelect();
        document.getElementById('smModelBrandId').value = m.brand_id;
        document.getElementById('smModelModalTitle').textContent = 'Edit Model';
        smModelModal.show();
    }

    function smSaveModel() {
        const id = document.getElementById('smModelId').value.trim();
        const brandId = document.getElementById('smModelBrandId').value;
        const name = document.getElementById('smModelName').value.trim();
        const protocols = document.getElementById('smModelProtocols').value.trim();
        const power = document.getElementById('smModelPower').value.trim();
        const chars = document.getElementById('smModelChars').value.trim();
        if (!brandId || !name) {
            alert('Manufacturer and model are required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('brand_id', brandId);
        data.append('model_name', name);
        data.append('communication_protocols', protocols);
        data.append('power_supply', power);
        data.append('characteristics', chars);
        fetch('ajax/manage_smart_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    smModelModal.hide();
                    smLoadModels();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Save model error:', err));
    }

    function smDeleteModel(id) {
        if (!confirm('Delete this model?')) return;
        const data = new FormData();
        data.append('action', 'delete_model');
        data.append('id', id);
        fetch('ajax/manage_smart_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    smLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    alert('Error: ' + resp.error);
                }
            })
            .catch(err => console.error('Delete model error:', err));
    }

    // ========== ENERGY METERS MANAGEMENT ========== 
    let emBrandModal, emModelModal;
    let emBrands = [];
    let emModels = [];

    // ---- Energy Meter Brands ----
    function emLoadBrands(q = '') {
        let url = 'ajax/manage_energy_meters.php?action=list_brands';
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    emBrands = data.data || [];
                    emRenderBrands(emBrands);
                    emPopulateBrandFilter();
                    emPopulateModelBrandSelect();
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load energy meter brands error:', err);
                const tbody = document.getElementById('emBrandsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function emRenderBrands(list) {
        const tbody = document.getElementById('emBrandsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No brands found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(b => `
            <tr>
                <td><strong><a href="#" class="text-decoration-none" onclick="emFilterModelsByBrand(${b.id}); return false;">${escapeHtml(b.brand_name)}</a></strong></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="emEditBrand(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="emDeleteBrand(${b.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function emEditBrand(id) {
        const b = emBrands.find(x => String(x.id) === String(id));
        if (!b) return;
        document.getElementById('emBrandId').value = b.id;
        document.getElementById('emBrandName').value = b.brand_name;
        document.getElementById('emBrandModalTitle').textContent = 'Edit Brand';
        emBrandModal.show();
    }

    function emSaveBrand() {
        const id = document.getElementById('emBrandId').value.trim();
        const name = document.getElementById('emBrandName').value.trim();
        if (!name) {
            showToast('Brand name is required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_brand' : 'create_brand');
        if (id) data.append('id', id);
        data.append('brand_name', name);
        fetch('ajax/manage_energy_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    emBrandModal.hide();
                    emLoadBrands(document.getElementById('emBrandSearch').value);
                    emPopulateBrandFilter();
                    emPopulateModelBrandSelect();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    showToast('Error: ' + (resp.error || 'Operation failed'), 'danger');
                }
            })
            .catch(err => {
                console.error('Save brand error:', err);
                showToast('Error saving brand', 'danger');
            });
    }

    async function emDeleteBrand(id) {
        if (!(await confirmDialog('Delete this brand? All its models will also be removed.', 'Delete', 'btn-danger'))) return;
        const data = new FormData();
        data.append('action', 'delete_brand');
        data.append('id', id);
        fetch('ajax/manage_energy_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    emLoadBrands(document.getElementById('emBrandSearch').value);
                    emLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    showToast('Error: ' + (resp.error || 'Operation failed'), 'danger');
                }
            })
            .catch(err => {
                console.error('Delete brand error:', err);
                showToast('Error deleting brand', 'danger');
            });
    }

    function emPopulateBrandFilter() {
        const sel = document.getElementById('emModelsBrandFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">— All brands —</option>';
        emBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function emPopulateModelBrandSelect() {
        const sel = document.getElementById('emModelBrandId');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '';
        emBrands.forEach(b => {
            sel.innerHTML += `<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`;
        });
        if ([...sel.options].some(o => o.value === current)) sel.value = current;
    }

    function emFilterModelsByBrand(brandId) {
        const sel = document.getElementById('emModelsBrandFilter');
        if (sel) sel.value = String(brandId);
        emLoadModels();
        const section = document.getElementById('section-energymeters');
        if (section && section.scrollIntoView) section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    // ---- Energy Meter Models ----
    function emLoadModels() {
        const brandSel = document.getElementById('emModelsBrandFilter');
        const brandId = brandSel ? brandSel.value : '';
        const q = document.getElementById('emModelSearch')?.value || '';
        let url = 'ajax/manage_energy_meters.php?action=list_models';
        if (brandId) url += '&brand_id=' + encodeURIComponent(brandId);
        if (q) url += '&q=' + encodeURIComponent(q);
        fetch(url, {
                credentials: 'include'
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    emModels = data.data || [];
                    emRenderModels(emModels);
                } else {
                    console.error('API Error:', data.error);
                }
            })
            .catch(err => {
                console.error('❌ Load energy meter models error:', err);
                const tbody = document.getElementById('emModelsTbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Error loading: ' + err.message + '</td></tr>';
            });
    }

    function emRenderModels(list) {
        const tbody = document.getElementById('emModelsTbody');
        if (!tbody) return;
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No models found</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(m => `
            <tr>
                <td><strong>${escapeHtml(m.model_name)}</strong></td>
                <td><small>${escapeHtml(m.brand_name || '')}</small></td>
                <td><small>${escapeHtml(m.communication_protocol || '—')}</small></td>
                <td class="text-nowrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="emEditModel(${m.id})" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger" onclick="emDeleteModel(${m.id})" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    function emEditModel(id) {
        const m = emModels.find(x => String(x.id) === String(id));
        if (!m) return;
        document.getElementById('emModelId').value = m.id;
        document.getElementById('emModelName').value = m.model_name || '';
        document.getElementById('emModelProtocol').value = m.communication_protocol || '';
        document.getElementById('emModelVoltage').value = m.voltage_range || '';
        document.getElementById('emModelCurrent').value = m.current_range || '';
        document.getElementById('emModelChars').value = m.characteristics || '';
        emPopulateModelBrandSelect();
        document.getElementById('emModelBrandId').value = m.brand_id;
        document.getElementById('emModelModalTitle').textContent = 'Edit Model';
        emModelModal.show();
    }

    function emSaveModel() {
        const id = document.getElementById('emModelId').value.trim();
        const brandId = document.getElementById('emModelBrandId').value;
        const name = document.getElementById('emModelName').value.trim();
        const protocol = document.getElementById('emModelProtocol').value.trim();
        const voltage = document.getElementById('emModelVoltage').value.trim();
        const current = document.getElementById('emModelCurrent').value.trim();
        const chars = document.getElementById('emModelChars').value.trim();
        if (!brandId || !name) {
            showToast('Brand and model are required');
            return;
        }
        const data = new FormData();
        data.append('action', id ? 'update_model' : 'create_model');
        if (id) data.append('id', id);
        data.append('brand_id', brandId);
        data.append('model_name', name);
        data.append('communication_protocol', protocol);
        data.append('voltage_range', voltage);
        data.append('current_range', current);
        data.append('characteristics', chars);
        fetch('ajax/manage_energy_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    emModelModal.hide();
                    emLoadModels();
                    showToast(resp.message || 'Saved', 'success');
                } else {
                    showToast('Error: ' + (resp.error || 'Operation failed'), 'danger');
                }
            })
            .catch(err => {
                console.error('Save model error:', err);
                showToast('Error saving model', 'danger');
            });
    }

    async function emDeleteModel(id) {
        if (!(await confirmDialog('Delete this model?', 'Delete', 'btn-danger'))) return;
        const data = new FormData();
        data.append('action', 'delete_model');
        data.append('id', id);
        fetch('ajax/manage_energy_meters.php', {
                method: 'POST',
                body: data,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    emLoadModels();
                    showToast(resp.message || 'Deleted', 'success');
                } else {
                    showToast('Error: ' + (resp.error || 'Operation failed'), 'danger');
                }
            })
            .catch(err => {
                console.error('Delete model error:', err);
                showToast('Error deleting model', 'danger');
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const emBrandModalEl = document.getElementById('emBrandModal');
        const emModelModalEl = document.getElementById('emModelModal');
        if (emBrandModalEl) emBrandModal = new bootstrap.Modal(emBrandModalEl);
        if (emModelModalEl) emModelModal = new bootstrap.Modal(emModelModalEl);

        // Initial loads
        emLoadBrands();
        emLoadModels();

        // Brand events
        const emAddBrandBtn = document.getElementById('emAddBrandBtn');
        if (emAddBrandBtn) emAddBrandBtn.addEventListener('click', () => {
            document.getElementById('emBrandForm').reset();
            document.getElementById('emBrandId').value = '';
            document.getElementById('emBrandModalTitle').textContent = 'Add Brand';
            emBrandModal.show();
        });
        const emBrandForm = document.getElementById('emBrandForm');
        if (emBrandForm) emBrandForm.addEventListener('submit', (e) => {
            e.preventDefault();
            emSaveBrand();
        });
        const emBrandSearch = document.getElementById('emBrandSearch');
        if (emBrandSearch) emBrandSearch.addEventListener('keyup', debounce(() => emLoadBrands(emBrandSearch.value), 200));

        // Model events
        const emAddModelBtn = document.getElementById('emAddModelBtn');
        if (emAddModelBtn) emAddModelBtn.addEventListener('click', () => {
            document.getElementById('emModelForm').reset();
            document.getElementById('emModelId').value = '';
            document.getElementById('emModelModalTitle').textContent = 'Add Model';
            emPopulateModelBrandSelect();
            const currentFilter = document.getElementById('emModelsBrandFilter')?.value || '';
            if (currentFilter) document.getElementById('emModelBrandId').value = currentFilter;
            emModelModal.show();
        });
        const emModelForm = document.getElementById('emModelForm');
        if (emModelForm) emModelForm.addEventListener('submit', (e) => {
            e.preventDefault();
            emSaveModel();
        });
        const emModelSearch = document.getElementById('emModelSearch');
        if (emModelSearch) emModelSearch.addEventListener('keyup', debounce(() => emLoadModels(), 200));
        const emBrandFilter = document.getElementById('emModelsBrandFilter');
        if (emBrandFilter) emBrandFilter.addEventListener('change', () => emLoadModels());
    });
</script>
<?php include 'includes/footer.php'; ?>