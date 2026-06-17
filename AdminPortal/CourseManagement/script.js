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
