document.addEventListener('DOMContentLoaded', () => {
    const studentSearch = document.getElementById('student-search');
    const searchResults = document.getElementById('search-results');
    const courseSelect = document.getElementById('course-select');
    const currentGradeDisplay = document.getElementById('current-grade');
    const submitBtn = document.getElementById('submit-update');
    const activityList = document.getElementById('activity-list');
    const pendingCount = document.getElementById('pending-count');
    const approvalRate = document.getElementById('approval-rate');

    let selectedStudentId = null;
    let studentCourses = [];


    fetchRegistryData();

    let searchTimeout;
    studentSearch.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`api.php?action=search_students&q=${query}`)
                .then(res => res.json())
                .then(data => {
                    displaySearchResults(data);
                });
        }, 300);
    });

    function displaySearchResults(students) {
        searchResults.innerHTML = '';
        if (students.length === 0) {
            searchResults.innerHTML = '<div class="search-item">No students found</div>';
        } else {
            students.forEach(student => {
                const div = document.createElement('div');
                div.className = 'search-item';
                div.innerHTML = `
                    <div class="student-name">${student.name}</div>
                    <div class="student-id">${student.id}</div>
                `;
                div.addEventListener('click', () => selectStudent(student));
                searchResults.appendChild(div);
            });
        }
        searchResults.classList.remove('hidden');
    }

    function selectStudent(student) {
        selectedStudentId = student.id;
        studentSearch.value = `${student.name} (${student.id})`;
        searchResults.classList.add('hidden');
        loadStudentCourses(student.id);
    }

    function loadStudentCourses(studentId) {
        fetch(`api.php?action=get_student_courses&student_id=${studentId}`)
            .then(res => res.json())
            .then(data => {
                studentCourses = data;
                populateCourseSelect(data);
            });
    }

    function populateCourseSelect(courses) {
        courseSelect.innerHTML = '<option value="" disabled selected>Select a course</option>';
        courses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.course_code;
            option.textContent = `${course.course_code}: ${course.course_name}`;
            courseSelect.appendChild(option);
        });
        currentGradeDisplay.textContent = '--';
    }

    courseSelect.addEventListener('change', (e) => {
        const courseCode = e.target.value;
        const course = studentCourses.find(c => c.course_code === courseCode);
        if (course) {
            currentGradeDisplay.textContent = course.current_grade || '--';
        }
    });

    submitBtn.addEventListener('click', () => {
        const courseCode = courseSelect.value;
        const newGrade = document.getElementById('new-grade').value;
        const justification = document.getElementById('justification').value.trim();

        if (!selectedStudentId || !courseCode || !justification) {
            alert('Please fill in all required fields.');
            return;
        }

        const payload = {
            student_id: selectedStudentId,
            course_code: courseCode,
            current_grade: currentGradeDisplay.textContent,
            new_grade: newGrade,
            justification: justification
        };

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        fetch('api.php?action=submit_request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Registry update submitted successfully!');
                    resetForm();
                    fetchRegistryData();
                } else {
                    alert('Error: ' + (data.error || 'Submission failed'));
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Registry Update';
            });
    });

    function resetForm() {
        studentSearch.value = '';
        selectedStudentId = null;
        courseSelect.innerHTML = '<option value="" disabled selected>Select a course</option>';
        currentGradeDisplay.textContent = '--';
        document.getElementById('justification').value = '';
    }

    function fetchRegistryData() {
        fetch('api.php?action=get_registry_data')
            .then(res => res.json())
            .then(data => {
                displayActivity(data.activity);
                pendingCount.textContent = data.stats.pending_tasks;
                approvalRate.textContent = data.stats.approval_rate;
            });
    }

    function displayActivity(activity) {
        activityList.innerHTML = '';
        activity.forEach(item => {
            const date = new Date(item.created_at);
            const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const dateStr = date.toLocaleDateString();

            const statusClass = `status-${item.status.toLowerCase()}`;

            const div = document.createElement('div');
            div.className = 'activity-item';
            div.innerHTML = `
                <div class="activity-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <div class="activity-details">
                    <div class="activity-title">
                        <h4>${item.course_name}</h4>
                        <span class="status-badge ${statusClass}">${item.status}</span>
                    </div>
                    <div class="activity-info">
                        ${item.course_code} • ${item.current_grade} → ${item.new_grade}
                    </div>
                    <div class="activity-meta">
                        <span>Req by: ${item.teacher_name}</span>
                        <span>${timeStr}</span>
                    </div>
                </div>
            `;
            activityList.appendChild(div);
        });
    }

    document.addEventListener('click', (e) => {
        if (!searchResults.contains(e.target) && e.target !== studentSearch) {
            searchResults.classList.add('hidden');
        }
    });
});
