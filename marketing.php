<?php
require_once 'config.php';
require_auth();
require_role(['Admin', 'Staff']);

require_once 'libs/ListmonkClient.php';
$client = new ListmonkClient();

$stats = $client->getStats();
$isHealthy = $stats['healthy'];

$page_title = "Email Marketing & Campaigns Hub";
require_once 'header.php';
?>

<div class="main-content-inner" style="padding: 24px 30px;">
    <!-- Top Header Banner -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                <h1 style="font-size: 24px; font-weight: 800; color: #ffffff; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-bullhorn" style="color: #10b981;"></i>
                    Email Marketing & Campaigns Hub
                </h1>
                <?php if ($isHealthy): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 20px;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981; animation: pulse 2s infinite;"></span>
                    Listmonk Engine Active (Port 9000)
                </span>
                <?php else: ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 20px;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #ef4444;"></span>
                    Listmonk Offline
                </span>
                <?php endif; ?>
            </div>
            <p style="margin: 0; color: #94a3b8; font-size: 13.5px;">
                High-volume, multi-threaded campaign dispatcher & loan-wise audience segmenter for BFS Financial Services
            </p>
        </div>

        <div style="display: flex; gap: 10px;">
            <button onclick="triggerBulkSync()" id="btn-sync-all" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-weight: 700; border: none; padding: 10px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); transition: all 0.2s;">
                <i class="fa-solid fa-arrows-rotate" id="sync-icon"></i>
                <span>Sync CRM Contacts Now</span>
            </button>
            <a href="http://localhost:9000" target="_blank" class="btn" style="background: rgba(255, 255, 255, 0.08); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.15); font-weight: 600; padding: 10px 16px; border-radius: 10px; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                <span>Open Studio</span>
            </a>
        </div>
    </div>

    <!-- Live Metrics Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Total Subscribers</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; color: #10b981;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">
                <?php echo number_format($stats['total_subscribers']); ?>
            </div>
            <div style="font-size: 12px; color: #34d399; margin-top: 4px; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> Clean Verified Audience
            </div>
        </div>

        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Smart Loan Lists</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">
                <?php echo number_format($stats['total_lists']); ?>
            </div>
            <div style="font-size: 12px; color: #60a5fa; margin-top: 4px; font-weight: 600;">
                Home, LAP, Personal, VIP & Partners
            </div>
        </div>

        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Total Campaigns</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">
                <?php echo number_format($stats['total_campaigns']); ?>
            </div>
            <div style="font-size: 12px; color: #fbbf24; margin-top: 4px; font-weight: 600;">
                Festive & Promotional Blasts
            </div>
        </div>

        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <span style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Delivery Protocol</span>
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(168, 85, 247, 0.15); display: flex; align-items: center; justify-content: center; color: #a855f7;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
            <div style="font-size: 28px; font-weight: 800; color: #ffffff; font-family: 'Outfit', sans-serif;">
                Go Engine
            </div>
            <div style="font-size: 12px; color: #c084fc; margin-top: 4px; font-weight: 600;">
                Async Queued + DND Protection
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 8px; border-bottom: 1px solid #1e293b; margin-bottom: 24px;">
        <button onclick="switchTab('audiences')" id="tab-btn-audiences" class="tab-btn active-tab">
            <i class="fa-solid fa-users-viewfinder"></i> Smart Audiences & Sync
        </button>
        <button onclick="switchTab('campaigns')" id="tab-btn-campaigns" class="tab-btn">
            <i class="fa-solid fa-paper-plane"></i> Quick Campaign Launcher
        </button>
        <button onclick="switchTab('history')" id="tab-btn-history" class="tab-btn">
            <i class="fa-solid fa-chart-pie"></i> Campaign History & Reports
        </button>
        <button onclick="switchTab('studio')" id="tab-btn-studio" class="tab-btn">
            <i class="fa-solid fa-palette"></i> Embedded Listmonk Studio
        </button>
    </div>

    <!-- TAB 1: Smart Audiences & Sync -->
    <div id="tab-content-audiences" class="tab-content" style="display: block;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <?php
            $list_meta = [
                3 => ['icon' => 'fa-house-chimney', 'color' => '#10b981', 'desc' => 'Prospective & in-process Home Loan borrowers'],
                4 => ['icon' => 'fa-building', 'color' => '#3b82f6', 'desc' => 'Commercial & Residential Loan Against Property'],
                5 => ['icon' => 'fa-briefcase', 'color' => '#f59e0b', 'desc' => 'SME, Business & Personal Loan applicants'],
                6 => ['icon' => 'fa-star', 'color' => '#a855f7', 'desc' => 'Sanctioned borrowers eligible for Top-Up offers'],
                7 => ['icon' => 'fa-handshake', 'color' => '#ec4899', 'desc' => 'Active DSAs, Channel Partners & Connectors']
            ];

            foreach ($stats['lists'] as $l):
                $meta = $list_meta[$l['id']] ?? ['icon' => 'fa-envelope', 'color' => '#64748b', 'desc' => 'Standard mailing list'];
            ?>
            <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -10px; right: -10px; width: 70px; height: 70px; background: <?php echo $meta['color']; ?>10; border-radius: 50%;"></div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: <?php echo $meta['color']; ?>20; display: flex; align-items: center; justify-content: center; color: <?php echo $meta['color']; ?>; font-size: 18px;">
                        <i class="fa-solid <?php echo $meta['icon']; ?>"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($l['name']); ?></h3>
                        <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">List ID: <?php echo $l['id']; ?> • <?php echo ucfirst($l['type']); ?></span>
                    </div>
                </div>
                <p style="font-size: 12.5px; color: #94a3b8; margin: 0 0 16px 0; line-height: 1.4;">
                    <?php echo $meta['desc']; ?>
                </p>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #1e293b; padding-top: 12px;">
                    <span style="font-size: 12px; color: #64748b;">Subscribers</span>
                    <span style="font-size: 16px; font-weight: 800; color: #ffffff; font-family: monospace;">
                        <?php echo number_format($l['subscriber_count']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sync Activity & Diagnostic Box -->
        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 22px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #ffffff; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #3b82f6;"></i>
                    Recent CRM Synchronization Activity
                </h3>
                <span id="sync-status-badge" style="font-size: 12px; color: #94a3b8;">Auto-sync enabled on Lead Creation</span>
            </div>
            
            <div id="sync-progress-box" style="display: none; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; animation: fadeIn 0.3s ease;">
                <div style="display: flex; align-items: center; gap: 10px; color: #34d399; font-weight: 700; font-size: 13.5px;">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                    <span id="sync-progress-text">Synchronizing CRM contacts with Listmonk smart lists...</span>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid #1e293b; color: #64748b; font-size: 11.5px; text-transform: uppercase;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">Entity</th>
                            <th style="padding: 10px;">Recipient Email</th>
                            <th style="padding: 10px;">List IDs</th>
                            <th style="padding: 10px;">Sync Status</th>
                            <th style="padding: 10px;">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="sync-logs-body">
                        <?php
                        $recent_syncs = $db->query("SELECT * FROM listmonk_sync_logs ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($recent_syncs)):
                            foreach ($recent_syncs as $s):
                        ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); color: #cbd5e1;">
                            <td style="padding: 10px; font-family: monospace;">#<?php echo $s['id']; ?></td>
                            <td style="padding: 10px;">
                                <span style="text-transform: capitalize; font-weight: 600; color: #ffffff;">
                                    <?php echo htmlspecialchars($s['entity_type']); ?> #<?php echo $s['entity_id']; ?>
                                </span>
                            </td>
                            <td style="padding: 10px; color: #38bdf8;"><?php echo htmlspecialchars($s['email']); ?></td>
                            <td style="padding: 10px; font-family: monospace; color: #94a3b8;"><?php echo htmlspecialchars($s['lists_synced']); ?></td>
                            <td style="padding: 10px;">
                                <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                                    Synced
                                </span>
                            </td>
                            <td style="padding: 10px; color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($s['synced_at']); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" style="padding: 24px; text-align: center; color: #64748b;">
                                Click "Sync CRM Contacts Now" above to initiate your first full audience sync.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: Quick Campaign Launcher -->
    <div id="tab-content-campaigns" class="tab-content" style="display: none;">
        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 28px; max-width: 900px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div style="margin-bottom: 20px;">
                <h2 style="font-size: 18px; font-weight: 800; color: #ffffff; margin: 0 0 4px 0;">Create & Dispatch Campaign</h2>
                <p style="font-size: 13px; color: #94a3b8; margin: 0;">Compose an offer or announcement and broadcast it to your segmented loan audiences</p>
            </div>

            <form id="campaign-form" onsubmit="handleCreateCampaign(event)">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: #ffffff; margin-bottom: 6px;">
                            Campaign Internal Title *
                        </label>
                        <input type="text" name="title" id="campaign_title" placeholder="e.g. Diwali Home Loan Interest Cut 2026" required class="crm-input">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; color: #ffffff; margin-bottom: 6px;">
                            Email Subject Line *
                        </label>
                        <input type="text" name="subject" id="campaign_subject" placeholder="e.g. 🌟 Exclusive 8.35% Home Loan Rate Announced - BFS Financial" required class="crm-input">
                    </div>
                </div>

                <!-- Target Audience Selection -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #ffffff; margin-bottom: 8px;">
                        Select Target Audience Lists *
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <?php foreach ($stats['lists'] as $l): ?>
                        <label style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.03); border: 1px solid #1e293b; padding: 10px 14px; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                            <input type="checkbox" name="lists[]" value="<?php echo $l['id']; ?>" <?php echo in_array($l['id'], [3, 4, 5]) ? 'checked' : ''; ?> style="accent-color: #10b981; width: 16px; height: 16px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($l['name']); ?></div>
                                <div style="font-size: 11px; color: #94a3b8;"><?php echo $l['subscriber_count']; ?> recipients</div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Template Preset Selector -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label style="font-size: 12.5px; font-weight: 600; color: #ffffff;">
                            Quick Templates / Presets
                        </label>
                        <span style="font-size: 11.5px; color: #34d399;">Click any template to auto-populate content</span>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button type="button" onclick="loadTemplate('homeloan')" class="preset-btn">🏠 Home Loan Festive Offer</button>
                        <button type="button" onclick="loadTemplate('lap')" class="preset-btn">🏢 LAP High-Value Liquidity</button>
                        <button type="button" onclick="loadTemplate('partner')" class="preset-btn">🤝 Partner Payout Hike</button>
                        <button type="button" onclick="loadTemplate('topup')" class="preset-btn">⭐ Pre-Approved Top-Up</button>
                    </div>
                </div>

                <!-- Email Body Content -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; color: #ffffff; margin-bottom: 6px;">
                        HTML Email Content * (Supports {{ .Subscriber.Name }} variable)
                    </label>
                    <textarea name="body" id="campaign_body" rows="10" required class="crm-input" style="font-family: monospace; font-size: 13px; line-height: 1.5;"></textarea>
                </div>

                <!-- Form Action Buttons -->
                <div style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="submitCampaignForm(false)" id="btn-save-draft" class="btn" style="background: rgba(255,255,255,0.08); color: #ffffff; border: 1px solid rgba(255,255,255,0.2); font-weight: 600; padding: 12px 22px; border-radius: 10px; cursor: pointer;">
                        Save as Draft in Listmonk
                    </button>
                    <button type="button" onclick="submitCampaignForm(true)" id="btn-send-now" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-weight: 700; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">
                        <i class="fa-solid fa-rocket"></i>
                        <span>Launch & Send Immediately</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 3: Campaign History & Analytics -->
    <div id="tab-content-history" class="tab-content" style="display: none;">
        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 22px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #ffffff;">Dispatched & Draft Campaigns</h3>
                <button onclick="refreshCampaignsList()" class="btn" style="background: none; border: 1px solid #334155; color: #94a3b8; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer;">
                    <i class="fa-solid fa-arrows-rotate"></i> Refresh
                </button>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid #1e293b; color: #64748b; font-size: 11.5px; text-transform: uppercase;">
                            <th style="padding: 12px 10px;">ID</th>
                            <th style="padding: 12px 10px;">Campaign Title</th>
                            <th style="padding: 12px 10px;">Subject</th>
                            <th style="padding: 12px 10px;">Target Lists</th>
                            <th style="padding: 12px 10px;">Status</th>
                            <th style="padding: 12px 10px;">Recipients / Sent</th>
                            <th style="padding: 12px 10px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stats['campaigns'])): foreach ($stats['campaigns'] as $c): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); color: #cbd5e1;">
                            <td style="padding: 12px 10px; font-family: monospace;">#<?php echo $c['id']; ?></td>
                            <td style="padding: 12px 10px; font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td style="padding: 12px 10px; color: #94a3b8; font-size: 12.5px;"><?php echo htmlspecialchars($c['subject']); ?></td>
                            <td style="padding: 12px 10px;">
                                <?php
                                $c_lists = array_column($c['lists'] ?? [], 'name');
                                echo htmlspecialchars(implode(', ', $c_lists));
                                ?>
                            </td>
                            <td style="padding: 12px 10px;">
                                <?php
                                $status = strtolower($c['status'] ?? 'draft');
                                $badge_style = 'background: rgba(100, 116, 139, 0.2); color: #94a3b8;';
                                if ($status === 'finished') $badge_style = 'background: rgba(16, 185, 129, 0.2); color: #34d399;';
                                elseif ($status === 'running') $badge_style = 'background: rgba(59, 130, 246, 0.2); color: #60a5fa;';
                                ?>
                                <span style="<?php echo $badge_style; ?> padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    <?php echo htmlspecialchars($c['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 12px 10px; font-family: monospace; font-weight: 700;">
                                <?php echo number_format($c['to_send'] ?? 0); ?>
                            </td>
                            <td style="padding: 12px 10px; text-align: right;">
                                <?php if ($c['status'] === 'draft'): ?>
                                <button onclick="triggerSendCampaign(<?php echo $c['id']; ?>)" class="btn" style="background: #10b981; color: #ffffff; border: none; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                                    Send Now
                                </button>
                                <?php else: ?>
                                <a href="http://localhost:9000/admin/campaigns/<?php echo $c['id']; ?>" target="_blank" style="color: #38bdf8; text-decoration: none; font-size: 12px; font-weight: 600;">
                                    View Stats →
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7" style="padding: 24px; text-align: center; color: #64748b;">
                                No campaigns found. Create your first campaign in the "Quick Campaign Launcher" tab.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: Embedded Listmonk Studio -->
    <div id="tab-content-studio" class="tab-content" style="display: none;">
        <div style="background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; overflow: hidden;">
            <div style="background: #1e293b; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12.5px; font-weight: 600; color: #cbd5e1; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-lock" style="color: #10b981;"></i>
                    Listmonk Studio (Port 9000 Internal Microservice)
                </div>
                <a href="http://localhost:9000" target="_blank" style="color: #38bdf8; font-size: 12px; text-decoration: none; font-weight: 600;">
                    Open in Full Window <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 10px;"></i>
                </a>
            </div>
            <iframe src="http://localhost:9000" style="width: 100%; height: 750px; border: none; background: #ffffff;"></iframe>
        </div>
    </div>
