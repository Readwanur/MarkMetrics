// Dashboard script.js

document.addEventListener('DOMContentLoaded', () => {
    // Toggle Teacher/Student in registry
    const teacherBtn = document.getElementById('teacherToggle');
    const studentBtn = document.getElementById('studentToggle');

    if (teacherBtn && studentBtn) {
        teacherBtn.addEventListener('click', () => {
            teacherBtn.classList.add('active');
            studentBtn.classList.remove('active');
        });

        studentBtn.addEventListener('click', () => {
            studentBtn.classList.add('active');
            teacherBtn.classList.remove('active');
        });
    }

    // Animate stat values on page load
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach((el) => {
        const finalText = el.textContent;
        const numericValue = parseFloat(finalText.replace(/[^0-9.]/g, ''));

        if (!isNaN(numericValue) && numericValue > 0) {
            let current = 0;
            const duration = 1200;
            const step = 16;
            const increment = numericValue / (duration / step);
            const isDecimal = finalText.includes('.');
            const hasComma = finalText.includes(',');
            const suffix = finalText.replace(/[0-9.,]/g, '');

            el.textContent = isDecimal ? '0.0' : '0';

            const timer = setInterval(() => {
                current += increment;
                if (current >= numericValue) {
                    el.textContent = finalText;
                    clearInterval(timer);
                } else {
                    let display;
                    if (isDecimal) {
                        display = current.toFixed(1);
                    } else {
                        display = Math.floor(current).toString();
                        if (hasComma) {
                            display = display.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        }
                    }
                    el.textContent = display + suffix;
                }
            }, step);
        }
    });

    // Animate bars on load
    const bars = document.querySelectorAll('.bar-chart .bar');
    bars.forEach((bar) => {
        const targetHeight = bar.style.height;
        bar.style.height = '0%';
        setTimeout(() => {
            bar.style.height = targetHeight;
        }, 300);
    });

    // --- Search functionality ---
    const searchInput = document.getElementById('searchInput');
    const searchSuggestions = document.getElementById('searchSuggestions');
    const modalOverlay = document.getElementById('userInfoModal');
    const closeModalBtn = document.getElementById('closeModalBtn');

    let debounceTimer;

    if (searchInput && searchSuggestions) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length === 0) {
                searchSuggestions.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`index.php?action=search&q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchSuggestions.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(user => {
                                const div = document.createElement('div');
                                div.className = 'suggestion-item';
                                div.innerHTML = `
                                    <div class="suggestion-name">${user.name}</div>
                                    <div class="suggestion-id">${user.id}</div>
                                `;
                                div.addEventListener('click', () => {
                                    searchSuggestions.style.display = 'none';
                                    searchInput.value = '';
                                    fetchUserDetails(user.id);
                                });
                                searchSuggestions.appendChild(div);
                            });
                            searchSuggestions.style.display = 'block';
                        } else {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.innerHTML = `<div class="suggestion-name" style="color: #6b7280;">No results found</div>`;
                            searchSuggestions.appendChild(div);
                            searchSuggestions.style.display = 'block';
                        }
                    })
                    .catch(err => console.error('Error fetching suggestions:', err));
            }, 300);
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });
    }

    function fetchUserDetails(id) {
        fetch(`index.php?action=user_info&id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return alert(data.error);

                document.getElementById('modalUserName').textContent = data.name || 'N/A';
                document.getElementById('modalUserRole').textContent = data.role || 'User';

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

                addRow('ID', data.id);
                addRow('Email', data.email);
                addRow('Department', data.dept_name);
                addRow('Status', data.status);

                if (data.role === 'student') {
                    addRow('Program', data.program_name);
                    addRow('Date of Birth', data.date_of_birth);
                    addRow('Academic Year', data.academic_year);
                    addRow('Credits Earned', data.total_credits_earned);
                    addRow('Cumulative GPA', data.cumulative_gpa);
                } else if (data.role === 'teacher') {
                    addRow('Position', data.position);
                }

                document.getElementById('modalUserDetails').innerHTML = detailsHTML;
                modalOverlay.style.display = 'flex';
            })
            .catch(err => console.error('Error fetching user info:', err));
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modalOverlay.style.display = 'none';
        });
    }

    // Close modal on clicking outside content
    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                modalOverlay.style.display = 'none';
            }
        });
    }

    // --- Real-time Audit Logs Polling ---
    const auditList = document.getElementById('auditList');
    let lastKnownLogId = null;

    // Get the initial latest log_id from the DOM (if any real logs exist)
    function getInitialLogId() {
        const firstItem = auditList ? auditList.querySelector('.audit-item[data-log-id]') : null;
        return firstItem ? parseInt(firstItem.dataset.logId, 10) : 0;
    }

    function fetchAuditLogs() {
        fetch('index.php?action=fetch_audit_logs')
            .then(res => res.json())
            .then(logs => {
                if (!auditList) return;

                // Check if there's new data
                const newestId = logs.length > 0 ? logs[0].log_id : 0;
                if (newestId == lastKnownLogId) return; // No change
                lastKnownLogId = newestId;

                // Build new HTML
                if (logs.length === 0) {
                    auditList.innerHTML = `
                        <div class="audit-item">
                            <div class="audit-time">—</div>
                            <p class="audit-text" style="color: #71717a;">No activity recorded yet. Logs will appear here as users log in, accounts are provisioned, or passwords are reset.</p>
                        </div>
                    `;
                    return;
                }

                let html = '';
                logs.forEach((log, index) => {
                    const isNew = index === 0 && lastKnownLogId !== null;
                    html += `
                        <div class="audit-item ${isNew ? 'audit-item-new' : ''}" data-log-id="${log.log_id}">
                            <div class="audit-time">${log.time_label}</div>
                            <p class="audit-text">${log.description}</p>
                        </div>
                    `;
                });

                auditList.innerHTML = html;

                // Animate new items
                const newItems = auditList.querySelectorAll('.audit-item-new');
                newItems.forEach(item => {
                    item.addEventListener('animationend', () => {
                        item.classList.remove('audit-item-new');
                    }, { once: true });
                });
            })
            .catch(err => console.error('Audit log poll error:', err));
    }

    // Initialize
    if (auditList) {
        lastKnownLogId = getInitialLogId();
        // Poll every 5 seconds
        setInterval(fetchAuditLogs, 5000);
        // Also fetch immediately to sync
        fetchAuditLogs();
    }
});
