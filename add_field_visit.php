<?php
require_once 'config.php';
$current_page = 'add_field_visit.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Field Visit - BFS Financial Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #FF7A00;
            --primary-dark: #E66A00;
            --secondary: #1E293B;
            --bg-color: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Placeholder - Should ideally be included */
        .sidebar {
            width: 260px;
            background: var(--secondary);
            color: white;
            padding: 24px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-logo i {
            color: var(--primary);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #94A3B8;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-item.active {
            background: var(--primary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }

        .header {
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 24px;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 32px;
            max-width: 900px;
            margin: 0 auto;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="date"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-main);
            background: #fff;
            transition: all 0.2s;
        }
        
        input[readonly] {
            background: #F1F5F9;
            cursor: not-allowed;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 122, 0, 0.1);
        }

        /* Photo Upload Section */
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #F8FAFC;
        }

        .upload-zone:hover {
            border-color: var(--primary);
            background: rgba(255, 122, 0, 0.02);
        }

        .upload-zone i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 16px;
        }

        .upload-zone h4 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .upload-zone p {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        #photo_input {
            display: none;
        }

        .preview-container {
            display: none;
            margin-top: 16px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            width: 100%;
            max-width: 300px;
            border: 1px solid var(--border-color);
        }

        .preview-container img {
            width: 100%;
            height: auto;
            display: block;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        #custom_profession_group {
            display: none;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: none;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        /* Mobile Header - Visible only on mobile */
        .mobile-header {
            display: none;
            background: var(--primary);
            color: white;
            padding: 16px;
            align-items: center;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .mobile-header .back-btn {
            color: white;
            font-size: 20px;
            text-decoration: none;
        }

        .mobile-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; background: var(--card-bg); }
            .sidebar { display: none; } /* Hide sidebar completely like a native app */
            .mobile-header { display: flex; }
            .main-content { padding: 16px 12px; }
            .header { display: none; } /* Hide desktop header */
            .form-grid { grid-template-columns: 1fr; gap: 16px; }
            .form-card { padding: 0; box-shadow: none; border-radius: 0; }
            input[type="text"], input[type="date"], input[type="tel"], select, textarea {
                padding: 14px 16px; /* Larger touch targets */
                font-size: 16px; /* Prevent iOS zoom */
            }
            .btn-submit { padding: 16px; font-size: 16px; border-radius: 12px; }
            .upload-zone { padding: 30px 16px; }
        }
    </style>