</div>

<style>
.tab-btn {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 13.5px;
    font-weight: 600;
    padding: 10px 16px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.tab-btn:hover {
    color: #ffffff;
}
.tab-btn.active-tab {
    color: #10b981;
    border-bottom: 2px solid #10b981;
}
.crm-input {
    width: 100%;
    background: #090d16;
    border: 1px solid #1e293b;
    border-radius: 8px;
    padding: 10px 14px;
    color: #ffffff;
    font-size: 13.5px;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.crm-input:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}
.preset-btn {
    background: rgba(255,255,255,0.05);
    border: 1px solid #1e293b;
    color: #cbd5e1;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}
.preset-btn:hover {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    color: #34d399;
}
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}
</style>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active-tab'));
    
    document.getElementById('tab-content-' + tabId).style.display = 'block';
    document.getElementById('tab-btn-' + tabId).classList.add('active-tab');
}

// 1-Click Sync Engine
function triggerBulkSync() {
    const btn = document.getElementById('btn-sync-all');
    const icon = document.getElementById('sync-icon');
    const progressBox = document.getElementById('sync-progress-box');
    const progressText = document.getElementById('sync-progress-text');
    
    btn.disabled = true;
    icon.classList.add('fa-spin');
    progressBox.style.display = 'block';
    progressText.innerText = 'Connecting to CRM database & Listmonk Go Backend...';

    fetch('api.php?api=listmonk_sync_all', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('fa-spin');
        if (data.success) {
            progressText.innerHTML = `✅ <strong>Sync Success!</strong> ${data.total_synced} contacts synced (${data.synced_leads} Leads, ${data.synced_applicants} Applicants, ${data.synced_referrals} Partners).`;
            setTimeout(() => location.reload(), 2000);
        } else {
            progressText.innerText = '❌ Error: ' + (data.error || 'Failed to sync');
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('fa-spin');
        progressText.innerText = '❌ Sync network connection error. Check if Listmonk is running.';
    });
}

