<?php
require_once 'config.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS payout_distributions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER NOT NULL,
        payee_type TEXT NOT NULL, 
        payee_user_id INTEGER,
        total_loan_amount REAL DEFAULT 0,
        commission_percentage REAL DEFAULT 0,
        gross_payout REAL DEFAULT 0,
        tds_deducted REAL DEFAULT 0,
        net_payable REAL DEFAULT 0,
        status TEXT DEFAULT 'Pending', 
        cancellation_reason TEXT,
        transaction_ref TEXT,
        paid_on DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    $db->exec($sql);
    echo "Table payout_distributions created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
