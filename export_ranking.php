<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'RM'])) {
    die("Access Denied. Insufficient privileges to export leaderboard data.");
}

$tab = $_GET['tab'] ?? 'rms';

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // BOM for excel utf-8

if ($tab === 'rms') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=RM_Leaderboard_' . date('Y-m-d') . '.csv');

    fputcsv($output, [
        'Rank',
        'Relationship Manager',
        'Username / Email',
        'Role',
        'Assigned Applications',
        'Approved / Sanctioned',
        'Disbursed Volume (INR)',
        'Monthly Target (INR)',
        'Achievement (%)',
        'Performance Status'
    ]);

    $rm_sql = "
        SELECT 
            u.id as user_id,
            COALESCE(e.full_name, u.name, u.full_name, u.username) as rm_name,
            u.username,
            u.role,
            COALESCE(u.monthly_target, 15000000) as monthly_target,
            COUNT(DISTINCT a.id) as total_assigned,
            COUNT(DISTINCT CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed','sanctioned','phase 4','phase 5') THEN a.id END) as approved_cnt,
            COALESCE(SUM(CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed') THEN a.loan_amount_requested ELSE 0 END), 0) as disbursed_volume
        FROM users u
        LEFT JOIN employees e ON (e.user_id = u.id OR e.official_email = u.username)
        LEFT JOIN applicants a ON (a.assigned_to = u.username OR a.assigned_to = u.name OR a.assigned_to = e.full_name)
        WHERE (
            u.role = 'RM' 
            OR (u.role = 'Staff' AND (e.designation LIKE '%Relationship Manager%' OR e.designation = 'RM' OR e.designation LIKE '%RM%'))
        )
        AND u.role NOT IN ('Agent', 'Partner', 'Builder', 'CA', 'DSA', 'Connector')
        AND LOWER(u.username) != 'admin' AND LOWER(u.role) != 'admin'
        GROUP BY u.id
        ORDER BY disbursed_volume DESC, approved_cnt DESC, total_assigned DESC
    ";
    $rm_rankings = $db->query($rm_sql)->fetchAll(PDO::FETCH_ASSOC);

    $rank = 1;
    foreach ($rm_rankings as $row) {
        $target = $row['monthly_target'] > 0 ? $row['monthly_target'] : 15000000;
        $pct = min(100, round(($row['disbursed_volume'] / $target) * 100, 1));
        $status = ($rank === 1) ? 'Top RM Leader' : (($rank <= 3) ? 'Star RM' : 'Active RM');

        fputcsv($output, [
            $rank,
            $row['rm_name'],
            $row['username'],
            $row['role'],
            $row['total_assigned'],
            $row['approved_cnt'],
            $row['disbursed_volume'],
            $target,
            $pct . '%',
            $status
        ]);
        $rank++;
    }

} elseif ($tab === 'agents') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Partner_Leaderboard_' . date('Y-m-d') . '.csv');

    fputcsv($output, [
        'Rank',
        'Agent / Referral Partner',
        'Username',
        'Partner Type',
        'Mobile',
        'Sourced Leads',
        'Approved / Sanctioned',
        'Disbursed Volume (INR)',
        'Performance Status'
    ]);

    $agent_sql = "
        SELECT 
            u.id as user_id,
            COALESCE(r.full_name, u.name, u.full_name, u.username) as agent_name,
            u.username,
            COALESCE(r.referrer_type, u.role, 'Agent') as partner_type,
            COALESCE(r.mobile, '') as mobile,
            COUNT(DISTINCT l.id) as total_sourced_leads,
            COUNT(DISTINCT CASE WHEN LOWER(l.stage) IN ('converted','disbursed','login','completed') THEN l.id END) + 
            COUNT(DISTINCT CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed','sanctioned','phase 4','phase 5') THEN a.id END) as approved_leads,
            COALESCE(SUM(CASE WHEN LOWER(l.stage) IN ('converted','disbursed','completed') THEN l.loan_amount ELSE 0 END), 0) +
            COALESCE(SUM(CASE WHEN LOWER(a.overall_status) IN ('completed','disbursed') THEN a.loan_amount_requested ELSE 0 END), 0) as total_volume
        FROM users u
        LEFT JOIN referrals r ON (r.user_id = u.id OR r.referral_id = u.username OR r.mobile = u.username)
        LEFT JOIN leads l ON (l.added_by = u.username OR l.added_by = r.referral_id OR l.added_by = r.full_name)
        LEFT JOIN applicants a ON (a.added_by = u.username OR a.referral_id = r.referral_id OR a.added_by = r.referral_id)
        WHERE u.role IN ('Agent','Partner','Builder','CA','DSA','Connector') OR r.id IS NOT NULL
        GROUP BY u.id
        ORDER BY approved_leads DESC, total_sourced_leads DESC, total_volume DESC
    ";
    $agent_rankings = $db->query($agent_sql)->fetchAll(PDO::FETCH_ASSOC);

    $rank = 1;
    foreach ($agent_rankings as $ag) {
        $status = ($rank === 1) ? 'Platinum Agent' : (($rank <= 3) ? 'Star Partner' : 'Active Partner');
        fputcsv($output, [
            $rank,
            $ag['agent_name'],
            $ag['username'],
            $ag['partner_type'],
            $ag['mobile'],
            $ag['total_sourced_leads'],
            $ag['approved_leads'],
            $ag['total_volume'],
            $status
        ]);
        $rank++;
    }

} else {
    // Field visits
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Field_Staff_Leaderboard_' . date('Y-m-d') . '.csv');

    fputcsv($output, ['Rank', 'Executive Name', 'Total Field Visits', 'Last Active (Visit Date)']);

    $stmt = $db->query("
        SELECT executive_name, COUNT(*) as total_visits, MAX(created_at) as last_activity 
        FROM field_visits 
        GROUP BY executive_name 
        ORDER BY total_visits DESC, last_activity DESC
    ");
    $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rank = 1;
    foreach ($rankings as $staff) {
        fputcsv($output, [
            $rank,
            $staff['executive_name'],
            $staff['total_visits'],
            date('d M Y, h:i A', strtotime($staff['last_activity']))
        ]);
        $rank++;
    }
}

fclose($output);
exit;
