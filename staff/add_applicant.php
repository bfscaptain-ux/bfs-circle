<?php
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff';
$edit_id = $_GET['id'] ?? '';
$init_customer_name = $_GET['customer_name'] ?? $_GET['name'] ?? '';
$init_mobile = $_GET['mobile'] ?? '';
$init_visit_id = $_GET['visit_id'] ?? $_GET['converted_lead_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Official BFS Circle Monochrome Favicon & Branding -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <link rel="icon" type="image/x-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="shortcut icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.ico?v=4">
    <link rel="apple-touch-icon" href="<?php echo isset($base_path) ? $base_path : ""; ?>favicon.png?v=4">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Loan Applicant - Mobile Field App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #FF7A00;
            --primary-dark: #E66A00;
            --secondary: #1E293B;
            --bg-color: #F1F5F9;
            --card-bg: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            padding-bottom: 80px;
        }

        /* Mobile Orange App Header */
        .app-header {
            background: linear-gradient(135deg, #FF6B00 0%, #FF9A44 100%);
            color: white;
            padding: 20px 16px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            box-shadow: 0 10px 25px rgba(255, 107, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title {
            font-size: 18px;
            font-weight: 800;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            text-decoration: none;
        }

        .content-container {
            padding: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-group label.required::after {
            content: " *";
            color: #EF4444;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            background: #F8FAFC;
        }

        .form-control:focus {
            border-color: var(--primary);
            background: white;
        }

        .submit-btn {
            background: linear-gradient(135deg, #FF7A00 0%, #E66A00 100%);
            color: white;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(255, 122, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Bottom Mobile Nav */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }

        .nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 10px;
            font-weight: 600;
        }

        .nav-btn.active { color: var(--primary); }
        .nav-btn i { font-size: 20px; }
    </style>
</head>
<body>

    <!-- Mobile Orange Header -->
    <div class="app-header">
        <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="header-title">Add Loan Applicant</div>
        <div style="width:36px;"></div>
    </div>

    <div class="content-container">
        <form id="mobile-applicant-form" onsubmit="saveMobileApplicant(event)">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id); ?>">
            <input type="hidden" name="converted_lead_id" id="converted_lead_id" value="<?php echo htmlspecialchars($init_visit_id); ?>">

            <!-- 1. BASIC DETAILS -->
            <div class="form-card">
                <div class="section-title"><i class="fa-solid fa-user"></i> 1. Basic Information</div>
                <div class="form-group">
                    <label class="required">Customer Full Name</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="Applicant Full Name" value="<?php echo htmlspecialchars($init_customer_name); ?>" required>
                </div>
                <div class="form-group">
                    <label class="required">Mobile Number</label>
                    <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile number" value="<?php echo htmlspecialchars($init_mobile); ?>" required pattern="\d{10}">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="client@example.com">
                </div>
            </div>

            <!-- 2. LOCATION & ADDRESS -->
            <div class="form-card">
                <div class="section-title"><i class="fa-solid fa-location-dot"></i> 2. Address & Location</div>
                <div class="form-group">
                    <label class="required">Pincode</label>
                    <input type="text" name="pincode" class="form-control" placeholder="6-digit pincode" pattern="\d{6}" maxlength="6">
                </div>
                <div class="form-group">
                    <label class="required">City / District</label>
                    <input type="text" name="city" class="form-control" placeholder="City name" required>
                </div>
                <div class="form-group">
                    <label class="required">Complete Address</label>
                    <textarea name="address" rows="2" class="form-control" placeholder="House/Flat No., Street, Area" required></textarea>
                </div>
            </div>

            <!-- 3. FINANCIAL & KYC -->
            <div class="form-card">
                <div class="section-title"><i class="fa-solid fa-id-card"></i> 3. Financial & KYC Details</div>
                <div class="form-group">
                    <label class="required">PAN Number</label>
                    <input type="text" name="pan_number" class="form-control" placeholder="ABCDE1234F" style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label>Aadhaar Card Number</label>
                    <input type="text" name="aadhar_number" class="form-control" placeholder="12-digit Aadhaar" maxlength="12">
                </div>
                <div class="form-group">
                    <label class="required">Employment Type</label>
                    <select name="employment_type" id="employment_type" class="form-control" required onchange="handleStaffEmpTypeChange(this.value)">
                        <option value="">Select Employment</option>
                        <option value="Salaried">Salaried (Private)</option>
                        <option value="Government Employee">Government Employee</option>
                        <option value="Self-Employed Business">Self-Employed Business Owner</option>
                        <option value="Self-Employed Professional">Self-Employed Professional (Doctor/CA/Lawyer)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Monthly Net Income (₹)</label>
                    <input type="number" name="monthly_income" class="form-control" placeholder="e.g. 55000" required>
                </div>

                <!-- Dynamic Employer / Organization Details -->
                <div id="staff_org_details_wrapper" style="display:none; margin-top:16px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px;">
                    <div class="form-group">
                        <label id="staff_org_name_label" class="required">Company / Department Name</label>
                        <input type="text" name="organization_name" id="staff_organization_name" class="form-control" placeholder="Enter organization name">
                    </div>
                    <div class="form-group" style="margin-top:12px;">
                        <label id="staff_org_address_label">Office / Business Address</label>
                        <input type="text" name="organization_address" id="staff_organization_address" class="form-control" placeholder="Enter office address">
                    </div>
                </div>
            </div>

            <!-- 4. LOAN APPLICATION REQUIREMENTS -->
            <div class="form-card">
                <div class="section-title"><i class="fa-solid fa-building-columns"></i> 4. Loan Requirements</div>
                <div class="form-group">
                    <label class="required">Loan Category Type</label>
                    <select name="loan_type" class="form-control" required>
                        <option value="">Select Loan Type</option>
                        <option value="Home Loan">Home Loan</option>
                        <option value="Personal Loan">Personal Loan</option>
                        <option value="Business Loan">Business Loan</option>
                        <option value="Loan Against Property">Loan Against Property (LAP)</option>
                        <option value="Car Loan">Car / Vehicle Loan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Requested Loan Amount (₹)</label>
                    <input type="number" name="loan_amount_requested" class="form-control" placeholder="e.g. 2500000" required>
                </div>
                <div class="form-group">
                    <label class="required" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Expected Loan Tenure</span>
                        <span style="font-size:11px; font-weight:normal; color:#64748b;">(Auto calculates Years ↔ Months)</span>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div style="flex: 1; position: relative;">
                            <input type="number" id="staff_tenure_years" class="form-control" step="0.5" min="0" placeholder="Years (e.g. 10)" oninput="calcStaffTenureFromYears(this.value)" style="padding-right: 50px;">
                            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 11px; color: #64748b; font-weight: 700; pointer-events: none;">Years</span>
                        </div>
                        <div style="font-weight: 800; color: #64748b; font-size: 14px;">=</div>
                        <div style="flex: 1; position: relative;">
                            <input type="number" name="tenure_months" id="staff_tenure_months" class="form-control" min="1" placeholder="Months (e.g. 120)" required oninput="calcStaffTenureFromMonths(this.value)" style="padding-right: 58px;">
                            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 11px; color: #64748b; font-weight: 700; pointer-events: none;">Months</span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fa-solid fa-check"></i> Register Applicant Profile
            </button>
        </form>
    </div>

    <!-- Bottom Mobile Nav -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-btn"><i class="fa-solid fa-house"></i>Home</a>
        <a href="add_visit.php" class="nav-btn"><i class="fa-solid fa-location-dot"></i>Visits</a>
        <a href="add_applicant.php" class="nav-btn active"><i class="fa-solid fa-user-plus"></i>Add Loan</a>
        <a href="my_route.php" class="nav-btn"><i class="fa-solid fa-route"></i>Route</a>
        <a href="profile.php" class="nav-btn"><i class="fa-solid fa-user"></i>Profile</a>
    </div>

    <script>
        function handleStaffEmpTypeChange(val) {
            const wrapper = document.getElementById('staff_org_details_wrapper');
            const nameLabel = document.getElementById('staff_org_name_label');
            const nameInput = document.getElementById('staff_organization_name');
            const addrLabel = document.getElementById('staff_org_address_label');
            const addrInput = document.getElementById('staff_organization_address');

            if (!wrapper || !nameLabel || !nameInput || !addrLabel || !addrInput) return;

            if (!val) {
                wrapper.style.display = 'none';
                return;
            }

            wrapper.style.display = 'block';

            if (val === 'Government Employee') {
                nameLabel.textContent = 'Department / Posting Name';
                nameInput.placeholder = 'e.g. Ministry of Railways, Revenue Dept, PWD, Police Dept';
                addrLabel.textContent = 'Office / Job Address';
                addrInput.placeholder = 'e.g. Department Office Address & City';
            } else if (val === 'Salaried') {
                nameLabel.textContent = 'Company / Employer Name';
                nameInput.placeholder = 'e.g. Tata Consultancy Services, Infosys, Pvt Ltd';
                addrLabel.textContent = 'Company / Office Address';
                addrInput.placeholder = 'e.g. Corporate Office / Work premises address';
            } else if (val.includes('Professional')) {
                nameLabel.textContent = 'Practice / Firm Name';
                nameInput.placeholder = 'e.g. Apex Legal Chambers, Dr. Care Clinic, CA Firm';
                addrLabel.textContent = 'Clinic / Office Address';
                addrInput.placeholder = 'e.g. Clinic, Chamber, or Studio address';
            } else {
                // Self-Employed Business
                nameLabel.textContent = 'Firm / Business Name';
                nameInput.placeholder = 'e.g. Apex Enterprises, Universal Traders, Royal Store';
                addrLabel.textContent = 'Business / Shop Address';
                addrInput.placeholder = 'e.g. Store, Factory, or Office establishment address';
            }
        }

        function calcStaffTenureFromYears(yearsVal) {
            const yrs = parseFloat(yearsVal);
            const monthsInput = document.getElementById('staff_tenure_months');
            if (!isNaN(yrs) && yrs > 0) {
                monthsInput.value = Math.round(yrs * 12);
            } else if (yearsVal === '') {
                monthsInput.value = '';
            }
        }

        function calcStaffTenureFromMonths(monthsVal) {
            const m = parseInt(monthsVal, 10);
            const yearsInput = document.getElementById('staff_tenure_years');
            if (!isNaN(m) && m > 0) {
                const yrs = m / 12;
                yearsInput.value = (yrs % 1 === 0) ? yrs : yrs.toFixed(1);
            } else if (monthsVal === '') {
                yearsInput.value = '';
            }
        }
        async function saveMobileApplicant(e) {
            e.preventDefault();
            const form = document.getElementById('mobile-applicant-form');
            const fd = new FormData(form);

            try {
                const res = await fetch('../api.php?api=save_applicant', { method: 'POST', body: fd });
                const data = await res.json();

                if (res.ok && data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Applicant Registered!',
                        text: `Generated Loan File #${data.loan_id || 'Success'}. Registered under Field Staff Workspace.`,
                        confirmButtonColor: '#FF7A00'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                } else if (res.status === 409 && data.warning === 'duplicate_detected') {
                    let matchesList = [];
                    if (data.duplicates && Array.isArray(data.duplicates)) {
                        data.duplicates.forEach(dup => {
                            if (dup.matches && Array.isArray(dup.matches)) {
                                dup.matches.forEach(m => {
                                    if (!matchesList.some(item => item.id === m.id || item.loan_id === m.loan_id)) {
                                        matchesList.push(m);
                                    }
                                });
                            }
                        });
                    }

                    let cardsHtml = '';
                    matchesList.forEach(m => {
                        cardsHtml += `
                            <div style="background:#ffffff; border:1px solid #fee2e2; border-left:4px solid #dc2626; border-radius:8px; padding:12px; margin-bottom:8px; text-align:left;">
                                <div style="font-weight:700; font-size:13px; color:#0f172a;">${m.customer_name || 'N/A'} <span style="font-size:11px; color:#dc2626; font-family:monospace;">(${m.loan_id || 'N/A'})</span></div>
                                <div style="font-size:11px; color:#64748b; margin-top:2px;">Mobile: <b>${m.mobile || 'N/A'}</b> &nbsp;•&nbsp; PAN: <b>${m.pan_number || 'N/A'}</b> &nbsp;•&nbsp; Stage: <b>${m.overall_status || 'Active'}</b></div>
                            </div>
                        `;
                    });

                    Swal.fire({
                        title: '<div style="font-size:16px; font-weight:800; color:#991b1b; text-align:left;">Applicant Profile Already Registered</div>',
                        html: `
                            <div style="text-align:left; font-size:12px; color:#64748b; margin-bottom:10px;">
                                Found existing loan applicant in database with matching Mobile, PAN, or Aadhaar:
                            </div>
                            <div style="max-height:200px; overflow-y:auto;">
                                ${cardsHtml}
                            </div>
                            <div style="font-size:11px; color:#7f1d1d; margin-top:8px; text-align:left; background:#fef2f2; padding:8px; border-radius:6px; border:1px solid #fecaca;">
                                Click <b>"Proceed & Register"</b> to create another loan file for this customer, or cancel to edit inputs.
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Proceed & Register Additional Loan',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#64748b'
                    }).then(r => {
                        if (r.isConfirmed) {
                            fd.append('force_duplicate', '1');
                            fetch('../api.php?api=save_applicant', { method: 'POST', body: fd })
                            .then(r2 => r2.json())
                            .then(d2 => {
                                if (d2.success) {
                                    Swal.fire({ icon: 'success', title: 'Applicant Created!', confirmButtonColor: '#FF7A00' }).then(() => {
                                        window.location.href = 'index.php';
                                    });
                                }
                            });
                        }
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Registration Failed', text: data.error || 'Failed to save applicant.', confirmButtonColor: '#FF7A00' });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Failed to connect to server.', confirmButtonColor: '#FF7A00' });
            }
        }
    </script>
</body>
</html>
