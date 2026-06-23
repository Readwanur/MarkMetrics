// Course Management script.js

document.addEventListener('DOMContentLoaded', () => {
    // View toggle (Grid / List)
    const gridViewBtn = document.getElementById('gridViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    const coursesGrid = document.getElementById('coursesGrid');

    if (gridViewBtn && listViewBtn && coursesGrid) {
        gridViewBtn.addEventListener('click', () => {
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            coursesGrid.style.gridTemplateColumns = 'repeat(3, 1fr)';
        });

        listViewBtn.addEventListener('click', () => {
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
            coursesGrid.style.gridTemplateColumns = '1fr';
        });
    }

    // Animate stat values on page load
    const statElements = [
        { el: document.getElementById('activeStudents') },
        { el: document.getElementById('completionRate') }
    ];

    statElements.forEach(({ el }) => {
        if (!el) return;
        const final = el.textContent;
        const numericValue = parseFloat(final.replace(/[^0-9.]/g, ''));
        const isPercent = final.includes('%');
        const hasComma = final.includes(',');

        let current = 0;
        const duration = 1200;
        const step = 16;
        const increment = numericValue / (duration / step);

        el.textContent = '0';

        const timer = setInterval(() => {
            current += increment;
            if (current >= numericValue) {
                el.textContent = final;
                clearInterval(timer);
            } else {
                let display;
                if (isPercent) {
                    display = current.toFixed(1) + '%';
                } else {
                    display = Math.floor(current).toString();
                    if (hasComma) {
                        display = display.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    }
                }
                el.textContent = display;
            }
        }, step);
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
                            data.forEach(course => {
                                const div = document.createElement('div');
                                div.className = 'suggestion-item';
                                div.innerHTML = `
                                    <div class="suggestion-name">${course.course_name}</div>
                                    <div class="suggestion-id">${course.course_code}</div>
                                `;
                                div.addEventListener('click', () => {
                                    searchSuggestions.style.display = 'none';
                                    searchInput.value = '';
                                    fetchCourseDetails(course.course_code);
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

    function fetchCourseDetails(code) {
        fetch(`index.php?action=course_info&code=${encodeURIComponent(code)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.course_code) return;

                document.getElementById('modalUserName').textContent = data.course_name || 'N/A';
                document.getElementById('modalUserRole').textContent = data.course_code || '';

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

                addRow('Course Code', data.course_code);
                addRow('Department', data.dept_name);
                addRow('Credits', data.credits);
                addRow('Semester', data.semester);
                addRow('Section', data.section || 'A');
                addRow('Room', data.room_number || 'TBA');
                addRow('Instructor', data.teacher_name || 'TBA');
                addRow('Enrolled Count', data.enrolled_students);

                // Enrolled Students list section
                detailsHTML += `
                    <div class="modal-enrolled-students-section" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                        <span class="modal-detail-label" style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 10px;">Enrolled Students</span>
                `;
                if (data.enrolled_list && data.enrolled_list.length > 0) {
                    detailsHTML += `<div style="max-height: 180px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding-right: 6px;">`;
                    data.enrolled_list.forEach(student => {
                        detailsHTML += `
                            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 14px; font-size: 13px;">
                                <span style="font-weight: 600; color: var(--text-primary);">${student.name}</span>
                                <span style="font-family: monospace; color: var(--text-muted); font-size: 11px;">${student.id}</span>
                            </div>
                        `;
                    });
                    detailsHTML += `</div>`;
                } else {
                    detailsHTML += `
                        <div style="text-align: center; color: var(--text-muted); font-size: 13px; padding: 12px 0;">
                            No students enrolled in this course yet.
                        </div>
                    `;
                }
                detailsHTML += `</div>`;

                document.getElementById('modalUserDetails').innerHTML = detailsHTML;
                modalOverlay.style.display = 'flex';
            })
            .catch(err => console.error('Error fetching course info:', err));
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modalOverlay.style.display = 'none';
        });
    }

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                modalOverlay.style.display = 'none';
            }
        });
    }

    // Attach click listeners to course cards
    document.querySelectorAll('.course-card').forEach(card => {
        card.addEventListener('click', () => {
            const code = card.getAttribute('data-course-code');
            if (code) {
                fetchCourseDetails(code);
            }
        });
    });

    // Combined Status and Department filtering logic
    let selectedStatus = 'Active';
    let selectedDept = 'all';

    function applyFilters() {
        const catalogTitleText = document.getElementById('catalogTitleText');
        if (catalogTitleText) {
            catalogTitleText.textContent = `${selectedStatus} Catalog`;
        }

        document.querySelectorAll('.course-card').forEach(card => {
            const cardStatus = card.getAttribute('data-status') || 'Active';
            const cardDept = card.getAttribute('data-dept-id');

            const matchesStatus = (cardStatus === selectedStatus);
            const matchesDept = (selectedDept === 'all' || cardDept === selectedDept);

            if (matchesStatus && matchesDept) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        // Show/hide "Register new Program" card only when status is 'Active'
        const registerCard = document.getElementById('registerProgramCard');
        if (registerCard) {
            if (selectedStatus === 'Active') {
                registerCard.style.display = '';
            } else {
                registerCard.style.display = 'none';
            }
        }
    }

    // Status filter buttons
    document.querySelectorAll('.status-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.status-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedStatus = btn.getAttribute('data-status');
            applyFilters();
        });
    });

    // Department filtering (Dropdown Selector)
    const deptFilterSelect = document.getElementById('deptFilter');
    if (deptFilterSelect) {
        deptFilterSelect.addEventListener('change', (e) => {
            selectedDept = e.target.value;
            applyFilters();
        });
    }

    // Run initial filter on load
    applyFilters();

    // Drop Section action
    document.querySelectorAll('.drop-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const code = btn.getAttribute('data-code');
            if (confirm(`Are you sure you want to drop all enrollments and mark section ${code} as Dropped?`)) {
                const formData = new FormData();
                formData.append('action', 'drop_course');
                formData.append('course_code', code);

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`Successfully dropped course/section ${code}.`);
                        window.location.reload();
                    } else {
                        alert(`Error dropping course: ${data.error}`);
                    }
                })
                .catch(err => {
                    console.error('Error dropping course:', err);
                    alert('An error occurred while dropping the course.');
                });
            }
        });
    });

    // Delete Section action
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const code = btn.getAttribute('data-code');
            if (confirm(`Are you sure you want to soft-delete course/section ${code}? This can be restored from the Deleted sections catalog.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_course');
                formData.append('course_code', code);

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`Successfully deleted course/section ${code}.`);
                        window.location.reload();
                    } else {
                        alert(`Error deleting course: ${data.error}`);
                    }
                })
                .catch(err => {
                    console.error('Error deleting course:', err);
                    alert('An error occurred while deleting the course.');
                });
            }
        });
    });

    // Restore Section action
    document.querySelectorAll('.restore-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const code = btn.getAttribute('data-code');
            if (confirm(`Are you sure you want to restore course/section ${code} back to the Active catalog?`)) {
                const formData = new FormData();
                formData.append('action', 'restore_course');
                formData.append('course_code', code);

                fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`Successfully restored course/section ${code}.`);
                        window.location.reload();
                    } else {
                        alert(`Error restoring course: ${data.error}`);
                    }
                })
                .catch(err => {
                    console.error('Error restoring course:', err);
                    alert('An error occurred while restoring the course.');
                });
            }
        });
    });

    // Register Program card click
    const registerCard = document.getElementById('registerProgramCard');
    const registerCourseModal = document.getElementById('registerCourseModal');
    const closeRegisterModalBtn = document.getElementById('closeRegisterModalBtn');

    if (registerCard && registerCourseModal) {
        registerCard.addEventListener('click', () => {
            registerCourseModal.style.display = 'flex';
        });

        if (closeRegisterModalBtn) {
            closeRegisterModalBtn.addEventListener('click', () => {
                registerCourseModal.style.display = 'none';
            });
        }

        registerCourseModal.addEventListener('click', (e) => {
            if (e.target === registerCourseModal) {
                registerCourseModal.style.display = 'none';
            }
        });
    }

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
