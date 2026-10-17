<?php
require_once 'config.php';
require_role(['Admin'], false);
// Seamless redirect to Unified Payout & Commission Center
header('Location: payout_distribution.php?tab=bank_tracker');
exit;
?>

// Fetch existing settings
$stmt = $db->query("SELECT * FROM bank_payout_settings ORDER BY bank_name ASC, loan_type ASC");
$settings = $stmt->fetchAll();

// Fetch distinct bank names for dropdown (from bankers table)
$bank_stmt = $db->query("SELECT DISTINCT bank_name FROM bankers WHERE bank_name IS NOT NULL AND bank_name != '' ORDER BY bank_name");
$banks = $bank_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch distinct loan types
$loan_stmt = $db->query("SELECT DISTINCT loan_type FROM applicants WHERE loan_type IS NOT NULL AND loan_type != '' ORDER BY loan_type");
$loan_types = $loan_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<style>
body { background-color: #f4f7f6; }
.premium-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 10px 30px -4px rgba(0,0,0,0.04), 0 4px 12px -2px rgba(0,0,0,0.02);
    overflow: hidden;
}
.premium-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.premium-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    padding: 16px 24px;
    border-bottom: 1px solid #e2e8f0;
}
.premium-table td {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.premium-table tbody tr {
    transition: background 0.2s;
}
.premium-table tbody tr:hover {
    background: #fdfdfd;
}
.btn-orange-gradient {
    background: linear-gradient(135deg, #0f172a 0%, #000000 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    transition: all 0.3s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-orange-gradient:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.35);
    transform: translateY(-1px);
}
.empty-state {
    padding: 64px 24px;
    text-align: center;
    background: linear-gradient(to bottom, #ffffff, #f8fafc);
}
</style>

<div class="view-container">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
        <p style="color:#64748b; margin:0; font-size:14px;">Manage auto-calculation percentages for your bank payouts.</p>
        <button class="btn-orange-gradient" onclick="document.getElementById('add-setting-modal').style.display='flex'">
            <i data-lucide="plus" style="width:18px;"></i> Add Payout Rule
        </button>
    </div>
    
    <div class="premium-card">
        <table class="premium-table">
            <thead>
                <tr>
                    <th>Bank Name</th>
                    <th>Loan Type</th>
                    <th>Commission (%)</th>
                    <th style="width: 100px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($settings)): ?>
                <tr>
                    <td colspan="4" style="padding:0;">
                        <div class="empty-state">
                            <div style="background:#f1f5f9; width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                                <i data-lucide="percent" style="color:#0f172a; width:32px; height:32px;"></i>
                            </div>
                            <h3 style="margin:0 0 8px; font-size:18px; color:#0f172a;">No Payout Rules Configured</h3>
                            <p style="color:#64748b; margin:0 0 24px; max-width:400px; margin-inline:auto; line-height:1.5;">Set up your commission structures here. Phase 4 will automatically fetch these rates based on the sanctioned bank and loan type.</p>
                            <button class="btn-orange-gradient" onclick="document.getElementById('add-setting-modal').style.display='flex'">
                                <i data-lucide="plus" style="width:18px;"></i> Create First Rule
                            </button>
                        </div>
                    </td>
                </tr>
                <?php else: foreach ($settings as $row): ?>
                <tr>
                    <td>
                        <div style="font-weight:700; color:#1e293b; font-size:14px; display:flex; align-items:center; gap:8px;">
                            <div style="width:8px; height:8px; border-radius:50%; background:#3b82f6;"></div>
                            <?php echo htmlspecialchars($row['bank_name']); ?>
                        </div>
                    </td>
                    <td>
                        <span style="background:#f1f5f9; color:#475569; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; border:1px solid #e2e8f0;">
                            <?php echo htmlspecialchars($row['loan_type']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:800; color:#000000; font-size:16px;">
                            <?php echo htmlspecialchars($row['payout_percentage']); ?>%
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <button class="btn btn-danger" style="padding:6px 10px; border-radius:6px; background:#fff1f2; color:#e11d48; border:1px solid #ffe4e6; cursor:pointer;" onclick="deleteRule(<?php echo $row['id']; ?>)" title="Delete Rule">
                            <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="add-setting-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
    <div class="premium-card" style="width:100%; max-width:450px; padding:32px;">
        <h3 style="margin-top:0; margin-bottom:24px; font-size:20px; color:#0f172a; display:flex; align-items:center; gap:8px;"><i data-lucide="settings-2" style="color:#0f172a;"></i> New Payout Rule</h3>
        <form onsubmit="saveRule(event)">
            
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">Bank Type</label>
                <select id="rule_bank_type" required style="width:100%; padding:12px 16px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-size:14px; outline:none; transition:border-color 0.2s;">
                    <option value="">-- Select Bank Type --</option>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">Bank Name</label>
                <select name="bank_name" id="rule_bank_name" required style="width:100%; padding:12px 16px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-size:14px; outline:none; transition:border-color 0.2s;">
                    <option value="">-- Select Bank --</option>
                </select>
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">Loan Type</label>
                <select name="loan_type" required style="width:100%; padding:12px 16px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-size:14px; outline:none; transition:border-color 0.2s;">
                    <option value="All">All Loan Types (Default)</option>
                    <?php foreach($loan_types as $lt): ?><option value="<?php echo htmlspecialchars($lt); ?>"><?php echo htmlspecialchars($lt); ?></option><?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom:32px;">
                <label style="display:block; margin-bottom:8px; font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.05em;">Commission Percentage (%)</label>
                <input type="number" name="payout_percentage" step="0.01" required placeholder="e.g. 1.50" style="width:100%; padding:12px 16px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; font-weight:800; color:#000000; font-size:18px; outline:none; transition:border-color 0.2s;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:16px;">
                <button type="button" onclick="document.getElementById('add-setting-modal').style.display='none'" style="background:none; border:none; color:#64748b; font-weight:600; cursor:pointer; padding:10px 16px;">Cancel</button>
                <button type="submit" class="btn-orange-gradient">Save Rule</button>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<style>
.ts-control {
    padding: 12px 16px !important;
    border-radius: 8px !important;
    border: 1px solid #cbd5e1 !important;
    background: #f8fafc !important;
    font-size: 14px !important;
    box-shadow: none !important;
}
.ts-dropdown {
    border-radius: 8px !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
    font-size: 14px !important;
    z-index: 10005 !important;
}
.ts-dropdown .option {
    padding: 10px 16px !important;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="assets/js/banks_directory.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // We will build the dropdowns manually to integrate perfectly with TomSelect
    const typeSelect = document.getElementById('rule_bank_type');
    const nameSelect = document.getElementById('rule_bank_name');
    
    // Populate Bank Types
    Object.keys(BANK_DIRECTORY).forEach(type => {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        typeSelect.appendChild(option);
    });

    // Initialize TomSelect on Bank Name first (empty)
    const tsName = new TomSelect('#rule_bank_name', {
        placeholder: '-- Select Bank Name --',
        searchField: ['text'],
        maxOptions: 50,
        dropdownParent: 'body'
    });

    // Initialize TomSelect on Bank Type
    const tsType = new TomSelect('#rule_bank_type', {
        placeholder: '-- Select Bank Type --',
        dropdownParent: 'body',
        onChange: function(value) {
            tsName.clear();
            tsName.clearOptions();
            if (value && BANK_DIRECTORY[value]) {
                BANK_DIRECTORY[value].forEach(bank => {
                    tsName.addOption({value: bank, text: bank});
                });
            }
            tsName.refreshOptions(false);
        }
    });
    
    // Initialize for Loan Type too for consistency
    new TomSelect('select[name="loan_type"]', {
        placeholder: '-- Select Loan Type --',
        dropdownParent: 'body'
    });
});

async function saveRule(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const oldText = btn.innerHTML;
    btn.innerHTML = 'Saving...';
    btn.disabled = true;
    
    const fd = new FormData(e.target);
    try {
        const res = await fetch('api.php?api=save_payout_rule', { method:'POST', body:fd });
        const data = await res.json();
        if(data.success) location.reload(); else alert(data.error || 'Error saving rule');
    } catch(err) { alert('Network error'); }
    
    btn.innerHTML = oldText;
    btn.disabled = false;
}

async function deleteRule(id) {
    if(!confirm("Are you sure you want to delete this payout rule?")) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const res = await fetch('api.php?api=delete_payout_rule', { method:'POST', body:fd });
        const data = await res.json();
        if(data.success) location.reload(); else alert(data.error);
    } catch(err) { alert('Network error'); }
}
</script>

<?php require_once 'footer.php'; ?>
