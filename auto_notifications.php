<?php
// auto_notifications.php - Automated transactional notifications & email dispatches for BFS Circle

if (!function_exists('sendAppointmentConfirmationEmail')) {
    /**
     * Dispatches high-trust professional consultation confirmation email to client
     */
    function sendAppointmentConfirmationEmail($app) {
        if (empty($app['email']) || !filter_var($app['email'], FILTER_VALIDATE_EMAIL) || str_contains($app['email'], 'no-email')) {
            return false;
        }

        $name = htmlspecialchars($app['name']);
        $slot_date = htmlspecialchars($app['slot_date'] ?: 'Upcoming Schedule');
        $slot_time = htmlspecialchars($app['slot_time'] ?: 'Confirmed Slot');
        $product = htmlspecialchars(($app['product_type'] ?: 'Finance') . ' - ' . ($app['sub_type'] ?: 'Consultation Advisory'));
        $city = htmlspecialchars($app['city'] ?: 'Agra');
        $advisor = htmlspecialchars($app['assigned_to'] ?: 'Senior Relationship Desk');
        $is_home_visit = (stripos($app['source'] ?? '', 'Home Visit') !== false || stripos($app['message'] ?? '', 'Doorstep') !== false);
        $meeting_mode = $is_home_visit ? '🏠 Doorstep Consultation (At Your Premises)' : '📞 Video / Direct Telephonic Advisory';

        $subject = "Confirmed: Your Financial Consultation Slot with Bhardwaj Financial Services [{$slot_date}]";

        $html = '
        <div style="font-family:\'Segoe UI\', Roboto, Arial, sans-serif; color:#1e293b; line-height:1.6;">
            <!-- Status Pill Banner -->
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:16px 20px; margin-bottom:22px; display:flex; align-items:center; gap:12px;">
                <div style="width:34px; height:34px; border-radius:50%; background:#16a34a; color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; flex-shrink:0;">✓</div>
                <div>
                    <h3 style="margin:0 0 2px 0; color:#166534; font-size:15px; font-weight:800;">Consultation Appointment Confirmed</h3>
                    <p style="margin:0; font-size:12px; color:#15803d;">Your preferred advisory time slot has been formally locked with our underwriting desk.</p>
                </div>
            </div>

            <p style="font-size:14px; margin-top:0;">Dear <strong>' . $name . '</strong>,</p>
            <p style="font-size:13.5px; color:#475569;">Thank you for reaching out to <strong>Bhardwaj Financial Services (BFS Circle)</strong>. We are pleased to confirm that your consultation session has been scheduled and assigned to our underwriting advisory desk.</p>

            <!-- Confirmed Schedule Card -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; margin:20px 0;">
                <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#64748b; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                    CONFIRMED CONSULTATION SPECIFICATIONS
                </div>
                
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <tr>
                        <td style="padding:6px 0; color:#64748b; width:35%;">📅 Confirmed Date:</td>
                        <td style="padding:6px 0; font-weight:800; color:#0f172a;">' . $slot_date . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#64748b;">⏰ Time Window:</td>
                        <td style="padding:6px 0; font-weight:800; color:#16a34a;">' . $slot_time . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#64748b;">💼 Advisory Product:</td>
                        <td style="padding:6px 0; font-weight:700; color:#0f172a;">' . $product . '</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#64748b;">📍 Meeting Mode:</td>
                        <td style="padding:6px 0; font-weight:700; color:#0f172a;">' . $meeting_mode . ' (' . $city . ')</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0; color:#64748b;">👤 Assigned Advisor:</td>
                        <td style="padding:6px 0; font-weight:700; color:#2563eb;">' . $advisor . '</td>
                    </tr>
                </table>
            </div>

            <!-- Documents to Keep Handy -->
            <div style="background:#fff7ed; border-left:3px solid #f97316; padding:12px 16px; margin:18px 0; border-radius:0 8px 8px 0;">
                <strong style="color:#9a3412; font-size:12.5px;">Recommended Documents to Keep Handy (For Instant Assessment):</strong>
                <ul style="margin:6px 0 0 0; padding-left:18px; font-size:12px; color:#7c2d12; line-height:1.5;">
                    <li>Primary Applicant PAN & Aadhaar Card</li>
                    <li>Latest 6 months Bank Statement (PDF or net banking)</li>
                    <li>Latest 3 months Salary Slips or 2 Years ITR (for Business/Self-Employed)</li>
                </ul>
            </div>

            <p style="font-size:13px; color:#475569; margin-top:18px;">If you wish to reschedule or have questions before the consultation, feel free to reply directly to this email or call our Relationship Desk at <strong>+91 7900979001</strong>.</p>

            <div style="margin-top:22px; padding-top:14px; border-top:1px solid #f1f5f9; font-size:11.5px; color:#94a3b8;">
                Warm regards,<br>
                <strong>Client Consultation & Underwriting Desk</strong><br>
                Bhardwaj Financial Services (BFS Circle)
            </div>
        </div>';

        return sendUniversalEmail($app['email'], $app['name'], $subject, $html, 'Appointment Confirmation Email');
    }
}

