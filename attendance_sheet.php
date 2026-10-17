<?php
require_once 'config.php';
require_role(['Admin'], false);
$page_title = 'Master Staff Attendance Register';
$page_subtitle = 'Single-Desk Staff Attendance Management, 1-Click Bulk Punching & Daily Shift Audits';
$hide_main_header = true;
require_once 'header.php';


$selected_date = trim($_GET['date'] ?? date('Y-m-d'));
?>

<style>
/* Pure Black & White Corporate Theme for Master Attendance Register */
:root {
    --bw-black: #0f172a;
    --bw-dark: #1e293b;
    --bw-mid: #475569;
    --bw-muted: #64748b;
    --bw-light: #f8fafc;
    --bw-border: #e2e8f0;
    --bw-border-dark: #cbd5e1;
}

.att-banner {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.att-banner-card {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    display: flex;
    align-items: center;
    gap: 12px;
}

.att-banner-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: var(--bw-black);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.att-control-bar {
    background: #ffffff;
    border: 1px solid var(--bw-border);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.status-btn-group {
    display: inline-flex;
    background: var(--bw-light);
    border: 1px solid var(--bw-border-dark);
    padding: 2px;
    border-radius: 7px;
    gap: 2px;
}

.status-btn {
    padding: 4px 10px;
    font-size: 11.5px;
    font-weight: 700;
    border-radius: 5px;
    border: none;
    background: transparent;
    color: var(--bw-muted);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.status-btn:hover {
    color: var(--bw-black);
}

.status-btn.active-present {
    background: var(--bw-black) !important;
    color: #ffffff !important;
}

.status-btn.active-halfday {
    background: #ffffff !important;
    color: var(--bw-black) !important;
    border: 1px solid var(--bw-black) !important;
}

.status-btn.active-absent {
    background: var(--bw-light) !important;
    color: var(--bw-muted) !important;
    border: 1px solid var(--bw-border-dark) !important;
}

.status-btn.active-leave {
    background: var(--bw-border-dark) !important;
    color: var(--bw-black) !important;
}

.mono-input, .mono-select {
    padding: 6px 10px;
    border: 1px solid var(--bw-border-dark);
    border-radius: 6px;
    font-size: 12.5px;
    color: var(--bw-black);
    background: #ffffff;
    outline: none;
    transition: border-color 0.2s;
}

.mono-input:focus, .mono-select:focus {
    border-color: var(--bw-black);
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.08);
}

.btn-mono-primary {
    background: var(--bw-black);
    color: #ffffff;
    border: 1px solid var(--bw-black);
    padding: 8px 16px;
    border-radius: 7px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-mono-primary:hover {
    background: #1e293b;
    border-color: #1e293b;
}

.btn-mono-outline {
    background: #ffffff;
    color: var(--bw-black);
    border: 1px solid var(--bw-black);
    padding: 7px 14px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-mono-outline:hover {
    background: var(--bw-light);
}

.btn-mono-subtle {
    background: var(--bw-light);
    color: var(--bw-black);
    border: 1px solid var(--bw-border-dark);
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-mono-subtle:hover {
    background: #ffffff;
}
</style>

<div class="view-container">
    <!-- Action Bar & Date Selection -->
    <div class="att-control-bar">
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <label style="font-size:13px; font-weight:800; color:var(--bw-black); display:flex; align-items:center; gap:6px;">
                <i data-lucide="calendar" style="width:16px;"></i> Attendance Date:
            </label>
            <input type="date" id="sheet-date-picker" value="<?= htmlspecialchars($selected_date) ?>" onchange="changeSheetDate(this.value)" class="mono-input" style="font-weight:700; width:160px;">
            <a href="employees_list.php" class="btn-mono-outline" style="padding:6px 12px; font-size:12px;"><i data-lucide="users" style="width:13px;"></i> Staff Directory</a>
        </div>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <button onclick="markAllPresentToday()" class="btn-mono-outline" title="Set all staff status to Present with default shift times (09:00 - 18:00)">
                <i data-lucide="zap" style="width:14px;"></i> ⚡ Mark All Present Today
            </button>
            <button onclick="saveEntireSheet()" id="btn-save-entire-sheet" class="btn-mono-primary">
                <i data-lucide="save" style="width:14px;"></i> 💾 Save Entire Attendance Sheet
            </button>
        </div>
    </div>

    <!-- 4 Key Attendance Metric Cards -->
    <div class="att-banner">
        <div class="att-banner-card">
            <div class="att-banner-icon">
                <i data-lucide="users" style="width:18px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;" id="stat-total-staff">0</div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase;">Registered Staff</div>
            </div>
        </div>

        <div class="att-banner-card">
            <div class="att-banner-icon" style="background:#ffffff; color:var(--bw-black); border:1px solid var(--bw-black);">
                <i data-lucide="user-check" style="width:18px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;" id="stat-present-count">0</div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase;">Present Today</div>
            </div>
        </div>

        <div class="att-banner-card">
            <div class="att-banner-icon">
                <i data-lucide="user-minus" style="width:18px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;" id="stat-absent-count">0</div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase;">Absent / Unpaid</div>
            </div>
        </div>

        <div class="att-banner-card">
            <div class="att-banner-icon" style="background:#ffffff; color:var(--bw-black); border:1px solid var(--bw-black);">
                <i data-lucide="coffee" style="width:18px;"></i>
            </div>
            <div>
                <div style="font-size:20px; font-weight:900; color:var(--bw-black); font-family:'Outfit',sans-serif;" id="stat-leave-count">0</div>
                <div style="font-size:11px; font-weight:700; color:var(--bw-muted); text-transform:uppercase;">Paid Leave / Half Day</div>
            </div>
        </div>
    </div>

    <!-- Master Attendance Register Table -->
    <div style="background:#ffffff; border-radius:12px; border:1px solid var(--bw-border); padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-size:15px; font-weight:800; color:var(--bw-black); margin:0;">
                Master Staff Attendance Register • <span id="sheet-date-title" style="color:var(--bw-muted);"><?= date('d M Y', strtotime($selected_date)) ?></span>
            </h3>
            <span style="font-size:11.5px; color:var(--bw-muted); font-weight:600;">Changes can be updated individually or saved in bulk</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Officer Code &amp; Name</th>
                        <th>Department &amp; Designation</th>
                        <th style="min-width:240px;">Attendance Status</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Shift Hours</th>
                        <th>Remarks / Notes</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="master-sheet-tbody">
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--bw-muted);">Loading master attendance register...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let currentSheetDate = '<?= $selected_date ?>';
let sheetData = [];

function changeSheetDate(newDate) {
    if (!newDate) return;
    currentSheetDate = newDate;
    window.history.replaceState({}, '', `attendance_sheet.php?date=${newDate}`);
    document.getElementById('sheet-date-title').innerText = new Date(newDate.replace(/-/g, '/')).toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
    loadMasterSheet();
}

async function loadMasterSheet() {
    const tbody = document.getElementById('master-sheet-tbody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--bw-muted);"><i data-lucide="loader-2" class="spin" style="width:20px;"></i> Loading attendance register for ' + currentSheetDate + '...</td></tr>';
    lucide.createIcons();

    try {
        const res = await fetch(`api.php?api=get_daily_attendance_sheet&date=${currentSheetDate}`);
        const data = await res.json();

        if (!data || !data.success) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--bw-muted);">${data.error || 'Failed to load attendance register'}</td></tr>`;
            return;
        }

        sheetData = data.employees || [];
        updateSheetStats();
        renderSheetRows();
    } catch(e) {
        console.error("Error loading master sheet:", e);
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--bw-muted);">Network communication error</td></tr>';
    }
}

function updateSheetStats() {
    let present = 0, absent = 0, leave = 0;
    sheetData.forEach(e => {
        const st = e.status || 'Present';
        if (st === 'Present') present++;
        else if (st === 'Absent') absent++;
        else leave++;
    });

    document.getElementById('stat-total-staff').innerText = sheetData.length;
    document.getElementById('stat-present-count').innerText = present;
    document.getElementById('stat-absent-count').innerText = absent;
    document.getElementById('stat-leave-count').innerText = leave;
}

function renderSheetRows() {
    const tbody = document.getElementById('master-sheet-tbody');
    if (sheetData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:30px; color:var(--bw-muted);">No active staff officers registered in the system.</td></tr>';
        return;
    }

    let html = '';
    sheetData.forEach((emp, index) => {
        const status = emp.status || 'Present';
        const cinVal = emp.check_in_time ? emp.check_in_time.substring(11, 16) : '09:00';
        const coutVal = emp.check_out_time ? emp.check_out_time.substring(11, 16) : '18:00';
        const notesVal = emp.notes || '';

        // Calculate Shift Mins
        let shiftStr = '--:--';
        if (cinVal && coutVal) {
            const [h1, m1] = cinVal.split(':').map(Number);
            const [h2, m2] = coutVal.split(':').map(Number);
            let diffMins = (h2 * 60 + m2) - (h1 * 60 + m1);
            if (diffMins > 0) {
                const hrs = Math.floor(diffMins / 60);
                const mins = diffMins % 60;
                shiftStr = `<strong>${hrs}h ${mins}m</strong>`;
            }
        }

        html += `
            <tr data-emp-id="${emp.id}" data-emp-username="${emp.username}">
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:32px; height:32px; border-radius:50%; background:var(--bw-black); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0;">
                            ${emp.full_name.substring(0, 1).toUpperCase()}
                        </div>
                        <div>
                            <div style="font-weight:700; color:var(--bw-black); font-size:13px;">${emp.full_name}</div>
                            <div style="font-size:11px; color:var(--bw-muted);">Code: <strong>${emp.emp_id}</strong> • ${emp.username}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-size:12.5px; font-weight:600; color:var(--bw-dark);">${emp.designation || 'Executive'}</div>
                    <div style="font-size:11px; color:var(--bw-muted);">${emp.department || 'General'}</div>
                </td>
                <td>
                    <div class="status-btn-group" id="status-group-${emp.id}">
                        <button type="button" onclick="setRowStatus(${index}, 'Present')" class="status-btn ${status === 'Present' ? 'active-present' : ''}">Present</button>
                        <button type="button" onclick="setRowStatus(${index}, 'Half Day')" class="status-btn ${status === 'Half Day' ? 'active-halfday' : ''}">Half Day</button>
                        <button type="button" onclick="setRowStatus(${index}, 'Paid Leave')" class="status-btn ${status === 'Paid Leave' ? 'active-leave' : ''}">Paid Leave</button>
                        <button type="button" onclick="setRowStatus(${index}, 'Absent')" class="status-btn ${status === 'Absent' ? 'active-absent' : ''}">Absent</button>
                    </div>
                </td>
                <td>
                    <input type="time" id="cin-${emp.id}" value="${cinVal}" onchange="updateRowTime(${index})" class="mono-input" style="width:110px;">
                </td>
                <td>
                    <input type="time" id="cout-${emp.id}" value="${coutVal}" onchange="updateRowTime(${index})" class="mono-input" style="width:110px;">
                </td>
                <td id="shift-display-${emp.id}">
                    ${shiftStr}
                </td>
                <td>
                    <input type="text" id="notes-${emp.id}" value="${notesVal}" placeholder="Add remarks..." class="mono-input" style="width:100%; min-width:140px;">
                </td>
                <td style="text-align:right;">
                    <div style="display:inline-flex; gap:4px;">
                        <button onclick="saveSingleRow(${index})" class="btn-mono-primary" style="padding:4px 8px; font-size:11px;" title="Save Row">
                            <i data-lucide="check" style="width:12px;"></i> Save
                        </button>
                        ${emp.attendance_id ? `<button onclick="deleteSingleRow(${emp.attendance_id}, '${emp.full_name}')" class="btn-mono-subtle" style="padding:4px 8px; font-size:11px;" title="Delete Log"><i data-lucide="trash-2" style="width:12px;"></i></button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    lucide.createIcons();
}

function setRowStatus(index, newStatus) {
    sheetData[index].status = newStatus;
    updateSheetStats();
    renderSheetRows();
}

function updateRowTime(index) {
    const emp = sheetData[index];
    const cin = document.getElementById(`cin-${emp.id}`).value;
    const cout = document.getElementById(`cout-${emp.id}`).value;

    if (cin && cout) {
        const [h1, m1] = cin.split(':').map(Number);
        const [h2, m2] = cout.split(':').map(Number);
        let diffMins = (h2 * 60 + m2) - (h1 * 60 + m1);
        const shiftEl = document.getElementById(`shift-display-${emp.id}`);
        if (diffMins > 0) {
            const hrs = Math.floor(diffMins / 60);
            const mins = diffMins % 60;
            shiftEl.innerHTML = `<strong>${hrs}h ${mins}m</strong>`;
        } else {
            shiftEl.innerText = '--:--';
        }
    }
}

function markAllPresentToday() {
    sheetData.forEach(e => {
        e.status = 'Present';
    });
    updateSheetStats();
    renderSheetRows();
    showNotification('All staff marked as Present for ' + currentSheetDate + '. Click "Save Entire Attendance Sheet" to confirm.', 'info');
}

async function saveSingleRow(index) {
    const emp = sheetData[index];
    const status = emp.status || 'Present';
    const cin = document.getElementById(`cin-${emp.id}`).value;
    const cout = document.getElementById(`cout-${emp.id}`).value;
    const notes = document.getElementById(`notes-${emp.id}`).value;

    const formData = new FormData();
    formData.append('employee_id', emp.id);
    formData.append('emp_username', emp.username);
    formData.append('attendance_date', currentSheetDate);
    formData.append('status', status);
    formData.append('check_in_time', cin);
    formData.append('check_out_time', cout);
    formData.append('notes', notes);

    try {
        const res = await fetch('api.php?api=record_employee_attendance', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(`Attendance updated for ${emp.full_name}`, 'success');
            loadMasterSheet();
        } else {
            showNotification(data.error || 'Failed to save row', 'error');
        }
    } catch(e) {
        showNotification('Network error', 'error');
    }
}

async function saveEntireSheet() {
    const btn = document.getElementById('btn-save-entire-sheet');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="spin" style="width:14px;"></i> Saving All Records...';

    const payload = [];
    sheetData.forEach(emp => {
        const cin = document.getElementById(`cin-${emp.id}`) ? document.getElementById(`cin-${emp.id}`).value : '09:00';
        const cout = document.getElementById(`cout-${emp.id}`) ? document.getElementById(`cout-${emp.id}`).value : '18:00';
        const notes = document.getElementById(`notes-${emp.id}`) ? document.getElementById(`notes-${emp.id}`).value : '';

        payload.push({
            employee_id: emp.id,
            emp_username: emp.username,
            status: emp.status || 'Present',
            check_in_time: cin,
            check_out_time: cout,
            notes: notes
        });
    });

    const formData = new FormData();
    formData.append('attendance_date', currentSheetDate);
    formData.append('sheet_data', JSON.stringify(payload));

    try {
        const res = await fetch('api.php?api=bulk_save_attendance', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            loadMasterSheet();
        } else {
            showNotification(data.error || 'Failed to save attendance sheet', 'error');
        }
    } catch(e) {
        showNotification('Network error', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" style="width:14px;"></i> 💾 Save Entire Attendance Sheet';
        lucide.createIcons();
    }
}

async function deleteSingleRow(attId, empName) {
    if (!confirm(`Are you sure you want to delete the attendance log for ${empName} on ${currentSheetDate}?`)) return;

    const formData = new FormData();
    formData.append('attendance_id', attId);

    try {
        const res = await fetch('api.php?api=delete_employee_attendance', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data && data.success) {
            showNotification(data.message, 'success');
            loadMasterSheet();
        } else {
            showNotification(data.error || 'Failed to delete record', 'error');
        }
    } catch(e) {
        showNotification('Network error', 'error');
    }
}

window.addEventListener('DOMContentLoaded', () => {
    loadMasterSheet();
});
</script>

<?php require_once 'footer.php'; ?>
