<?php
require_once __DIR__ . '/../config.php';
$page_title = 'Bankers Directory';
$page_subtitle = 'ðŸ‘¥ View and manage bankers in the system';
require_once __DIR__ . '/header.php';

$stmt = $db->query("SELECT * FROM bankers ORDER BY created_at DESC");
$bankers = $stmt->fetchAll();
?>

<div id="view-bankers" class="view-container">
    <div class="card">
        <div class="card-title-bar">
            <h2>Registered Bankers</h2>
            <div class="actions">
                <?php if (($_SESSION['role'] ?? '') === 'Admin'): ?>
                <a href="add_banker.php" class="btn btn-primary"><i data-lucide="plus"></i> Add New Banker</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Full Name</th>
                        <th>Designation</th>
                        <th>Branch</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bankers)): ?>
                        <tr><td colspan="7" class="text-center">No bankers registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bankers as $banker): ?>
                            <tr>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($banker['employee_id'] ?? ('BNK-' . str_pad($banker['id'], 4, '0', STR_PAD_LEFT))); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($banker['full_name'] ?? ''); ?></strong></td>
                                <td><?php echo htmlspecialchars($banker['designation'] ?? 'Banker'); ?></td>
                                <td><?php echo htmlspecialchars($banker['branch_name'] ?? $banker['bank_name'] ?? 'Main Branch'); ?></td>
                                <td>
                                    <div><i data-lucide="phone" style="width:14px;height:14px;"></i> <?php echo htmlspecialchars($banker['contact_number'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $bg_status = $banker['bg_verification_status'] ?? 'Approved';
                                        $bg_class = 'success';
                                        if($bg_status == 'Pending') $bg_class = 'warning';
                                        elseif($bg_status != 'Approved' && $bg_status != 'Active') $bg_class = 'danger';
                                    ?>
                                    <span class="badge badge-<?php echo $bg_class; ?>"><?php echo htmlspecialchars($bg_status); ?></span>
                                </td>
                                <td>
                                    <a href="../view_banker.php?id=<?php echo $banker['id']; ?>" class="btn btn-sm btn-secondary">
                                        <i data-lucide="eye"></i> View Profile
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

