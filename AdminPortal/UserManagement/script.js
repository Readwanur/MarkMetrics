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
            
            const idLabel = document.getElementById('idInputLabel');
            if (this.value === 'student') {
                if (idLabel) idLabel.textContent = 'ID';
                parentFields.style.display = 'grid';
                parentNameInput.required = true;
                parentEmailInput.required = true;
                
                teacherFields.style.display = 'none';
                if(positionInput) positionInput.required = false;
            } else if (this.value === 'teacher') {
                if (idLabel) idLabel.textContent = 'Initial';
                parentFields.style.display = 'none';
                parentNameInput.required = false;
                parentEmailInput.required = false;
                
                teacherFields.style.display = 'grid';
                if(positionInput) positionInput.required = true;
            } else {
                if (idLabel) idLabel.textContent = 'Initial/ID';
                parentFields.style.display = 'none';
                parentNameInput.required = false;
                parentEmailInput.required = false;
                
                teacherFields.style.display = 'none';
                if(positionInput) positionInput.required = false;
            }
        });
    }

    // Auto-generate ID/Initial
    const nameInput = document.getElementById('nameInput');
    const idInput = document.getElementById('idInput');
    
    function updateGeneratedId() {
        if (!nameInput || !idInput || !roleSelect) return;
        const name = nameInput.value.trim();
        const role = roleSelect.value;
        if (!name || !role) {
            idInput.value = '';
            return;
        }
        
        if (role === 'student') {
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            let rand = Math.abs(hash % 9000) + 1000;
            idInput.value = '011233' + rand;
        } else if (role === 'teacher') {
            let clean = name.replace(/^(prof\.|dr\.|mr\.|mrs\.|ms\.)\s+/i, '');
            clean = clean.replace(/[^a-zA-Z\s]/g, '');
            let words = clean.split(/\s+/).filter(Boolean);
            let initial = '';
            
            if (words.length === 1) {
                initial = words[0].substring(0, 4);
                initial = initial.charAt(0).toUpperCase() + initial.slice(1).toLowerCase();
            } else if (words.length === 2) {
                initial = words[0].substring(0, 2).charAt(0).toUpperCase() + words[0].substring(0, 2).slice(1).toLowerCase() +
                          words[1].substring(0, 2).charAt(0).toUpperCase() + words[1].substring(0, 2).slice(1).toLowerCase();
            } else if (words.length === 3) {
                initial = words[0].substring(0, 2).charAt(0).toUpperCase() + words[0].substring(0, 2).slice(1).toLowerCase() +
                          words[1].substring(0, 2).charAt(0).toUpperCase() + words[1].substring(0, 2).slice(1).toLowerCase() +
                          words[2].substring(0, 2).charAt(0).toUpperCase() + words[2].substring(0, 2).slice(1).toLowerCase();
            } else {
                for (let i = 0; i < Math.min(words.length, 6); i++) {
                    let w = words[i];
                    initial += w.charAt(0).toUpperCase();
                }
            }
            idInput.value = initial;
        } else {
            idInput.value = '';
        }
    }
    
    if (nameInput && roleSelect) {
        nameInput.addEventListener('input', updateGeneratedId);
        roleSelect.addEventListener('change', updateGeneratedId);
    }

    // Helper for checking email availability in real-time
    function checkEmailDuplication(inputEl, statusEl, feedbackEl) {
        if (!inputEl || !statusEl || !feedbackEl) return;
        
        inputEl.addEventListener('input', function() {
            const email = this.value.trim();
            if (!email) {
                statusEl.innerHTML = '';
                feedbackEl.style.display = 'none';
                return;
            }
            
            // Basic regex check
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                statusEl.innerHTML = '❌';
                statusEl.style.color = '#ef4444';
                feedbackEl.innerHTML = 'Please enter a valid email address.';
                feedbackEl.style.color = '#ef4444';
                feedbackEl.style.display = 'block';
                return;
            }
            
            fetch('index.php?check_email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        statusEl.innerHTML = '❌';
                        statusEl.style.color = '#ef4444';
                        feedbackEl.innerHTML = 'This email is already in use!';
                        feedbackEl.style.color = '#ef4444';
                        feedbackEl.style.display = 'block';
                    } else {
                        statusEl.innerHTML = '✓';
                        statusEl.style.color = '#22c55e';
                        feedbackEl.innerHTML = 'Email is available.';
                        feedbackEl.style.color = '#22c55e';
                        feedbackEl.style.display = 'block';
                    }
                })
                .catch(() => {
                    statusEl.innerHTML = '';
                    feedbackEl.style.display = 'none';
                });
        });
    }

    const emailInput = document.getElementById('emailInput');
    const emailStatus = document.getElementById('emailStatus');
    const emailFeedback = document.getElementById('emailFeedback');
    checkEmailDuplication(emailInput, emailStatus, emailFeedback);

    const parentEmailInput = document.getElementById('parentEmailInput');
    const parentEmailStatus = document.getElementById('parentEmailStatus');
    const parentEmailFeedback = document.getElementById('parentEmailFeedback');
    checkEmailDuplication(parentEmailInput, parentEmailStatus, parentEmailFeedback);

    // --- Search & Management functionality ---
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const searchSuggestions = document.getElementById('searchSuggestions');
    
    const detailModal = document.getElementById('userDetailModal');
    const closeDetailModalBtn = document.getElementById('closeDetailModalBtn');
    const suspendUserBtn = document.getElementById('suspendUserBtn');
    const unsuspendUserBtn = document.getElementById('unsuspendUserBtn');
    
    let searchDebounceTimer;
    let selectedUser = null; // Holds currently clicked user details
    
    function performSearch() {
        if (!searchInput || !searchSuggestions) return;
        const query = searchInput.value.trim();
        const role = roleFilter ? roleFilter.value : 'all';
        
        if (query.length === 0) {
            searchSuggestions.style.display = 'none';
            return;
        }
        
        fetch(`index.php?search_users=${encodeURIComponent(query)}&role_filter=${encodeURIComponent(role)}`)
            .then(res => res.json())
            .then(data => {
                searchSuggestions.innerHTML = '';
                if (data && data.length > 0) {
                    data.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        
                        let subtitle = `${user.role.charAt(0).toUpperCase() + user.role.slice(1)}`;
                        if (user.role === 'teacher' && user.initials) {
                            subtitle += ` (${user.initials})`;
                        } else {
                            subtitle += ` (${user.id})`;
                        }
                        
                        div.innerHTML = `
                            <div class="suggestion-name">${user.name}</div>
                            <div class="suggestion-id">${subtitle} • Status: ${user.status}</div>
                        `;
                        
                        div.addEventListener('click', () => {
                            searchSuggestions.style.display = 'none';
                            searchInput.value = '';
                            showUserDetails(user);
                        });
                        searchSuggestions.appendChild(div);
                    });
                    searchSuggestions.style.display = 'block';
                } else {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.innerHTML = `<div class="suggestion-name" style="color: #71717a;">No matching users found</div>`;
                    searchSuggestions.appendChild(div);
                    searchSuggestions.style.display = 'block';
                }
            })
            .catch(err => console.error('Search error:', err));
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(performSearch, 300);
        });
    }
    
    if (roleFilter) {
        roleFilter.addEventListener('change', performSearch);
    }
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (searchInput && !searchInput.contains(e.target) && searchSuggestions && !searchSuggestions.contains(e.target) && roleFilter && !roleFilter.contains(e.target)) {
            searchSuggestions.style.display = 'none';
        }
    });
    
    function showUserDetails(user) {
        selectedUser = user;
        document.getElementById('detailUserName').textContent = user.name;
        document.getElementById('detailUserRole').textContent = user.role.toUpperCase();
        
        let detailsHTML = '';
        const addRow = (label, value) => {
            if (value !== null && value !== undefined && value !== '') {
                detailsHTML += `
                    <div class="modal-detail-row">
                        <span class="modal-detail-label">${label}</span>
                        <span class="modal-detail-value">${value}</span>
                    </div>
                `;
            }
        };
        
        addRow('User ID', user.id);
        addRow('Email Address', user.email);
        addRow('Department', user.dept_name || 'N/A');
        
        if (user.role === 'student') {
            addRow('Academic Year/Term', user.academic_year || 'N/A');
        } else if (user.role === 'teacher') {
            addRow('Initial', user.initials || 'N/A');
            addRow('Position', user.position || 'N/A');
        }
        
        addRow('Current Status', user.status);
        
        document.getElementById('detailModalBody').innerHTML = detailsHTML;
        
        // Show correct button based on status
        if (user.status === 'Inactive') {
            suspendUserBtn.style.display = 'none';
            unsuspendUserBtn.style.display = 'block';
        } else {
            suspendUserBtn.style.display = 'block';
            unsuspendUserBtn.style.display = 'none';
        }
        
        detailModal.style.display = 'flex';
    }
    
    if (closeDetailModalBtn) {
        closeDetailModalBtn.addEventListener('click', () => {
            detailModal.style.display = 'none';
            selectedUser = null;
        });
    }
    
    if (detailModal) {
        detailModal.addEventListener('click', (e) => {
            if (e.target === detailModal) {
                detailModal.style.display = 'none';
                selectedUser = null;
            }
        });
    }
    
    function updateStatus(newStatus) {
        if (!selectedUser) return;
        
        const formData = new FormData();
        formData.append('update_status', '1');
        formData.append('user_id', selectedUser.id);
        formData.append('status', newStatus);
        
        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                selectedUser.status = newStatus;

                // Update modal UI immediately
                showUserDetails(selectedUser);

                // Update the row badge in the directory table
                const row = document.querySelector(`.user-dir-row[data-user*='"id":"${selectedUser.id}"']`);
                if (row) {
                    const badge = row.querySelector('span[style*="border-radius: 20px"]');
                    if (badge) {
                        const isActive = newStatus === 'Active';
                        badge.textContent = newStatus;
                        badge.style.color = isActive ? '#22c55e' : '#ef4444';
                        badge.style.background = isActive ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)';
                    }
                    // Update data-user attribute
                    try {
                        const userData = JSON.parse(row.getAttribute('data-user'));
                        userData.status = newStatus;
                        row.setAttribute('data-user', JSON.stringify(userData));
                    } catch(e) {}
                }
            } else {
                alert('Error changing user status: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Failed to update status.');
        });
    }
    
    if (suspendUserBtn) {
        suspendUserBtn.addEventListener('click', () => {
            if (confirm(`Are you sure you want to suspend user ${selectedUser.name}?`)) {
                updateStatus('Inactive');
            }
        });
    }
    
    if (unsuspendUserBtn) {
        unsuspendUserBtn.addEventListener('click', () => {
            if (confirm(`Are you sure you want to unsuspend user ${selectedUser.name}?`)) {
                updateStatus('Active');
            }
        });
    }

    // --- Wire up User Directory "View" buttons ---
    document.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.view-user-btn');
        if (viewBtn) {
            e.stopPropagation();
            const row = viewBtn.closest('.user-dir-row');
            if (row) {
                try {
                    const userData = JSON.parse(row.getAttribute('data-user'));
                    showUserDetails(userData);
                } catch (err) {
                    console.error('Failed to parse user data:', err);
                }
            }
        }
    });

    // ===== Live Clock for Top Bar =====
    function updateClock() {
        const dateSpan = document.getElementById('topBarDate');
        const timeSpan = document.getElementById('topBarTime');
        if (!dateSpan || !timeSpan) return;

        const now = new Date();
        const day = now.getDate();
        const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const month = months[now.getMonth()];
        
        let suffix = "th";
        if (day === 1 || day === 21 || day === 31) suffix = "st";
        else if (day === 2 || day === 22) suffix = "nd";
        else if (day === 3 || day === 23) suffix = "rd";
        
        dateSpan.textContent = `${day}${suffix} ${month}`;
        
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const hoursStr = hours.toString().padStart(2, '0');
        
        timeSpan.textContent = `${hoursStr}:${minutes} ${ampm}`;
    }
    updateClock();
    setInterval(updateClock, 1000);
});
