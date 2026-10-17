<?php
define('IS_SUBFOLDER', true);
require_once __DIR__ . '/../config.php';
require_role(['RM']);


// ─── AJAX JSON Endpoints (called before any HTML output) ─────────────────────
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    $rm_id      = (int)$_SESSION['user_id'];
    $month_start = date('Y-m-01 00:00:00');

    // ── Summary endpoint ──────────────────────────────────────────────────────
    if ($_GET['ajax'] === 'summary') {
        try {
            $s = $db->prepare("SELECT
                COALESCE(SUM(gross_payout), 0)                                                      AS total_gross,
                COALESCE(SUM(tds_deducted), 0)                                                      AS total_tds,
                COALESCE(SUM(CASE WHEN status='Paid'    THEN net_payable ELSE 0 END), 0)            AS total_received,
                COALESCE(SUM(CASE WHEN status='Pending' OR status='Approved' THEN net_payable ELSE 0 END), 0) AS total_pending,
                COALESCE(SUM(CASE WHEN status='Paid' AND paid_on >= ?  THEN net_payable ELSE 0 END), 0) AS this_month,
                COUNT(*)                                                                             AS total_tx,
                SUM(CASE WHEN status='Paid'    THEN 1 ELSE 0 END)                                   AS paid_tx,
                SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END)                                   AS pending_tx,
                SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END)                                  AS approved_tx
                FROM payout_distributions WHERE payee_user_id = ?");
            $s->execute([$month_start, $rm_id]);
            $data = $s->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // ── Records endpoint (paginated) ──────────────────────────────────────────
    if ($_GET['ajax'] === 'records') {
        $filter   = trim($_GET['status'] ?? '');
        $search   = trim($_GET['q'] ?? '');
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $per_page = min(50, max(5, (int)($_GET['per_page'] ?? 20)));
        $offset   = ($page - 1) * $per_page;

        // Build WHERE
        $conditions = ['pd.payee_user_id = ?'];
        $params     = [$rm_id];

        if ($filter) {
            $conditions[] = 'pd.status = ?';
            $params[]     = $filter;
        }
        if ($search) {
            $conditions[] = '(a.customer_name LIKE ? OR a.loan_id LIKE ? OR a.loan_type LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
        }

        $where = implode(' AND ', $conditions);

        try {
            // Count (separate lightweight query)
            $ct = $db->prepare("SELECT COUNT(*) FROM payout_distributions pd
                LEFT JOIN applicants a ON a.id = pd.applicant_id
                WHERE $where");
            $ct->execute($params);
            $total = (int)$ct->fetchColumn();

            // Data
            $p2 = array_merge($params, [$per_page, $offset]);
            $rs = $db->prepare("
                SELECT pd.id, pd.status, pd.gross_payout, pd.tds_deducted, pd.net_payable,
                       pd.transaction_ref, pd.paid_on, pd.created_at,
                       pd.commission_percentage,
                       a.customer_name, a.loan_id, a.loan_type,
                       a.loan_amount_requested AS loan_amount
                FROM payout_distributions pd
                LEFT JOIN applicants a ON a.id = pd.applicant_id
                WHERE $where
                ORDER BY pd.created_at DESC
                LIMIT ? OFFSET ?");
            $rs->execute($p2);
            $rows = $rs->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok'         => true,
                'total'      => $total,
                'page'       => $page,
                'per_page'   => $per_page,
                'total_pages'=> (int)ceil($total / $per_page),
                'rows'       => $rows
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown endpoint']);
    exit;
}

// ─── Regular page render ──────────────────────────────────────────────────────
$page_title    = 'My Earnings & Wallet';
$page_subtitle = 'Commission history, pending payouts and wallet ledger';
require_once __DIR__ . '/header.php';
?>
<style>
/* ─── Page shell ─────────────────────────────────────────────────── */
.we-wrap { padding: 0 0 40px; }

/* ─── Hero ───────────────────────────────────────────────────────── */
.we-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 16px; padding: 28px 32px; color: #fff;
    margin-bottom: 22px; position: relative; overflow: hidden;
}
.we-hero::before {
    content:''; position:absolute; top:-50px; right:-50px;
    width:220px; height:220px;
    background: radial-gradient(circle, rgba(16,185,129,.18) 0%, transparent 70%);
    border-radius:50%;
}
.we-hero::after {
    content:''; position:absolute; bottom:-60px; left:80px;
    width:160px; height:160px;
    background: radial-gradient(circle, rgba(56,189,248,.1) 0%, transparent 70%);
    border-radius:50%;
}
.we-hero-inner { position: relative; z-index: 1; }
.we-hero-amount { font-size:42px; font-weight:900; color:#4ade80; font-family:'Outfit',sans-serif; letter-spacing:-1.5px; line-height:1; margin:6px 0 10px; }
.we-hero-pills  { display:flex; gap:8px; flex-wrap:wrap; }
.we-pill { display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); border-radius:20px; padding:4px 12px; font-size:11px; color:#94a3b8; font-weight:600; }

/* ─── Metric grid ────────────────────────────────────────────────── */
.we-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(185px,1fr)); gap:14px; margin-bottom:22px; }
.we-metric { background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:18px 20px; }
.we-metric .lbl { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:6px; }
.we-metric .val { font-size:22px; font-weight:900; font-family:'Outfit',sans-serif; line-height:1; margin-bottom:4px; }
.we-metric .sub { font-size:11px; color:#94a3b8; }
.we-metric.c-green { border-left:4px solid #10b981; }
.we-metric.c-amber { border-left:4px solid #f59e0b; }
.we-metric.c-blue  { border-left:4px solid #0284c7; }
.we-metric.c-red   { border-left:4px solid #ef4444; }

/* ─── Controls bar ───────────────────────────────────────────────── */
.we-controls { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px; margin-bottom:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.we-filter-btn { padding:6px 14px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; font-size:12px; font-weight:600; color:#334155; cursor:pointer; transition:all .15s; white-space:nowrap; }
.we-filter-btn:hover, .we-filter-btn.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.we-search { flex:1; min-width:180px; padding:7px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; }
.we-search:focus { border-color:#0f172a; }
.we-per-page { padding:7px 10px; border:1px solid #e2e8f0; border-radius:8px; font-size:12px; outline:none; }
.we-results-label { margin-left:auto; font-size:12px; color:#94a3b8; font-weight:600; white-space:nowrap; }

/* ─── Table card ─────────────────────────────────────────────────── */
.we-table-card { background:#fff; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; }
.we-table-wrap { overflow-x:auto; min-height:280px; }
.we-table { width:100%; border-collapse:collapse; font-size:13px; }
.we-table thead th { background:#f8fafc; padding:11px 14px; text-align:left; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.we-table thead th.num { text-align:right; }
.we-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
.we-table tbody tr:hover { background:#fafbff; }
.we-table tbody td { padding:11px 14px; vertical-align:middle; }
.we-table tbody td.num { text-align:right; }

/* ─── Status badges ──────────────────────────────────────────────── */
.bs { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; display:inline-block; white-space:nowrap; }
.bs-paid      { background:#dcfce7; color:#15803d; }
.bs-pending   { background:#fef3c7; color:#d97706; }
.bs-approved  { background:#dbeafe; color:#1d4ed8; }
.bs-cancelled { background:#fee2e2; color:#dc2626; }
.bs-hold      { background:#f1f5f9; color:#475569; }

/* ─── Skeleton loader ────────────────────────────────────────────── */
@keyframes shimmer { 0%{background-position:-600px 0} 100%{background-position:600px 0} }
.skel {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 600px 100%;
    animation: shimmer 1.4s infinite;
    border-radius: 6px;
}
.skel-hero-line  { height:14px; margin-bottom:10px; }
.skel-hero-val   { height:46px; width:220px; margin-bottom:14px; border-radius:8px; }
.skel-metric     { height:80px; border-radius:12px; }
.skel-row        { display:flex; gap:10px; padding:14px; border-bottom:1px solid #f1f5f9; }
.skel-cell       { height:14px; border-radius:4px; flex-shrink:0; }

/* ─── Pagination ─────────────────────────────────────────────────── */
.we-pagination { padding:14px 18px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
.we-page-info  { font-size:12px; color:#64748b; font-weight:600; }
.we-page-btns  { display:flex; gap:5px; flex-wrap:wrap; }
.we-page-btn   { padding:5px 11px; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; font-size:12px; font-weight:700; color:#334155; cursor:pointer; transition:all .15s; }
.we-page-btn:hover  { background:#0f172a; color:#fff; border-color:#0f172a; }
.we-page-btn.active { background:#0f172a; color:#fff; border-color:#0f172a; }
.we-page-btn:disabled { opacity:.4; cursor:default; }

/* ─── Empty / error state ────────────────────────────────────────── */
.we-empty { text-align:center; padding:50px 20px; color:#94a3b8; }
.we-empty i { display:block; margin:0 auto 12px; }
</style>

<div class="view-container we-wrap">

<!-- ─── HERO (skeleton while loading) ───────────────────────────── -->
<div class="we-hero" id="we-hero">
    <div class="we-hero-inner" id="we-hero-content">
        <!-- skeleton -->
        <div class="skel skel-hero-line" style="width:160px; background:rgba(255,255,255,.1);"></div>
        <div class="skel skel-hero-val"  style="background:rgba(255,255,255,.1);"></div>
        <div style="display:flex;gap:8px;">
            <div class="skel" style="width:90px;height:24px;border-radius:20px;background:rgba(255,255,255,.1);"></div>
            <div class="skel" style="width:70px;height:24px;border-radius:20px;background:rgba(255,255,255,.1);"></div>
            <div class="skel" style="width:110px;height:24px;border-radius:20px;background:rgba(255,255,255,.1);"></div>
        </div>
    </div>
</div>

<!-- ─── METRIC CARDS (skeleton) ──────────────────────────────────── -->
<div class="we-metrics" id="we-metrics">
    <?php for($i=0;$i<4;$i++): ?>
    <div class="we-metric"><div class="skel skel-metric"></div></div>
    <?php endfor; ?>
</div>

<!-- ─── CONTROLS BAR ─────────────────────────────────────────────── -->
<div class="we-controls">
    <span style="font-size:12px;font-weight:700;color:#475569;">Filter:</span>
    <button class="we-filter-btn active" data-status="">All</button>
    <button class="we-filter-btn" data-status="Pending">⏳ Pending</button>
    <button class="we-filter-btn" data-status="Paid">✅ Paid</button>
    <button class="we-filter-btn" data-status="Approved">📋 Approved</button>
    <button class="we-filter-btn" data-status="Cancelled">❌ Cancelled</button>
    <input type="text" class="we-search" id="we-search" placeholder="🔍 Search applicant, loan ID..." oninput="debouncedSearch()">
    <select class="we-per-page" id="we-per-page" onchange="resetAndLoad()">
        <option value="15">15 / page</option>
        <option value="25">25 / page</option>
        <option value="50">50 / page</option>
    </select>
    <span class="we-results-label" id="we-results-label">Loading...</span>
</div>

<!-- ─── TABLE CARD ────────────────────────────────────────────────── -->
<div class="we-table-card">
    <div class="we-table-wrap" id="we-table-wrap">
        <!-- skeleton rows while loading -->
        <table class="we-table">
            <thead>
                <tr>
                    <th>#</th><th>Loan / Applicant</th>
                    <th class="num">Loan Amount</th><th class="num">Gross</th>
                    <th class="num">TDS</th><th class="num">Net Payable</th>
                    <th>Status</th><th>Date / Ref</th>
                </tr>
            </thead>
            <tbody id="we-tbody">
                <?php for($i=0;$i<8;$i++): ?>
                <tr class="skel-row" style="display:table-row;">
                    <td><div class="skel skel-cell" style="width:24px;"></div></td>
                    <td><div class="skel skel-cell" style="width:130px;margin-bottom:6px;"></div><div class="skel skel-cell" style="width:80px;height:10px;"></div></td>
                    <td class="num"><div class="skel skel-cell" style="width:60px;margin-left:auto;"></div></td>
                    <td class="num"><div class="skel skel-cell" style="width:55px;margin-left:auto;"></div></td>
                    <td class="num"><div class="skel skel-cell" style="width:40px;margin-left:auto;"></div></td>
                    <td class="num"><div class="skel skel-cell" style="width:65px;margin-left:auto;"></div></td>
                    <td><div class="skel skel-cell" style="width:70px;border-radius:20px;"></div></td>
                    <td><div class="skel skel-cell" style="width:80px;"></div></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="we-pagination" id="we-pagination" style="display:none;">
        <span class="we-page-info" id="we-page-info"></span>
        <div class="we-page-btns" id="we-page-btns"></div>
    </div>
</div>

<!-- Info note -->
<div style="margin-top:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:12px 16px; display:flex; align-items:flex-start; gap:10px; font-size:12px; color:#15803d; line-height:1.6;">
    <i data-lucide="info" style="width:15px;height:15px;flex-shrink:0;margin-top:2px;"></i>
    <div><strong>How payouts work:</strong> Admin adds commission entries for your disbursed loan files. When status changes to <strong>"Paid"</strong>, net amount (after 2% TDS) is credited. You'll see a dashboard notification too.</div>
</div>

</div><!-- /view-container -->

<script>
// ─── State ────────────────────────────────────────────────────────────────────
const BASE = '../api.php';  // not used for summary, we use same file AJAX
const SELF = 'my_earnings.php';
let currentPage   = 1;
let currentStatus = '';
let currentSearch = '';
let currentPerPage= 15;
let searchTimer   = null;
let isFetching    = false;

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fmt(n) {
    n = parseFloat(n) || 0;
    if (n >= 10000000) return '₹' + (n/10000000).toFixed(2) + ' Cr';
    if (n >= 100000)   return '₹' + (n/100000).toFixed(2) + ' L';
    return '₹' + Math.round(n).toLocaleString('en-IN');
}
function fmtDate(str) {
    if (!str) return '—';
    const d = new Date(str);
    return d.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
}
function statusClass(s) {
    const m = {Paid:'bs-paid', Pending:'bs-pending', Approved:'bs-approved', Cancelled:'bs-cancelled', Hold:'bs-hold'};
    return m[s] || 'bs-hold';
}
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

// ─── Load Summary ─────────────────────────────────────────────────────────────
async function loadSummary() {
    try {
        const res  = await fetch(`my_earnings.php?ajax=summary`);
        const json = await res.json();
        if (!json.ok) return;
        const d = json.data;

        // Hero
        document.getElementById('we-hero-content').innerHTML = `
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                <i data-lucide="wallet" style="width:16px;height:16px;color:#4ade80;"></i> My Earnings Wallet
            </div>
            <div class="we-hero-amount">${fmt(d.total_received)}</div>
            <div style="font-size:13px;color:#cbd5e1;margin-bottom:14px;">Total Amount Credited to your account</div>
            <div class="we-hero-pills">
                <span class="we-pill"><i data-lucide="clock" style="width:11px;height:11px;"></i> ${d.pending_tx||0} Pending</span>
                <span class="we-pill"><i data-lucide="check-circle" style="width:11px;height:11px;"></i> ${d.paid_tx||0} Paid</span>
                <span class="we-pill"><i data-lucide="layers" style="width:11px;height:11px;"></i> ${d.total_tx||0} Total Transactions</span>
            </div>`;

        // Metrics
        const monthName = new Date().toLocaleString('default', {month:'long', year:'numeric'});
        document.getElementById('we-metrics').innerHTML = `
            <div class="we-metric c-green">
                <div class="lbl">Total Earned (Gross)</div>
                <div class="val" style="color:#10b981;">${fmt(d.total_gross)}</div>
                <div class="sub">Before TDS deduction</div>
            </div>
            <div class="we-metric c-amber">
                <div class="lbl">Pending Payout</div>
                <div class="val" style="color:#d97706;">${fmt(d.total_pending)}</div>
                <div class="sub">Awaiting Admin release</div>
            </div>
            <div class="we-metric c-blue">
                <div class="lbl">This Month (${monthName})</div>
                <div class="val" style="color:#0284c7;">${fmt(d.this_month)}</div>
                <div class="sub">Net received this month</div>
            </div>
            <div class="we-metric c-red">
                <div class="lbl">TDS Deducted (Total)</div>
                <div class="val" style="color:#ef4444;">${fmt(d.total_tds)}</div>
                <div class="sub">Tax at source (2%)</div>
            </div>`;

        if (window.lucide) lucide.createIcons();
    } catch(e) { /* silent */ }
}

// ─── Render skeleton rows ─────────────────────────────────────────────────────
function showSkeleton(count) {
    const tbody = document.getElementById('we-tbody');
    let html = '';
    for (let i = 0; i < count; i++) {
        html += `<tr>
            <td><div class="skel skel-cell" style="width:24px;"></div></td>
            <td><div class="skel skel-cell" style="width:130px;margin-bottom:6px;"></div><div class="skel skel-cell" style="width:80px;height:10px;"></div></td>
            <td class="num"><div class="skel skel-cell" style="width:60px;margin-left:auto;"></div></td>
            <td class="num"><div class="skel skel-cell" style="width:55px;margin-left:auto;"></div></td>
            <td class="num"><div class="skel skel-cell" style="width:40px;margin-left:auto;"></div></td>
            <td class="num"><div class="skel skel-cell" style="width:65px;margin-left:auto;"></div></td>
            <td><div class="skel skel-cell" style="width:70px;border-radius:20px;"></div></td>
            <td><div class="skel skel-cell" style="width:80px;"></div></td>
        </tr>`;
    }
    tbody.innerHTML = html;
}

// ─── Load Records ─────────────────────────────────────────────────────────────
async function loadRecords(page) {
    if (isFetching) return;
    isFetching  = true;
    currentPage = page || 1;

    document.getElementById('we-results-label').textContent = 'Loading...';
    document.getElementById('we-pagination').style.display  = 'none';
    showSkeleton(currentPerPage > 25 ? 10 : Math.min(currentPerPage, 8));

    try {
        const params = new URLSearchParams({
            ajax    : 'records',
            status  : currentStatus,
            q       : currentSearch,
            page    : currentPage,
            per_page: currentPerPage
        });
        const res  = await fetch(`my_earnings.php?${params.toString()}`);
        const json = await res.json();

        if (!json.ok) throw new Error(json.msg || 'Error');

        renderTable(json);
        renderPagination(json);

        const start = (json.page - 1) * json.per_page + 1;
        const end   = Math.min(json.page * json.per_page, json.total);
        document.getElementById('we-results-label').textContent =
            json.total === 0 ? '0 records' : `${start}–${end} of ${json.total.toLocaleString()} records`;

    } catch(e) {
        document.getElementById('we-tbody').innerHTML =
            `<tr><td colspan="8" style="text-align:center;padding:40px;color:#ef4444;font-size:13px;">
                ⚠️ Failed to load data. <button onclick="loadRecords(currentPage)" style="margin-left:8px;padding:4px 10px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:12px;">Retry</button>
             </td></tr>`;
        document.getElementById('we-results-label').textContent = 'Error';
    }
    isFetching = false;
}

// ─── Render Table ─────────────────────────────────────────────────────────────
function renderTable(json) {
    const tbody = document.getElementById('we-tbody');
    const offset = (json.page - 1) * json.per_page;

    if (!json.rows.length) {
        tbody.innerHTML = `<tr><td colspan="8">
            <div class="we-empty">
                <i data-lucide="wallet" style="width:40px;height:40px;color:#cbd5e1;"></i>
                <div style="font-size:15px;font-weight:700;color:#475569;margin-bottom:6px;">No transactions found</div>
                <div style="font-size:12px;">Your earnings will appear here once Admin adds payout entries.</div>
            </div>
        </td></tr>`;
        if (window.lucide) lucide.createIcons();
        return;
    }

    tbody.innerHTML = json.rows.map((r, i) => {
        const sr  = offset + i + 1;
        const sc  = statusClass(r.status);
        const dt  = r.paid_on ? fmtDate(r.paid_on) : fmtDate(r.created_at);
        const ref = r.transaction_ref
            ? `<div style="font-size:10px;color:#10b981;font-weight:600;margin-top:2px;">Ref: ${esc(r.transaction_ref)}</div>` : '';
        const netColor = r.status === 'Paid' ? '#10b981' : '#d97706';
        const pct = r.commission_percentage ? `<div style="font-size:10px;color:#94a3b8;">@${parseFloat(r.commission_percentage).toFixed(2)}%</div>` : '';

        return `<tr>
            <td style="color:#94a3b8;font-size:12px;width:36px;">${sr}</td>
            <td>
                <div style="font-weight:700;color:#0f172a;font-size:13px;">${esc(r.customer_name||'—')}</div>
                <div style="font-size:11px;color:#64748b;">${esc(r.loan_id||'')} &bull; ${esc(r.loan_type||'—')}</div>
            </td>
            <td class="num" style="font-weight:600;color:#334155;">${fmt(r.loan_amount)}</td>
            <td class="num" style="font-weight:700;color:#0f172a;">${fmt(r.gross_payout)}${pct}</td>
            <td class="num" style="color:#ef4444;">-${fmt(r.tds_deducted)}</td>
            <td class="num" style="font-weight:800;color:${netColor};">${fmt(r.net_payable)}</td>
            <td><span class="bs ${sc}">${esc(r.status)}</span></td>
            <td style="font-size:12px;color:#475569;">${dt}${ref}</td>
        </tr>`;
    }).join('');
}

// ─── Render Pagination ────────────────────────────────────────────────────────
function renderPagination(json) {
    const pag = document.getElementById('we-pagination');
    const info = document.getElementById('we-page-info');
    const btns = document.getElementById('we-page-btns');

    if (json.total_pages <= 1) { pag.style.display = 'none'; return; }
    pag.style.display = 'flex';

    info.textContent = `Page ${json.page} of ${json.total_pages}`;

    // Smart pagination: prev, ...window..., next
    const p = json.page, tp = json.total_pages;
    let pages = new Set([1, tp]);
    for (let i = Math.max(1, p-2); i <= Math.min(tp, p+2); i++) pages.add(i);
    pages = [...pages].sort((a,b) => a-b);

    let html = `<button class="we-page-btn" onclick="loadRecords(${p-1})" ${p===1?'disabled':''}>&#8592; Prev</button>`;
    let prev = 0;
    for (const pg of pages) {
        if (pg - prev > 1) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">…</span>`;
        html += `<button class="we-page-btn ${pg===p?'active':''}" onclick="loadRecords(${pg})">${pg}</button>`;
        prev = pg;
    }
    html += `<button class="we-page-btn" onclick="loadRecords(${p+1})" ${p===tp?'disabled':''}>Next &#8594;</button>`;
    btns.innerHTML = html;
}

// ─── Filter / search handlers ─────────────────────────────────────────────────
function setFilter(status) {
    currentStatus = status;
    document.querySelectorAll('.we-filter-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.status === status);
    });
    resetAndLoad();
}

function resetAndLoad() {
    currentPage    = 1;
    currentSearch  = (document.getElementById('we-search').value || '').trim();
    currentPerPage = parseInt(document.getElementById('we-per-page').value) || 15;
    loadRecords(1);
}

function debouncedSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(resetAndLoad, 420);
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.querySelectorAll('.we-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => setFilter(btn.dataset.status));
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
    loadSummary();
    loadRecords(1);
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
