<?php
/**
 * BFS On-Premise Document Vault - Permanent Redirect to Unified CRM Settings
 * 
 * Vault management has been permanently unified into CRM Settings (settings.php?tab=vault).
 */
require_once 'config.php';
header("Location: settings.php?tab=vault");
exit;
