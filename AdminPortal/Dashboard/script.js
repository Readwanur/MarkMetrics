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
    const roleFilterDropdown = document.getElementById('roleFilter');
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
                const role = roleFilterDropdown ? roleFilterDropdown.value : 'all';
                fetch(`index.php?action=search&q=${encodeURIComponent(query)}&role=${encodeURIComponent(role)}`)
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

        if (roleFilterDropdown) {
            roleFilterDropdown.addEventListener('change', () => {
                if (searchInput.value.trim().length > 0) {
                    searchInput.dispatchEvent(new Event('input'));
                }
            });
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target) && (!roleFilterDropdown || !roleFilterDropdown.contains(e.target))) {
                searchSuggestions.style.display = 'none';
            }
        });
    }

    let selectedUser = null;
    const suspendUserBtn = document.getElementById('suspendUserBtn');
    const unsuspendUserBtn = document.getElementById('unsuspendUserBtn');

    function fetchUserDetails(id) {
        fetch(`index.php?action=user_info&id=${encodeURIComponent(id)}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return alert(data.error);

                selectedUser = data;
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
                addRow('Status', data.status === 'Inactive' ? 'Suspended' : data.status);

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

                // Show correct button based on status
                if (data.status === 'Inactive') {
                    if (suspendUserBtn) suspendUserBtn.style.display = 'none';
                    if (unsuspendUserBtn) unsuspendUserBtn.style.display = 'block';
                } else {
                    if (suspendUserBtn) suspendUserBtn.style.display = 'block';
                    if (unsuspendUserBtn) unsuspendUserBtn.style.display = 'none';
                }

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

    // --- User Directory Actions ---
    if (suspendUserBtn) {
        suspendUserBtn.addEventListener('click', () => {
            if (selectedUser && confirm(`Are you sure you want to suspend user ${selectedUser.name}?`)) {
                updateUserStatus('Inactive');
            }
        });
    }

    if (unsuspendUserBtn) {
        unsuspendUserBtn.addEventListener('click', () => {
            if (selectedUser && confirm(`Are you sure you want to unsuspend user ${selectedUser.name}?`)) {
                updateUserStatus('Active');
            }
        });
    }

    function updateUserStatus(newStatus) {
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
                fetchUserDetails(selectedUser.id);
                loadUserDirectory(currentDirPage);
                if (typeof fetchAuditLogs === 'function') {
                    fetchAuditLogs();
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

    // --- Directory Loading and Pagination ---
    let currentDirPage = 1;
    let currentDirRole = 'all';
    let currentSortField = ''; // 'name', 'id', 'access', or 'status'
    let currentSortOrder = 'asc'; // 'asc' or 'desc'
    let currentDirLimit = 6;

    function loadUserDirectory(page) {
        currentDirPage = page;
        const tbody = document.getElementById('registryBody');
        const countSpan = document.getElementById('directoryTotalCount');
        if (!tbody) return;
        
        const sortParam = currentSortField ? `${currentSortField}_${currentSortOrder}` : '';
        const limitParam = currentDirLimit === 'all' ? '&limit=all' : '';
        fetch(`index.php?action=fetch_directory&page=${page}&role=${encodeURIComponent(currentDirRole)}&sort=${encodeURIComponent(sortParam)}${limitParam}`)
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                
                if (data.users && data.users.length > 0) {
                    data.users.forEach(u => {
                        const tr = document.createElement('tr');
                        
                        const avatarLetter = u.name.trim().charAt(0).toUpperCase();
                        const avatarBg = u.role === 'teacher' ? '#3b82f6' : '#22c55e';
                        
                        // Split ID and Initials logic
                        const idVal = u.role === 'student' ? u.id : '—';
                        const initialVal = u.role === 'teacher' ? (u.initials || '—') : '—';
                        
                        // Access Time
                        const accessTime = u.last_login || '—';
                        
                        const isActive = u.status === 'Active';
                        const isSuspended = u.status === 'Inactive';
                        const statusColor = isActive ? '#22c55e' : '#ef4444';
                        const statusBg = isActive ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)';
                        const statusLabel = isSuspended ? 'Suspended' : u.status;
                        
                        tr.innerHTML = `
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm" style="background: ${avatarBg}; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 14px; border: none;">
                                        ${avatarLetter}
                                    </div>
                                    <div>
                                        <div class="user-name">${escapeHTML(u.name)}</div>
                                        <div class="user-dept">${escapeHTML(u.dept_name || 'N/A')}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span style="font-family: monospace; font-size: 13px; color: var(--text-secondary);">${escapeHTML(accessTime)}</span></td>
                            <td><span style="font-family: monospace; font-size: 13px; color: var(--text-secondary);">${escapeHTML(idVal)}</span></td>
                            <td><span style="font-family: monospace; font-size: 13px; color: var(--text-secondary);">${escapeHTML(initialVal)}</span></td>
                            <td><span class="role-text" style="color: ${u.role === 'teacher' ? '#60a5fa' : '#4ade80'};">${u.role.charAt(0).toUpperCase() + u.role.slice(1)}</span></td>
                            <td>
                                <span class="status-badge" style="background: ${statusBg}; color: ${statusColor}; border: 1px solid rgba(${isActive ? '34,197,94' : '239,68,68'}, 0.25); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                    <span class="status-badge-dot" style="background: ${statusColor};"></span>
                                    ${escapeHTML(statusLabel)}
                                </span>
                            </td>
                            <td>
                                <button class="view-user-btn" style="background: var(--card-bg-lighter); border: 1px solid var(--border-color); border-radius: 6px; color: var(--accent-orange); font-size: 12px; font-weight: 600; padding: 6px 14px; cursor: pointer; transition: all 0.2s;" onmouseenter="this.style.background='var(--accent-orange)'; this.style.color='#fff'; this.style.borderColor='var(--accent-orange)'" onmouseleave="this.style.background='var(--card-bg-lighter)'; this.style.color='var(--accent-orange)'; this.style.borderColor='var(--border-color)'">View</button>
                            </td>
                        `;
                        
                        const viewBtn = tr.querySelector('.view-user-btn');
                        if (viewBtn) {
                            viewBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                fetchUserDetails(u.id);
                            });
                        }
                        
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">No matching users found</td>
                        </tr>
                    `;
                }
                
                if (countSpan) {
                    countSpan.textContent = `Total ${data.total_users} users`;
                }
                
                renderPagination(data.total_users, data.current_page);
            })
            .catch(err => console.error('Directory fetch error:', err));
    }

    function renderPagination(totalUsers, currentPage) {
        const pagination = document.getElementById('directoryPagination');
        if (!pagination) return;
        pagination.innerHTML = '';
        
        const defaultLimit = 6;
        const normalTotalPages = Math.ceil(totalUsers / defaultLimit);
        
        if (normalTotalPages <= 1 && currentDirLimit !== 'all') return;
        
        const isAllMode = (currentDirLimit === 'all');
        
        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.textContent = 'Prev';
        prevBtn.className = 'pagination-btn';
        prevBtn.disabled = isAllMode || currentPage === 1;
        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            loadUserDirectory(currentPage - 1);
        });
        pagination.appendChild(prevBtn);
        
        // Page numbers
        for (let i = 1; i <= normalTotalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.textContent = i;
            pageBtn.className = (!isAllMode && i === currentPage) ? 'pagination-btn active' : 'pagination-btn';
            pageBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentDirLimit = 6;
                loadUserDirectory(i);
            });
            pagination.appendChild(pageBtn);
        }
        
        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Next';
        nextBtn.className = 'pagination-btn';
        nextBtn.disabled = isAllMode || currentPage === normalTotalPages;
        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            loadUserDirectory(currentPage + 1);
        });
        pagination.appendChild(nextBtn);

        // All Button
        const allBtn = document.createElement('button');
        allBtn.textContent = 'All';
        allBtn.className = isAllMode ? 'pagination-btn active' : 'pagination-btn';
        allBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (isAllMode) {
                currentDirLimit = 6;
                loadUserDirectory(1);
            } else {
                currentDirLimit = 'all';
                loadUserDirectory(1);
            }
        });
        pagination.appendChild(allBtn);
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Wire sorting & filter
    const thSortName = document.getElementById('thSortName');
    const thSortAccess = document.getElementById('thSortAccess');
    const thSortId = document.getElementById('thSortId');
    const thSortStatus = document.getElementById('thSortStatus');
    const dirFilter = document.getElementById('dirRoleFilter');

    function updateSortIndicators() {
        const indicatorName = document.getElementById('sortIndicatorName');
        const indicatorAccess = document.getElementById('sortIndicatorAccess');
        const indicatorId = document.getElementById('sortIndicatorId');
        const indicatorStatus = document.getElementById('sortIndicatorStatus');
        
        const thName = document.getElementById('thSortName');
        const thAccess = document.getElementById('thSortAccess');
        const thId = document.getElementById('thSortId');
        const thStatus = document.getElementById('thSortStatus');
        
        if (!indicatorName || !indicatorAccess || !indicatorId || !indicatorStatus || 
            !thName || !thAccess || !thId || !thStatus) return;
        
        // Reset styles and text
        indicatorName.textContent = '⇅';
        indicatorAccess.textContent = '⇅';
        indicatorId.textContent = '⇅';
        indicatorStatus.textContent = '⇅';
        
        thName.style.color = '';
        thAccess.style.color = '';
        thId.style.color = '';
        thStatus.style.color = '';
        
        if (currentSortField === 'name') {
            thName.style.color = 'var(--accent-orange)';
            indicatorName.textContent = currentSortOrder === 'asc' ? '▲' : '▼';
        } else if (currentSortField === 'access') {
            thAccess.style.color = 'var(--accent-orange)';
            indicatorAccess.textContent = currentSortOrder === 'asc' ? '▲' : '▼';
        } else if (currentSortField === 'id') {
            thId.style.color = 'var(--accent-orange)';
            indicatorId.textContent = currentSortOrder === 'asc' ? '▲' : '▼';
        } else if (currentSortField === 'status') {
            thStatus.style.color = 'var(--accent-orange)';
            indicatorStatus.textContent = currentSortOrder === 'asc' ? '▲' : '▼';
        }
    }

    if (thSortName) {
        thSortName.addEventListener('click', () => {
            if (currentSortField === 'name') {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortField = 'name';
                currentSortOrder = 'asc';
            }
            updateSortIndicators();
            loadUserDirectory(1);
        });
    }

    if (thSortAccess) {
        thSortAccess.addEventListener('click', () => {
            if (currentSortField === 'access') {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortField = 'access';
                currentSortOrder = 'desc'; // Default to newest login first
            }
            updateSortIndicators();
            loadUserDirectory(1);
        });
    }

    if (thSortId) {
        thSortId.addEventListener('click', () => {
            if (currentSortField === 'id') {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortField = 'id';
                currentSortOrder = 'asc';
            }
            updateSortIndicators();
            loadUserDirectory(1);
        });
    }

    if (thSortStatus) {
        thSortStatus.addEventListener('click', () => {
            if (currentSortField === 'status') {
                currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortField = 'status';
                currentSortOrder = 'asc';
            }
            updateSortIndicators();
            loadUserDirectory(1);
        });
    }

    if (dirFilter) {
        dirFilter.addEventListener('change', () => {
            currentDirRole = dirFilter.value;
            loadUserDirectory(1);
        });
    }

    // Initialize
    if (auditList) {
        lastKnownLogId = getInitialLogId();
        // Poll every 5 seconds
        setInterval(fetchAuditLogs, 5000);
        // Also fetch immediately to sync
        fetchAuditLogs();
    }

    // --- Audit Logs Slide-out Drawer ---
    const viewAllAuditBtn = document.getElementById('viewAllAuditBtn');
    const auditDrawerOverlay = document.getElementById('auditDrawerOverlay');
    const closeDrawerBtn = document.getElementById('closeDrawerBtn');
    const drawerAuditList = document.getElementById('drawerAuditList');
    const drawerSearchInput = document.getElementById('drawerSearchInput');
    let allDrawerLogs = [];

    if (viewAllAuditBtn && auditDrawerOverlay && closeDrawerBtn && drawerAuditList) {
        viewAllAuditBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openAuditDrawer();
        });

        closeDrawerBtn.addEventListener('click', () => {
            closeAuditDrawer();
        });

        auditDrawerOverlay.addEventListener('click', (e) => {
            if (e.target === auditDrawerOverlay) {
                closeAuditDrawer();
            }
        });

        if (drawerSearchInput) {
            drawerSearchInput.addEventListener('input', () => {
                filterDrawerLogs(drawerSearchInput.value.trim());
            });
        }
    }

    function openAuditDrawer() {
        auditDrawerOverlay.style.display = 'flex';
        // Allow rendering to happen, then apply the active class for slide-in animation
        setTimeout(() => {
            auditDrawerOverlay.classList.add('active');
        }, 10);
        fetchDrawerAuditLogs();
    }

    function closeAuditDrawer() {
        auditDrawerOverlay.classList.remove('active');
        // Wait for CSS transition (0.3s) before hiding display
        setTimeout(() => {
            auditDrawerOverlay.style.display = 'none';
        }, 300);
        if (drawerSearchInput) {
            drawerSearchInput.value = '';
        }
    }

    function fetchDrawerAuditLogs() {
        drawerAuditList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">Loading logs...</div>';
        
        fetch('index.php?action=fetch_audit_logs&limit=all')
            .then(res => res.json())
            .then(logs => {
                allDrawerLogs = logs;
                renderDrawerLogs(logs);
            })
            .catch(err => {
                console.error('Error fetching all audit logs:', err);
                drawerAuditList.innerHTML = '<div style="text-align: center; color: var(--accent-red); padding: 20px;">Failed to load audit logs.</div>';
            });
    }

    function renderDrawerLogs(logs) {
        if (!logs || logs.length === 0) {
            drawerAuditList.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">No matching activity logs.</div>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            html += `
                <div class="audit-item" data-log-id="${log.log_id}">
                    <div class="audit-time">${log.time_label}</div>
                    <p class="audit-text">${log.description}</p>
                </div>
            `;
        });
        drawerAuditList.innerHTML = html;
    }

    function filterDrawerLogs(query) {
        if (!query) {
            renderDrawerLogs(allDrawerLogs);
            return;
        }

        const lowercaseQuery = query.toLowerCase();
        const filtered = allDrawerLogs.filter(log => {
            const desc = (log.description || '').toLowerCase();
            const label = (log.time_label || '').toLowerCase();
            const name = (log.user_name || '').toLowerCase();
            return desc.includes(lowercaseQuery) || label.includes(lowercaseQuery) || name.includes(lowercaseQuery);
        });
        renderDrawerLogs(filtered);
    }

    // Initial load
    loadUserDirectory(1);
});

// ===== Admin Notification Bell =====
function toggleAdminNoti(event) {
    if (event) event.stopPropagation();
    const dropdown = document.getElementById('adminNotiDropdown');
    if (!dropdown) return;

    const isVisible = dropdown.style.display !== 'none';
    if (isVisible) {
        dropdown.style.display = 'none';
        return;
    }

    // Populate with recent audit log items
    dropdown.style.display = 'block';
    const notiContent = document.getElementById('notiContent');
    const notiCount = document.getElementById('notiCount');

    fetch('index.php?action=fetch_audit_logs')
        .then(res => res.json())
        .then(logs => {
            if (!logs || logs.length === 0) {
                notiContent.innerHTML = '<div class="noti-empty">✓ No recent activity</div>';
                if (notiCount) notiCount.textContent = '0 events';
                return;
            }
            if (notiCount) notiCount.textContent = logs.length + ' Recent';
            let html = '';
            logs.slice(0, 6).forEach(log => {
                html += `
                <div style="display:flex; align-items:flex-start; gap:10px; padding:10px; border-radius:8px; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04); margin-bottom:6px;">
                    <div style="width:8px; height:8px; border-radius:50%; background:var(--accent-orange); flex-shrink:0; margin-top:5px;"></div>
                    <div>
                        <div style="font-size:12px; color:#e4e4e7; line-height:1.4;">${log.description || log.action || 'System event'}</div>
                        <div style="font-size:10px; color:#71717a; margin-top:3px;">${log.time_label || ''}</div>
                    </div>
                </div>`;
            });
            notiContent.innerHTML = html;
        })
        .catch(() => {
            notiContent.innerHTML = '<div class="noti-empty">Failed to load notifications</div>';
        });
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('adminNotiDropdown');
    const bellBtn = document.getElementById('notificationBtn');
    if (dropdown && dropdown.style.display === 'block') {
        if (!dropdown.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
            dropdown.style.display = 'none';
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
document.addEventListener('DOMContentLoaded', () => {
    updateClock();
    setInterval(updateClock, 1000);
});


