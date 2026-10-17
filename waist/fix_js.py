js = """
<script>
    function populateBankDropdown() {
        const select = document.getElementById('bank_name');
        if (!select) return;
        
        let allBanks = [];
        if (typeof BANK_DIRECTORY !== 'undefined') {
            for (const type in BANK_DIRECTORY) {
                allBanks = allBanks.concat(BANK_DIRECTORY[type]);
            }
            
            // Remove duplicates and sort
            allBanks = [...new Set(allBanks)].sort((a,b) => a.localeCompare(b));
            
            allBanks.forEach(bank => {
                const opt = document.createElement('option');
                opt.value = bank;
                opt.textContent = bank;
                select.appendChild(opt);
            });
            
            // Auto-select existing if present (from data-existing)
            const existingBank = select.getAttribute('data-existing');
            if (existingBank) select.value = existingBank;
        }
    }
    
    function initValidationAndCapitalize() {
        const textFields = document.querySelectorAll('input[type="text"]');
        textFields.forEach(f => {
            f.addEventListener('input', function() {
                // Ignore specific fields like PAN, IFSC that need full uppercase
                if (this.id === 'pan_number' || this.id === 'ifsc_code' || this.id === 'gst_no') {
                    this.value = this.value.toUpperCase();
                } else if (this.id !== 'account_number' && this.id !== 'aadhar_number' && this.id !== 'mobile') {
                    // Capitalize first letter of every word
                    let words = this.value.split(' ');
                    for (let i = 0; i < words.length; i++) {
                        if (words[i].length > 0) {
                            words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
                        }
                    }
                    this.value = words.join(' ');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        populateBankDropdown();
        initValidationAndCapitalize();
    });
</script>
"""

def add_js(filename):
    with open(filename, "r", encoding="utf-8") as f:
        html = f.read()
    
    if "populateBankDropdown" not in html:
        html = html.replace("<?php require_once 'footer.php'; ?>", js + "\n<?php require_once 'footer.php'; ?>")
        with open(filename, "w", encoding="utf-8") as f:
            f.write(html)

add_js("add_referral.php")
add_js("edit_referral.php")
