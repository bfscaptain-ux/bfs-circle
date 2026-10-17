<?php
require_once 'config.php';

$id = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

if (!$id && !empty($token)) {
    // Extract ID from token if passed as token
    $parts = explode('_', $token);
    $id = (int)end($parts);
}

if (!$id) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'><h2>Invalid Upload Link</h2><p>Please request a fresh upload link from your loan RM.</p></div>");
}

$stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
$stmt->execute([$id]);
$applicant = $stmt->fetch();

if (!$applicant) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'><h2>Application Not Found</h2></div>");
}

// Fetch Co-Applicants
$co_stmt = $db->prepare("SELECT id, full_name, relationship FROM co_applicants WHERE applicant_id = ?");
$co_stmt->execute([$id]);
$co_applicants = $co_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Loan Documents - <?php echo htmlspecialchars($applicant['customer_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --primary: #0f172a;
            --accent: #2563eb;
            --success: #16a34a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; color: var(--text-main); padding-bottom: 40px; }
        
        .header-bar {
            background: var(--bg-dark);
            color: #ffffff;
            padding: 20px 16px;
            text-align: center;
            border-bottom: 3px solid #1e293b;
        }
        .header-bar h1 { font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 800; letter-spacing: -0.5px; }
        .header-bar p { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        
        .container { max-width: 600px; margin: 20px auto; padding: 0 16px; }
        
        .card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        }
        
        .btn-upload {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-upload:hover { background: #1e293b; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #0f172a;
            outline: none;
        }
        .form-control:focus { border-color: #0f172a; }
        
        .drop-box {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            position: relative;
        }
        .drop-box:hover { border-color: #0f172a; background: #ffffff; }
        
        .success-banner {
            display: none;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 14px;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="header-bar">
        <h1>BFS FINANCIAL SERVICES</h1>
        <p>Secure Client Document Self-Upload Portal</p>
    </div>

    <div class="container">
        <!-- Applicant Details Summary Card -->
        <div class="card">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Applicant Name</div>
            <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 2px; font-family: 'Outfit', sans-serif;">
                <?php echo htmlspecialchars($applicant['customer_name']); ?>
            </div>
            <div style="display: flex; gap: 14px; margin-top: 10px; font-size: 12px; color: #475569;">
                <div>Loan Ref: <strong><?php echo htmlspecialchars($applicant['loan_id']); ?></strong></div>
                <div>•</div>
                <div>Loan Type: <strong><?php echo htmlspecialchars($applicant['loan_type']); ?></strong></div>
            </div>
        </div>

        <!-- Upload Card -->
        <div class="card">
            <h3 style="font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="upload-cloud" style="width: 18px; color: #0f172a;"></i> Upload New Document
            </h3>

            <form id="client-upload-form" onsubmit="handleClientUpload(event)">
                <input type="hidden" name="applicant_id" value="<?php echo $id; ?>">

                <div class="form-group">
                    <label>Document Category *</label>
                    <select name="document_category" id="doc_cat" class="form-control" required>
                        <option value="Basic KYC">🪪 Basic KYC (PAN, Aadhaar, Photo, Address)</option>
                        <option value="Income Proof">💼 Income Proof (Salary Slips, Bank Statement, ITR)</option>
                        <option value="Property / Asset Docs">🏠 Property Docs (Registry, Map, Tax, Bill)</option>
                        <option value="Business Proof">🏢 Business Proof (GST, MSME, Trade License)</option>
                        <option value="Vehicle Docs">🚗 Vehicle Docs (RC Copy, Insurance)</option>
                        <option value="Other">📁 Other Document</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Document Name *</label>
                    <input type="text" name="document_name" id="doc_name" class="form-control" placeholder="e.g. PAN Card Front / Salary Slip Oct" required>
                </div>

                <div class="form-group">
                    <label>Document Owner *</label>
                    <select name="owner_val" class="form-control" required>
                        <option value="Applicant_0">Primary Applicant (<?php echo htmlspecialchars($applicant['customer_name']); ?>)</option>
                        <?php foreach ($co_applicants as $co): ?>
                        <option value="CoApplicant_<?php echo $co['id']; ?>">Co-Applicant: <?php echo htmlspecialchars($co['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select File / Snap Photo *</label>
                    <div class="drop-box" onclick="document.getElementById('client-file-input').click()">
                        <i data-lucide="camera" style="width: 32px; height: 32px; color: #0f172a; margin-bottom: 6px;"></i>
                        <div style="font-weight: 700; font-size: 13.5px; color: #0f172a;">Choose File or Take Photo</div>
                        <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">PDF, JPG, PNG accepted (Max 25MB)</div>
                        <input type="file" name="document_file[]" id="client-file-input" accept="image/*,.pdf" style="display:none;" onchange="updateFileLabel(this)">
                        <div id="file-label" style="margin-top: 8px; font-weight: 700; color: #16a34a; font-size: 12.5px;"></div>
                    </div>
                </div>

                <button type="submit" class="btn-upload" id="submit-btn">
                    <i data-lucide="send"></i> Submit Document to BFS Loan File
                </button>
            </form>

            <div id="success-banner" class="success-banner">
                ✅ Document uploaded successfully! Your RM has been notified.
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function updateFileLabel(input) {
            const label = document.getElementById('file-label');
            if (input.files && input.files[0]) {
                label.innerText = 'Selected: ' + input.files[0].name;
            } else {
                label.innerText = '';
            }
        }

        async function handleClientUpload(e) {
            e.preventDefault();
            const form = document.getElementById('client-upload-form');
            const btn = document.getElementById('submit-btn');
            const banner = document.getElementById('success-banner');
            const fileInput = document.getElementById('client-file-input');

            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please select a file or take a photo first!');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Uploading...';
            banner.style.display = 'none';

            const formData = new FormData(form);

            try {
                const res = await fetch('api.php?api=upload_applicant_document', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    banner.style.display = 'block';
                    form.reset();
                    document.getElementById('file-label').innerText = '';
                } else {
                    alert(data.error || 'Upload failed. Please try again.');
                }
            } catch (err) {
                alert('Connection error during upload.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="send"></i> Submit Document to BFS Loan File';
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