// Template Presets
const TEMPLATES = {
    homeloan: {
        title: 'Diwali Special 8.35% Home Loan Scheme',
        subject: '🏡 Great News {{ .Subscriber.Name }}! Special 8.35% Home Loan Interest Rate Announced',
        body: `<div style="max-width:600px; margin:0 auto; font-family:Arial,sans-serif; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
    <div style="background:#0f172a; padding:24px; text-align:center; color:#ffffff;">
        <h2 style="margin:0; color:#10b981;">BFS Financial Services</h2>
        <p style="margin:4px 0 0 0; font-size:12px; color:#94a3b8;">Home Loan Advisory Cell</p>
    </div>
    <div style="padding:28px; color:#334155; line-height:1.6;">
        <p>Dear <strong>{{ .Subscriber.Name }}</strong>,</p>
        <p>We are delighted to share that leading nationalized and private banks have slashed home loan interest rates to an all-time low starting from <strong>8.35% p.a.*</strong></p>
        <div style="background:#f0fdf4; border:1px solid #86efac; border-radius:10px; padding:18px; margin:20px 0;">
            <div style="font-size:18px; font-weight:800; color:#166534;">Special Festival Benefits:</div>
            <ul style="margin:10px 0 0 0; padding-left:20px; color:#15803d; font-size:14px;">
                <li>Zero Processing Fee* on Balance Transfers</li>
                <li>Instant In-Principle Sanctions within 48 Hours</li>
                <li>Flexible Tenures up to 30 Years</li>
            </ul>
        </div>
        <p>Our dedicated Relationship Manager is ready to assist you. Simply reply to this email or contact our desk.</p>
        <p style="margin-top:24px;">Warm Regards,<br><strong>BFS Circle Loans Team</strong></p>
    </div>
</div>`
    },
    lap: {
        title: 'High-Value Loan Against Property (LAP) Liquidity Offer',
        subject: '🏢 Unlock Up to ₹10 Crore with BFS Loan Against Property (LAP)',
        body: `<div style="max-width:600px; margin:0 auto; font-family:Arial,sans-serif; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
    <div style="background:#0f172a; padding:24px; text-align:center; color:#ffffff;">
        <h2 style="margin:0; color:#3b82f6;">BFS Financial Services</h2>
        <p style="margin:4px 0 0 0; font-size:12px; color:#94a3b8;">Commercial & Residential LAP Desk</p>
    </div>
    <div style="padding:28px; color:#334155; line-height:1.6;">
        <p>Hello <strong>{{ .Subscriber.Name }}</strong>,</p>
        <p>Unlock the hidden value of your residential, commercial, or industrial property with competitive LAP financing starting at <strong>9.15% ROI</strong>.</p>
        <p>Ideal for business expansion, debt consolidation, or high-value capital expenditure.</p>
        <p>Warm Regards,<br><strong>BFS Commercial Advisory</strong></p>
    </div>
</div>`
    },
    partner: {
        title: 'Channel Partner Payout & Commission Hike Notice',
        subject: '🤝 Exciting News for BFS Partners: Up to 1.25% Payout on Disbursed Files!',
        body: `<div style="max-width:600px; margin:0 auto; font-family:Arial,sans-serif; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
    <div style="background:#0f172a; padding:24px; text-align:center; color:#ffffff;">
        <h2 style="margin:0; color:#ec4899;">BFS Circle Partner Network</h2>
        <p style="margin:4px 0 0 0; font-size:12px; color:#94a3b8;">DSA & Partner Relations</p>
    </div>
    <div style="padding:28px; color:#334155; line-height:1.6;">
        <p>Dear Valued Partner <strong>{{ .Subscriber.Name }}</strong>,</p>
        <p>We are excited to announce our enhanced payout structure for this festive quarter. Earn up to <strong>1.25% instant payout</strong> on all eligible loan files logged through BFS Circle.</p>
        <p>Log in to your Partner Portal to track your live pipeline and payouts.</p>
        <p>Best Regards,<br><strong>BFS Partner Growth Desk</strong></p>
    </div>
</div>`
    },
    topup: {
        title: 'Pre-Approved Top-Up Loan Scheme for Disbursed Clients',
        subject: '⭐ Pre-Approved Offer: Instant ₹10 Lakh Top-Up on Your Existing Loan',
        body: `<div style="max-width:600px; margin:0 auto; font-family:Arial,sans-serif; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
    <div style="background:#0f172a; padding:24px; text-align:center; color:#ffffff;">
        <h2 style="margin:0; color:#a855f7;">BFS VIP Borrowers Club</h2>
        <p style="margin:4px 0 0 0; font-size:12px; color:#94a3b8;">Pre-Approved Capital Scheme</p>
    </div>
    <div style="padding:28px; color:#334155; line-height:1.6;">
        <p>Dear <strong>{{ .Subscriber.Name }}</strong>,</p>
        <p>As a valued borrower with an excellent repayment track record, you have been selected for an exclusive <strong>Pre-Approved Top-Up Loan</strong> with zero fresh property mortgage charges.</p>
        <p>Funds can be credited directly into your account in 24 hours.</p>
        <p>Warm Regards,<br><strong>BFS Circle VIP Desk</strong></p>
    </div>
</div>`
    }
};

