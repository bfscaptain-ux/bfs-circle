<?php
require_once 'config.php';
$page_title = 'Financial Calculators';
$page_subtitle = 'Select an advanced financial tool to proceed';
$hide_main_header = true;
require_once 'header.php';
?>

<style>
    .calc-cat-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--primary);
        margin: 30px 0 20px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .calc-search-wrapper {
        margin: 0 0 32px 0;
        display: flex;
        align-items: center;
    }
    
    .calc-search-input {
        width: 100%;
        position: relative;
    }
    
    .calc-search-input input {
        width: 100%;
        padding: 14px 20px 14px 48px;
        border-radius: 12px;
        border: 1px solid var(--border);
        font-size: 15px;
        font-weight: 500;
        color: var(--text-primary);
        background: #fff;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
        transition: all 0.3s ease;
        outline: none;
    }
    
    .calc-search-input input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.08);
    }
    
    .calc-search-input .lucide-search, .calc-search-input svg {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        width: 20px;
        height: 20px;
        pointer-events: none;
    }
    
    .calc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
        width: 100%;
    }
    
    .calc-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid var(--border);
        text-decoration: none;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    
    .calc-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }
    
    .calc-screen {
        background: linear-gradient(135deg, #1e293b, #0f172a);
        padding: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        border-bottom: 1px solid #334155;
    }
    
    .calc-screen::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background-image: radial-gradient(circle at top right, rgba(255,255,255,0.06) 0%, transparent 60%);
        pointer-events: none;
    }
    
    .calc-icon-wrapper {
        width: 48px;
        height: 48px;
        background: rgba(255,255,255,0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.15);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .calc-screen-value {
        font-family: 'Outfit', sans-serif;
        font-size: 28px;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: 1px;
    }
    
    .calc-body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        background-color: #ffffff;
    }
    
    .calc-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 8px;
        font-family: 'Outfit', sans-serif;
    }
    
    .calc-desc {
        font-size: 13.5px;
        color: var(--text-muted);
        line-height: 1.5;
        flex-grow: 1;
        margin-bottom: 24px;
    }
    
    .calc-btn {
        background: var(--bg-main);
        color: var(--primary);
        padding: 12px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s;
        border: 1px solid var(--border);
    }
    
    .calc-card:hover .calc-btn {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
    }
</style>

<div class="view-container">

    <!-- Search Bar -->
    <div class="calc-search-wrapper">
        <div class="calc-search-input">
            <i data-lucide="search"></i>
            <input type="text" id="calcSearch" placeholder="Search for a calculator (e.g. EMI, Loan, Eligibility)..." onkeyup="filterCalculators()">
        </div>
    </div>

    <!-- Category 1 -->
    <div class="calc-category-group">
        <h3 class="calc-cat-title"><i data-lucide="user-check" style="color:var(--primary); width:24px; height:24px;"></i> Eligibility & Affordability</h3>
        <div class="calc-grid">
            <a href="calc_eligibility.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="shield-check" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Loan Eligibility</div>
                    <div class="calc-desc">Calculate maximum loan amount based on income, existing obligations, and desired tenure.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_foir.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="pie-chart" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">%</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">FOIR / DTI Checker</div>
                    <div class="calc-desc">Check Fixed Obligation to Income Ratio limits to determine underwriting safety.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_maxtenure.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="clock" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">YRS</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Max Tenure & Age</div>
                    <div class="calc-desc">Determine maximum allowable loan tenure based on current age and retirement parameters.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Category 2 -->
    <div class="calc-category-group">
        <h3 class="calc-cat-title"><i data-lucide="calculator" style="color:var(--primary); width:24px; height:24px;"></i> Repayment & Amortization</h3>
        <div class="calc-grid">
            <a href="calc_smartemi.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="bar-chart-2" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Smart EMI</div>
                    <div class="calc-desc">Standard EMI calculator featuring detailed Principal vs Interest breakdown charts.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_stepemi.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="trending-up" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Step-Up / Step-Down EMI</div>
                    <div class="calc-desc">Calculate graded EMI structures for growing incomes or nearing retirement scenarios.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_prepay.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="piggy-bank" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Part-Prepayment</div>
                    <div class="calc-desc">Analyze interest savings and tenure reduction by making a lump-sum part payment.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Category 3 -->
    <div class="calc-category-group">
        <h3 class="calc-cat-title"><i data-lucide="building" style="color:var(--primary); width:24px; height:24px;"></i> Property & Construction</h3>
        <div class="calc-grid">
            <a href="calc_tranche.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="layers" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Stage-wise Tranche</div>
                    <div class="calc-desc">Calculate loan release amounts based on site construction progress percentages.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_preemi.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="home" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Pre-EMI vs Full-EMI</div>
                    <div class="calc-desc">Calculate interest-only payments for under-construction properties during disbursement.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_ltv.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="percent" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">%</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">LTV & Margin</div>
                    <div class="calc-desc">Determine maximum allowable loan based on the property Loan-To-Value ratio.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Category 4 -->
    <div class="calc-category-group">
        <h3 class="calc-cat-title"><i data-lucide="award" style="color:var(--primary); width:24px; height:24px;"></i> Special Products</h3>
        <div class="calc-grid">
            <a href="calc_gold.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="sun" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Gold Loan Value</div>
                    <div class="calc-desc">Calculate maximum loan limit against gold weight and purity based on current rates.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_business.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="briefcase" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Working Capital Limit</div>
                    <div class="calc-desc">Estimate Cash Credit or Overdraft limits based on business turnover.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_bt.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="refresh-cw" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">BT Savings</div>
                    <div class="calc-desc">Calculate exact savings when transferring a loan from another bank at a lower ROI.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
        </div>
    </div>

    <!-- Category 5 -->
    <div class="calc-category-group">
        <h3 class="calc-cat-title"><i data-lucide="alert-triangle" style="color:var(--primary); width:24px; height:24px;"></i> Recovery & Operations</h3>
        <div class="calc-grid">
            <a href="calc_penalty.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="alert-circle" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">Late Payment Penalty</div>
                    <div class="calc-desc">Calculate bounce charges and penal interest on overdue EMI payments.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_npa.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="shield-alert" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">NPA Settlement</div>
                    <div class="calc-desc">Restructuring tool to calculate revised EMIs or waiver amounts for overdue accounts.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
            <a href="calc_dsa.php" class="calc-card">
                <div class="calc-screen">
                    <div class="calc-icon-wrapper"><i data-lucide="users" style="width:28px; height:28px;"></i></div>
                    <div class="calc-screen-value">0.00</div>
                </div>
                <div class="calc-body">
                    <div class="calc-title">DSA Commission</div>
                    <div class="calc-desc">Calculate Agent/DSA gross payout, TDS deduction, and net payable amount.</div>
                    <div class="calc-btn">Open Calculator <i data-lucide="arrow-right" style="width:16px;"></i></div>
                </div>
            </a>
        </div>
    </div>

</div>

<script>
function filterCalculators() {
    let input = document.getElementById('calcSearch').value.toLowerCase();
    
    document.querySelectorAll('.calc-category-group').forEach(group => {
        let cards = group.querySelectorAll('.calc-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            let title = card.querySelector('.calc-title').innerText.toLowerCase();
            let desc = card.querySelector('.calc-desc').innerText.toLowerCase();
            
            if(title.includes(input) || desc.includes(input)) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        if (visibleCount === 0) {
            group.style.display = 'none';
        } else {
            group.style.display = 'block';
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
