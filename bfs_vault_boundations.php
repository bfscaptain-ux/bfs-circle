<?php
/**
 * BFS Document Boundations & Quality Gatekeeper Engine
 * 
 * Enforces strict financial compliance, file integrity, expiry tracking,
 * and hard gatekeeping before any loan file can proceed to bank login / Phase 3.
 */

require_once 'config.php';

class BfsVaultBoundations {
    const MIN_FILE_SIZE_BYTES = 20480;       // 20 KB (blocks corrupted/blank scans)
    const MAX_FILE_SIZE_BYTES = 26214400;    // 25 MB (standard bank portal cap)
    const ALLOWED_EXTS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    const EXPIRY_THRESHOLDS = [
        'salary'    => 90,  // Salary slips older than 90 days are expired
        'bank'      => 45,  // Bank statements older than 45 days are expired
        'utility'   => 60,  // Electricity/utility bill older than 60 days
        'cibil'     => 30   // CIBIL credit report older than 30 days
    ];

    /**
     * Pre-upload Boundation: Validates file size, extension, and integrity
     */
    public static function validateUploadFile(array $file, string $category, string $docName): array {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error or partial file transfer.'];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size < self::MIN_FILE_SIZE_BYTES) {
            return ['valid' => false, 'error' => 'Boundation Error: File is too small (<20 KB). Blurry or blank documents are rejected by bank underwriters.'];
        }
        if ($size > self::MAX_FILE_SIZE_BYTES) {
            return ['valid' => false, 'error' => 'Boundation Error: File exceeds 25 MB bank portal limit. Please compress or select a smaller PDF.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS)) {
            return ['valid' => false, 'error' => "Boundation Error: Format '.$ext' not permitted. Only PDF, JPG, PNG, and DOCX are accepted."];
        }

        return ['valid' => true, 'ext' => $ext, 'size' => $size];
    }

    /**
     * Duplicate Document Boundation: Checks if the identical document was already uploaded
     */
    public static function checkDuplicateFile(PDO $db, int $applicantId, string $tmpFilePath): array {
        if (!file_exists($tmpFilePath)) return ['is_duplicate' => false];
        $fileHash = hash_file('sha256', $tmpFilePath);

        $stmt = $db->prepare("SELECT id, document_name, document_category FROM applicant_documents WHERE applicant_id = ? AND notes LIKE ?");
        $stmt->execute([$applicantId, "%hash:{$fileHash}%"]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return [
                'is_duplicate' => true,
                'error' => "Boundation Alert: This exact identical file was already uploaded as '{$existing['document_name']}' under '{$existing['document_category']}'."
            ];
        }

        return ['is_duplicate' => false, 'hash' => $fileHash];
    }

    /**
     * Recency & Expiry Boundation: Detects if document is too old for bank login
     */
    public static function evaluateDocumentRecency(string $uploadedAt, string $category, string $docName): array {
        $uploadTime = strtotime($uploadedAt);
        $ageDays = floor((time() - $uploadTime) / 86400);

        $nameLower = strtolower($docName);
        $catLower = strtolower($category);

        $maxDays = null;
        $docType = 'general';

        if (strpos($nameLower, 'statement') !== false || strpos($nameLower, 'bank') !== false) {
            $maxDays = self::EXPIRY_THRESHOLDS['bank'];
            $docType = 'Bank Statement';
        } elseif (strpos($nameLower, 'salary') !== false || strpos($nameLower, 'slip') !== false) {
            $maxDays = self::EXPIRY_THRESHOLDS['salary'];
            $docType = 'Salary Slip';
        } elseif (strpos($nameLower, 'electricity') !== false || strpos($nameLower, 'utility') !== false || strpos($nameLower, 'bill') !== false) {
            $maxDays = self::EXPIRY_THRESHOLDS['utility'];
            $docType = 'Utility Bill';
        }

        $isExpired = ($maxDays !== null && $ageDays > $maxDays);

        return [
            'age_days'   => $ageDays,
            'max_days'   => $maxDays,
            'is_expired' => $isExpired,
            'doc_type'   => $docType,
            'warning'    => $isExpired ? "⚠️ EXPIRED: {$docType} is {$ageDays} days old (limit: {$maxDays} days). Bank will request a fresh copy." : null
        ];
    }