function loadTemplate(key) {
    const t = TEMPLATES[key];
    if (!t) return;
    document.getElementById('campaign_title').value = t.title;
    document.getElementById('campaign_subject').value = t.subject;
    document.getElementById('campaign_body').value = t.body;
}

function submitCampaignForm(sendNow) {
    const form = document.getElementById('campaign-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const title = document.getElementById('campaign_title').value.trim();
    const subject = document.getElementById('campaign_subject').value.trim();
    const body = document.getElementById('campaign_body').value.trim();
    
    const checkedLists = Array.from(document.querySelectorAll('input[name="lists[]"]:checked')).map(el => el.value);
    if (checkedLists.length === 0) {
        alert('Please select at least one target audience list.');
        return;
    }

    if (sendNow && !confirm(`Are you sure you want to DISPATCH this campaign immediately to all selected lists?`)) {
        return;
    }

    const btn = sendNow ? document.getElementById('btn-send-now') : document.getElementById('btn-save-draft');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...`;

    const fd = new FormData();
    fd.append('title', title);
    fd.append('subject', subject);
    checkedLists.forEach(id => fd.append('lists[]', id));
    fd.append('body', body);
    if (sendNow) fd.append('send_now', '1');

    fetch('api.php?api=listmonk_create_campaign', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data.success) {
            alert(data.message);
            switchTab('history');
            setTimeout(() => location.reload(), 1000);
        } else {
            alert('Failed: ' + (data.error || 'Server error'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('Network error connecting to Listmonk.');
    });
}

function triggerSendCampaign(id) {
    if (!confirm('Dispatch this draft campaign now?')) return;
    const fd = new FormData();
    fd.append('campaign_id', id);
    fd.append('status', 'running');

    fetch('api.php?api=listmonk_update_campaign_status', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Campaign dispatched!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
