// User Management script.js

document.addEventListener('DOMContentLoaded', () => {
    // Animate stat values on page load
    const statElements = [
        { el: document.getElementById('totalCapacity') },
        { el: document.getElementById('activeNow') },
        { el: document.getElementById('facultyLoad') },
        { el: document.getElementById('serverLoad') }
    ];

    statElements.forEach(({ el }) => {
        if (!el) return;
        const final = el.textContent;
        const numericValue = parseFloat(final.replace(/[^0-9.]/g, ''));
        const suffix = final.replace(/[0-9.,]/g, '');
        const hasComma = final.includes(',');

        let current = 0;
        const duration = 1200;
        const step = 16;
        const increment = numericValue / (duration / step);

        el.textContent = '0' + suffix;

        const timer = setInterval(() => {
            current += increment;
            if (current >= numericValue) {
                el.textContent = final;
                clearInterval(timer);
            } else {
                let display = Math.floor(current).toString();
                if (hasComma) {
                    display = display.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                el.textContent = display + suffix;
            }
        }, step);
    });

    // Allow normal form submission for provisioning
    const provisionForm = document.getElementById('provisionForm');
    if (provisionForm) {
        provisionForm.addEventListener('submit', () => {
            const btn = document.getElementById('provisionBtn');
            btn.innerHTML = 'Provisioning...';
        });
    }

    // Form input focus animations
    const inputs = document.querySelectorAll('.form-group input, .form-group select');
    inputs.forEach((input) => {
        input.addEventListener('focus', () => {
            input.parentElement.style.transform = 'translateY(-1px)';
        });
        input.addEventListener('blur', () => {
            input.parentElement.style.transform = 'translateY(0)';
        });
    });

    // Toggle fields based on selected role
    const roleSelect = document.getElementById('roleSelect');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            const parentFields = document.getElementById('parentFields');
            const parentNameInput = document.getElementById('parentNameInput');
            const parentEmailInput = document.getElementById('parentEmailInput');
            
            const teacherFields = document.getElementById('teacherFields');
            const positionInput = document.getElementById('positionInput');
            
            if (this.value === 'student') {
                parentFields.style.display = 'grid';
                parentNameInput.required = true;
                parentEmailInput.required = true;
                
                teacherFields.style.display = 'none';
                if(positionInput) positionInput.required = false;
            } else if (this.value === 'teacher') {
                parentFields.style.display = 'none';
                parentNameInput.required = false;
                parentEmailInput.required = false;
                
                teacherFields.style.display = 'grid';
                if(positionInput) positionInput.required = true;
            } else {
                parentFields.style.display = 'none';
                parentNameInput.required = false;
                parentEmailInput.required = false;
                
                teacherFields.style.display = 'none';
                if(positionInput) positionInput.required = false;
            }
        });
    }
});