</head>
<body>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <a href="field_visits.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <h2>Record Field Visit</h2>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <div style="background:var(--primary);color:white;width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:bold;">A</div>
            BFS Financial Services
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="applicants_list.php" class="nav-item"><i class="fas fa-file-invoice"></i> Loan Applications</a>
            <a href="field_visits.php" class="nav-item"><i class="fas fa-map-marked-alt"></i> Manage Visits</a>
            <a href="add_field_visit.php" class="nav-item active"><i class="fas fa-camera"></i> Add Field Visit</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Record Field Visit</h1>
            <p>Capture details and live location photos from your field visits.</p>
        </div>
        
        <div id="alert_box" class="alert"></div>

        <div class="form-card">
            <form id="fieldVisitForm" onsubmit="submitForm(event)">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Visit Date</label>
                        <input type="date" id="visit_date" name="visit_date" required readonly>
                    </div>
                    <div class="form-group">
                        <label>Executive Name</label>
                        <input type="text" id="executive_name" name="executive_name" value="<?= htmlspecialchars($_SESSION['username'] ?? 'Staff') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Person Name</label>
                        <input type="text" id="person_name" name="person_name" placeholder="Enter full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <input type="tel" id="mobile" name="mobile" placeholder="10-digit mobile" pattern="[0-9]{10}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Alternate Number (Optional)</label>
                        <input type="tel" id="alt_mobile" name="alt_mobile" placeholder="Alternate mobile" pattern="[0-9]{10}">
                    </div>

                    <div class="form-group">
                        <label>Profession</label>
                        <select id="profession" name="profession" onchange="toggleCustomProfession()" required>
                            <option value="">Select Profession</option>
                            <option value="CA">CA</option>
                            <option value="PROPERTY DEALER">PROPERTY DEALER</option>
                            <option value="ARCHITECT">ARCHITECT</option>
                            <option value="CONTRACTOR">CONTRACTOR</option>
                            <option value="BUSINESS OWNER">BUSINESS OWNER</option>
                            <option value="OTHER">OTHER</option>
                        </select>
                    </div>

                    <div class="form-group" id="custom_profession_group">
                        <label>Specify Other Profession</label>
                        <input type="text" id="custom_profession" name="custom_profession" placeholder="Type profession here...">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Firm Name</label>
                        <input type="text" id="firm_name" name="firm_name" placeholder="Name of company or firm" required>
                    </div>

                    <div class="form-group">
                        <label>State</label>
                        <input type="text" id="state" name="state" placeholder="E.g., Maharashtra" required>
                    </div>
                    
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" id="city" name="city" placeholder="E.g., Mumbai" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" id="pincode" name="pincode" placeholder="6-digit pincode" pattern="[0-9]{6}">
                    </div>
                    
                    <div class="form-group">
                        <label>Lead Quality</label>
                        <select id="lead_quality" name="lead_quality" required>
                            <option value="">Select Quality</option>
                            <option value="Hot"> Hot (Very Interested)</option>
                            <option value="Warm">️ Warm (Interested, Needs Followup)</option>
                            <option value="Cold">️ Cold (Not Interested Right Now)</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Remarks / Follow-up Details</label>
                        <textarea id="remarks" name="remarks" rows="3" placeholder="What was discussed?" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Next Meeting Date</label>
                        <input type="date" id="next_meeting_date" name="next_meeting_date">
                    </div>

                    <!-- Live Photo Capture -->
                    <div class="form-group full-width">
                        <label>Location Photo Evidence</label>
                        
                        <div class="upload-zone" onclick="document.getElementById('photo_input').click()">
                            <i class="fas fa-camera"></i>
                            <h4>Tap to Take a Photo</h4>
                            <p>Capture visiting card or shop front</p>
                        </div>
                        <!-- capture="environment" ensures it opens the rear camera directly on mobile -->
                        <input type="file" id="photo_input" name="photo" accept="image/*" capture="environment" onchange="previewImage(this)">
                        
                        <div class="preview-container" id="preview_container">
                            <img id="image_preview" src="#" alt="Preview">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-save"></i> Save Visit Record
                </button>
            </form>
        </div>
    </div>

    <script>
        // Set today's date
        document.getElementById('visit_date').valueAsDate = new Date();

        function toggleCustomProfession() {
            const select = document.getElementById('profession');
            const customGroup = document.getElementById('custom_profession_group');
            const customInput = document.getElementById('custom_profession');
            
            if (select.value === 'OTHER') {
                customGroup.style.display = 'block';
                customInput.required = true;
            } else {
                customGroup.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        }

        function previewImage(input) {
            const container = document.getElementById('preview_container');
            const preview = document.getElementById('image_preview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                container.style.display = 'none';
            }
        }

        async function submitForm(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const alertBox = document.getElementById('alert_box');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            const form = document.getElementById('fieldVisitForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('api.php?api=save_field_visit', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                
                if (response.ok) {
                    alertBox.className = 'alert alert-success';
                    alertBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + res.message;
                    alertBox.style.display = 'block';
                    form.reset();
                    document.getElementById('visit_date').valueAsDate = new Date();
                    document.getElementById('preview_container').style.display = 'none';
                    toggleCustomProfession();
                    window.scrollTo(0,0);
                } else {
                    alertBox.className = 'alert alert-error';
                    alertBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + (res.error || 'Failed to save');
                    alertBox.style.display = 'block';
                    window.scrollTo(0,0);
                }
            } catch (err) {
                alertBox.className = 'alert alert-error';
                alertBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error occurred';
                alertBox.style.display = 'block';
            }
            
            btn.innerHTML = '<i class="fas fa-save"></i> Save Visit Record';
            btn.disabled = false;
        }
    </script>
</body>
</html>
