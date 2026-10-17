const BANK_DIRECTORY = {
    "Public Sector Bank": [
        "Bank of Baroda",
        "Bank of India",
        "Bank of Maharashtra",
        "Canara Bank",
        "Central Bank of India",
        "Indian Bank",
        "Indian Overseas Bank",
        "Punjab & Sind Bank",
        "Punjab National Bank",
        "State Bank of India",
        "UCO Bank",
        "Union Bank of India"
    ],
    "Private Sector Bank": [
        "Axis Bank",
        "Bandhan Bank",
        "CSB Bank",
        "City Union Bank",
        "DCB Bank",
        "Dhanlaxmi Bank",
        "Federal Bank",
        "HDFC Bank",
        "ICICI Bank",
        "IDBI Bank",
        "IDFC First Bank",
        "IndusInd Bank",
        "Jammu & Kashmir Bank",
        "Karnataka Bank",
        "Karur Vysya Bank",
        "Kotak Mahindra Bank",
        "Nainital Bank",
        "RBL Bank",
        "South Indian Bank",
        "Tamilnad Mercantile Bank",
        "Yes Bank"
    ],
    "Small Finance Bank": [
        "AU Small Finance Bank",
        "Capital Small Finance Bank",
        "Equitas Small Finance Bank",
        "ESAF Small Finance Bank",
        "Fincare Small Finance Bank",
        "Jana Small Finance Bank",
        "North East Small Finance Bank",
        "Shivalik Small Finance Bank",
        "Suryoday Small Finance Bank",
        "Ujjivan Small Finance Bank",
        "Utkarsh Small Finance Bank"
    ],
    "Housing Finance Company (HFC)": [
        "Aadhar Housing Finance",
        "Aavas Financiers",
        "Aditya Birla Housing Finance",
        "Altum Credo Home Finance",
        "Anand Rathi Housing Finance",
        "Aptus Value Housing Finance",
        "Aviom India Housing Finance",
        "Bajaj Housing Finance",
        "Can Fin Homes",
        "Capri Global Housing Finance",
        "Cent Bank Home Finance",
        "Cholamandalam Home Finance",
        "Easy Home Finance",
        "Edelweiss Housing Finance",
        "Fasttrack Housing Finance",
        "GIC Housing Finance",
        "Godrej Housing Finance",
        "Hero Housing Finance",
        "Hinduja Housing Finance",
        "Home First Finance Company",
        "ICICI Home Finance",
        "India Home Loan",
        "India Shelter Finance",
        "Indiabulls Housing Finance",
        "Khush Housing Finance",
        "L&T Housing Finance",
        "LIC Housing Finance",
        "Mahindra Rural Housing Finance",
        "Manappuram Home Finance",
        "Muthoot Homefin",
        "Navin Housing Finance",
        "Omaxe Housing Finance",
        "Piramal Capital & Housing Finance",
        "PNB Housing Finance",
        "Poonawalla Housing Finance",
        "Reliance Home Finance",
        "Repco Home Finance",
        "Roha Housing Finance",
        "Shriram Housing Finance",
        "Shubham Housing Development Finance",
        "SRG Housing Finance",
        "Star Housing Finance",
        "Sundaram Home Finance",
        "Tata Capital Housing Finance",
        "Vastu Housing Finance",
        "Wonder Home Finance"
    ],
    "NBFC / Others": [
        "Bajaj Finance",
        "Muthoot Finance",
        "Manappuram Finance",
        "Cholamandalam Investment",
        "Mahindra & Mahindra Financial",
        "Shriram Finance",
        "Tata Capital",
        "Other (Manual Entry)"
    ]
};

function populateBankDropdowns(typeSelectId, nameSelectId, preselectedType = null, preselectedName = null) {
    const typeSelect = document.getElementById(typeSelectId);
    const nameSelect = document.getElementById(nameSelectId);
    
    if (!typeSelect || !nameSelect) return;
    
    // Clear and populate Bank Types
    typeSelect.innerHTML = '<option value="" disabled selected>Select Bank Type</option>';
    for (const type in BANK_DIRECTORY) {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        if (type === preselectedType) option.selected = true;
        typeSelect.appendChild(option);
    }

    // Function to update Bank Names based on type
    const updateNames = (selectedType, preName = null) => {
        nameSelect.innerHTML = '<option value="" disabled selected>Select Bank Name</option>';
        if (selectedType && BANK_DIRECTORY[selectedType]) {
            let nameMatched = false;
            BANK_DIRECTORY[selectedType].forEach(bank => {
                const option = document.createElement('option');
                option.value = bank;
                option.textContent = bank;
                if (preName && bank.trim().toLowerCase() === preName.trim().toLowerCase()) {
                    option.selected = true;
                    nameMatched = true;
                }
                nameSelect.appendChild(option);
            });

            // If preselected name is not in directory, add it dynamically
            if (preName && !nameMatched && preName !== 'OTHER_CUSTOM' && preName !== 'Other (Manual Entry)') {
                const customOpt = document.createElement('option');
                customOpt.value = preName;
                customOpt.textContent = preName;
                customOpt.selected = true;
                nameSelect.appendChild(customOpt);
            }

            // Always add + Add Custom / New Bank option
            const addOpt = document.createElement('option');
            addOpt.value = 'OTHER_CUSTOM';
            addOpt.textContent = '➕ + Add New Bank (Enter Manually)';
            nameSelect.appendChild(addOpt);
        }
    };

    // Listen to changes
    typeSelect.addEventListener('change', (e) => {
        updateNames(e.target.value);
        if (typeof handleBankNameChange === 'function') {
            handleBankNameChange(nameSelect);
        }
    });

    // Initial setup if preselected
    if (preselectedType) {
        updateNames(preselectedType, preselectedName);
    }
}