if (!function_exists('sendPartnerOnboardingEmail')) {
    /**
     * Dispatches official DSA onboarding welcome email with credentials & commission details
     */
    function sendPartnerOnboardingEmail($req, $dsa_code) {
        if (empty($req['email']) || !filter_var($req['email'], FILTER_VALIDATE_EMAIL) || str_contains($req['email'], 'partner-')) {
            return false;
        }

        $name = htmlspecialchars($req['name']);
        $profession = htmlspecialchars($req['profession'] ?: 'Channel Partner');
        $city = htmlspecialchars($req['city'] ?: 'Agra');
        $state = htmlspecialchars($req['state'] ?: 'Uttar Pradesh');

        $subject = "Welcome to BFS Circle! Your DSA Channel Partner Account is Approved [Code: {$dsa_code}]";

        $html = '
        <div style="font-family:\'Segoe UI\', Roboto, Arial, sans-serif; color:#1e293b; line-height:1.6;">
            <!-- Welcome Hero Badge -->
            <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius:12px; padding:22px 26px; color:#ffffff; margin-bottom:22px;">
                <div style="display:inline-block; background:#10b981; color:#ffffff; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
                    PARTNER ONBOARDING APPROVED
                </div>
                <h2 style="margin:0 0 6px 0; font-size:20px; font-weight:800; color:#ffffff; letter-spacing:-0.3px;">Welcome to the BFS Circle Partner Network!</h2>
                <p style="margin:0; font-size:12.5px; color:#cbd5e1; line-height:1.5;">Your registration as an authorized distribution partner has been formally verified and activated.</p>
            </div>

            <p style="font-size:14px; margin-top:0;">Dear <strong>' . $name . '</strong>,</p>
            <p style="font-size:13.5px; color:#475569;">Congratulations! We are delighted to welcome you to the <strong>Bhardwaj Financial Services (BFS Circle) Channel Partner Ecosystem</strong> as an authorized <strong>' . $profession . '</strong> partner for <strong>' . $city . ', ' . $state . '</strong>.</p>

            <!-- Official Partner Credentials Card -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin:20px 0;">
                <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#64748b; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                    YOUR OFFICIAL CHANNEL PARTNER CREDENTIALS
                </div>
                
                <div style="text-align:center; padding:14px 0 18px 0; border-bottom:1px dashed #cbd5e1; margin-bottom:14px;">
                    <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">OFFICIAL DSA PARTNER CODE</div>
                    <div style="font-size:26px; font-weight:900; letter-spacing:2px; color:#0f172a; font-family:\'Courier New\', Courier, monospace; background:#ffffff; border:2px dashed #0f172a; display:inline-block; padding:8px 22px; border-radius:8px;">
                        ' . $dsa_code . '
                    </div>
                    <div style="font-size:11px; color:#10b981; font-weight:700; margin-top:7px;">● Status: Active & Disbursement-Authorized</div>
                </div>

                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <tr>
                        <td style="padding:5px 0; color:#64748b; width:38%;">Partner Entity:</td>
                        <td style="padding:5px 0; font-weight:700; color:#0f172a;">' . $name . ' (' . $profession . ')</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0; color:#64748b;">Operating Territory:</td>
                        <td style="padding:5px 0; font-weight:700; color:#0f172a;">' . $city . ', ' . $state . '</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0; color:#64748b;">Base Commission Payout:</td>
                        <td style="padding:5px 0; font-weight:800; color:#16a34a;">0.40% on Disbursed Loan Volume</td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0; color:#64748b;">Settlement Frequency:</td>
                        <td style="padding:5px 0; font-weight:700; color:#0f172a;">Monthly Automated Bank Transfer / GST Settlement</td>
                    </tr>
                </table>
            </div>

            <!-- Key Advantages -->
            <h4 style="font-size:13.5px; font-weight:800; color:#0f172a; margin:18px 0 10px 0;">Your Partner Advantages:</h4>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px;">
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:12px;">
                    <strong style="color:#0f172a; display:block; margin-bottom:2px;">🏦 40+ Bank Tie-ups</strong>
                    Direct access to HDFC, SBI, ICICI, Axis, Kotak, BOB & top NBFCs.
                </div>
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:12px;">
                    <strong style="color:#0f172a; display:block; margin-bottom:2px;">⚡ Express File Processing</strong>
                    Fast-track underwriting & minimal turnaround time (TAT) on your cases.
                </div>
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:12px;">
                    <strong style="color:#0f172a; display:block; margin-bottom:2px;">📊 Complete Transparency</strong>
                    Track applicant status from login to sanction to final loan disbursement.
                </div>
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; font-size:12px;">
                    <strong style="color:#0f172a; display:block; margin-bottom:2px;">💼 High-Ticket Support</strong>
                    Dedicated desk for Home Loans, LAP, Project Finance & MSME Capital.
                </div>
            </div>

            <!-- 1-2-3 Next Steps -->
            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:14px 18px; margin:18px 0;">
                <strong style="color:#1e40af; font-size:13px; display:block; margin-bottom:5px;">🚀 How to Submit Your First Client File:</strong>
                <ol style="margin:0; padding-left:18px; font-size:12px; color:#1e3a8a; line-height:1.6;">
                    <li>Quote your Partner Code <strong>' . $dsa_code . '</strong> in all cases.</li>
                    <li>Forward client contact & basic requirements via WhatsApp to our Relationship Desk.</li>
                    <li>Our team coordinates doorstep document collection and bank login seamlessly.</li>
                </ol>
            </div>

            <p style="font-size:13px; color:#475569;">Our Senior Relationship Desk will connect with you shortly. If you have an urgent file to log in today, feel free to contact us at <strong>+91 7900979001</strong>.</p>

            <div style="margin-top:22px; padding-top:14px; border-top:1px solid #f1f5f9; font-size:11.5px; color:#94a3b8;">
                Warm regards,<br>
                <strong>Partner Distribution Division</strong><br>
                Bhardwaj Financial Services (BFS Circle) • <a href="https://bfsfin.com" style="color:#2563eb; text-decoration:none;">bfsfin.com</a>
            </div>
        </div>';

        return sendUniversalEmail($req['email'], $req['name'], $subject, $html, 'Partner Onboarding Approval Email');
    }
}