    /**
     * Phase 3 Proceed Boundation (Hard Gatekeeper):
     * Checks if all mandatory documents are uploaded AND verified before proceeding to disbursement / bank login
     */
    public static function evaluatePhase3Gatekeeper(PDO $db, int $applicantId): array {
        $stmt = $db->prepare("SELECT * FROM applicants WHERE id = ?");
        $stmt->execute([$applicantId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) return ['eligible' => false, 'error' => 'Applicant not found'];

        $co_stmt = $db->prepare("SELECT * FROM co_applicants WHERE applicant_id = ?");
        $co_stmt->execute([$applicantId]);
        $co_apps = $co_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Required checklist from master rules
        $suggestions = getLoanDocumentSuggestions(
            $app['loan_type'] ?? '',
            $app['loan_sub_type'] ?? '',
            $app['employment_type'] ?? 'Salaried',
            $co_apps
        );

        $mandatoryReqs = array_filter($suggestions, function($s) {
            return !empty($s['is_mandatory']);
        });

        // Uploaded documents
        $d_stmt = $db->prepare("SELECT * FROM applicant_documents WHERE applicant_id = ? AND status != 'Rejected'");
        $d_stmt->execute([$applicantId]);
        $uploadedDocs = $d_stmt->fetchAll(PDO::FETCH_ASSOC);

        $missingMandatory = [];
        $unapprovedMandatory = [];
        $verifiedMandatoryCount = 0;

        foreach ($mandatoryReqs as $req) {
            $matched = false;
            $approved = false;

            foreach ($uploadedDocs as $upDoc) {
                // Fuzzy match document name or category
                $upName = strtolower(trim($upDoc['document_name']));
                $reqName = strtolower(trim($req['doc_name']));

                if (strpos($upName, $reqName) !== false || strpos($reqName, $upName) !== false 
                    || (strpos($reqName, 'pan') !== false && strpos($upName, 'pan') !== false)
                    || (strpos($reqName, 'aadhaar') !== false && strpos($upName, 'addhar') !== false)
                    || (strpos($reqName, 'photo') !== false && strpos($upName, 'photo') !== false)
                    || (strpos($reqName, 'salary') !== false && strpos($upName, 'salary') !== false)
                    || (strpos($reqName, 'bank statement') !== false && strpos($upName, 'statement') !== false)
                ) {
                    $matched = true;
                    if ($upDoc['qa_status'] === 'Approved' || $upDoc['status'] === 'Verified') {
                        $approved = true;
                    }
                    break;
                }
            }

            if (!$matched) {
                $missingMandatory[] = $req['doc_name'] . " (" . $req['category'] . ")";
            } elseif (!$approved) {
                $unapprovedMandatory[] = $req['doc_name'] . " [Uploaded but QA Pending Verification]";
            } else {
                $verifiedMandatoryCount++;
            }
        }

        $totalMandatory = count($mandatoryReqs);
        $completionPct = $totalMandatory > 0 ? round(($verifiedMandatoryCount / $totalMandatory) * 100) : 100;
        $isEligible = (empty($missingMandatory) && empty($unapprovedMandatory));

        return [
            'eligible'               => $isEligible,
            'completion_pct'         => $completionPct,
            'total_mandatory'        => $totalMandatory,
            'verified_mandatory'     => $verifiedMandatoryCount,
            'missing_mandatory'      => $missingMandatory,
            'unapproved_mandatory'   => $unapprovedMandatory,
            'gatekeeper_status'      => $isEligible ? 'UNLOCKED_PASSED' : 'LOCKED_BLOCKED'
        ];
    }
}
