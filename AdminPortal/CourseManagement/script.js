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
                addRow('Enrolled Students', data.enrolled_students);

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

    // Register Program card click
    const registerCard = document.getElementById('registerProgramCard');
    if (registerCard) {
        registerCard.addEventListener('click', () => {
            alert('Register new Program feature coming soon!');
        });
    }
});
