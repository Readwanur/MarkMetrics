document.addEventListener('DOMContentLoaded', () => {
    // ─── DOM References ───
    const courseSelect = document.getElementById('course-select');
    const currentGradeDisplay = document.getElementById('current-grade');
    const submitBtn = document.getElementById('submit-update');
    const saveDraftBtn = document.getElementById('save-draft');
    const activityList = document.getElementById('activity-list');
    const pendingCount = document.getElementById('pending-count');
    const approvalRate = document.getElementById('approval-rate');
    const ongoingCount = document.getElementById('ongoing-count');
    const withdrawalCourseSelect = document.getElementById('withdrawal-course-select');
    const withdrawBtn = document.getElementById('withdraw-btn');
    const withdrawalCourseInfo = document.getElementById('withdrawal-course-info');
    const draftNotice = document.getElementById('draft-notice');

    let studentInfo = null;
    let allCourses = [];
    let ongoingCourses = [];

    // ─── Initialize ───
    init();

    async function init() {
        await loadStudentInfo();
        await loadCourses();
        await loadRegistryData();
        restoreDraft();
        setupTabs();
    }

    // ══════════════════════════════════════════
    //  DATA LOADING
    // ══════════════════════════════════════════

    async function loadStudentInfo() {
        try {
            const res = await fetch('api.php?action=get_my_info');
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            studentInfo = data;
            document.getElementById('student-name').textContent = data.name;
            document.getElementById('student-id-display').textContent = data.id;
        } catch (err) {
            console.error('Failed to load student info:', err);
            document.getElementById('student-name').textContent = 'Error loading';
            showToast('Failed to load student information', 'error');
        }
    }

    async function loadCourses() {
        try {
            // Fetch correction-eligible courses (current + previous semester only)
            const correctionRes = await fetch('api.php?action=get_my_courses&scope=correction');
            const correctionData = await correctionRes.json();
            if (correctionData.error) throw new Error(correctionData.error);

            allCourses = correctionData;
            populateCourseSelect(correctionData);

            // Fetch all courses for withdrawal (only ongoing ones are eligible)
            const allRes = await fetch('api.php?action=get_my_courses');
            const allData = await allRes.json();
            if (allData.error) throw new Error(allData.error);

            ongoingCourses = allData.filter(c => c.status === 'Ongoing');
            populateWithdrawalSelect(ongoingCourses);
        } catch (err) {
            console.error('Failed to load courses:', err);
            showToast('Failed to load courses', 'error');
        }
    }

    async function loadRegistryData() {
        try {
            const res = await fetch('api.php?action=get_registry_data');
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            pendingCount.textContent = data.stats.pending_requests;
            approvalRate.textContent = data.stats.approval_rate;
            ongoingCount.textContent = data.stats.ongoing_courses;

            displayActivity(data.activity, data.withdrawals);
        } catch (err) {
            console.error('Failed to load registry data:', err);
        }
    }

    // ══════════════════════════════════════════
    //  TAB NAVIGATION
    // ══════════════════════════════════════════

    function setupTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;

                // Update buttons
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Update panels
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
                document.getElementById(`panel-${tab}`).classList.add('active');
            });
        });
    }

    // ══════════════════════════════════════════
    //  GRADE CORRECTION FORM
    // ══════════════════════════════════════════

    function populateCourseSelect(courses) {
        courseSelect.innerHTML = '<option value="" disabled selected>Select a course</option>';

        // Group by semester
        const grouped = {};
        courses.forEach(c => {
            if (!grouped[c.semester_name]) grouped[c.semester_name] = [];
            grouped[c.semester_name].push(c);
        });

        Object.entries(grouped).forEach(([semester, semCourses]) => {
            const group = document.createElement('optgroup');
            group.label = semester;
            semCourses.forEach(course => {
                const opt = document.createElement('option');
                opt.value = course.course_code;
                opt.textContent = `${course.course_code}: ${course.course_name}`;
                opt.dataset.grade = course.current_grade || '--';
                group.appendChild(opt);
            });
            courseSelect.appendChild(group);
        });
    }

    courseSelect.addEventListener('change', (e) => {
        const courseCode = e.target.value;
        const course = allCourses.find(c => c.course_code === courseCode);
        if (course) {
            const grade = course.current_grade || '--';
            currentGradeDisplay.textContent = grade;

            // Add color styling based on grade
            currentGradeDisplay.className = 'value-display';
            if (grade.startsWith('A')) currentGradeDisplay.classList.add('grade-a');
            else if (grade.startsWith('B')) currentGradeDisplay.classList.add('grade-b');
            else if (grade.startsWith('C')) currentGradeDisplay.classList.add('grade-c');
            else if (grade === 'F') currentGradeDisplay.classList.add('grade-f');
        }
    });

    // Submit Grade Correction
    submitBtn.addEventListener('click', () => {
        const courseCode = courseSelect.value;
        const newGrade = document.getElementById('new-grade').value;
        const justification = document.getElementById('justification').value.trim();

        if (!courseCode) {
            showToast('Please select a course', 'error');
            courseSelect.focus();
            return;
        }
        if (!justification) {
            showToast('Please provide a justification', 'error');
            document.getElementById('justification').focus();
            return;
        }

        const currentGrade = currentGradeDisplay.textContent;

        if (currentGrade === newGrade) {
            showToast('New grade must be different from the current grade', 'error');
            return;
        }

        const course = allCourses.find(c => c.course_code === courseCode);
        const courseName = course ? course.course_name : courseCode;

        showConfirmModal(
            'Submit Grade Correction',
            `Are you sure you want to submit a grade correction request?`,
            `<div class="detail-row"><span>Course</span><strong>${courseName}</strong></div>
             <div class="detail-row"><span>Current Grade</span><strong>${currentGrade}</strong></div>
             <div class="detail-row"><span>Requested Grade</span><strong>${newGrade}</strong></div>`,
            () => submitGradeCorrection(courseCode, currentGrade, newGrade, justification)
        );
    });

    async function submitGradeCorrection(courseCode, currentGrade, newGrade, justification) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Submitting...';

        try {
            const res = await fetch('api.php?action=submit_request', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: studentInfo.id,
                    course_code: courseCode,
                    current_grade: currentGrade,
                    new_grade: newGrade,
                    justification: justification
                })
            });

            const data = await res.json();

            if (data.success) {
                showToast('Grade correction request submitted successfully!', 'success');
                clearForm();
                clearSavedDraft();
                await loadRegistryData();
            } else {
                showToast(data.error || 'Submission failed', 'error');
            }
        } catch (err) {
            showToast('Network error — please try again', 'error');
            console.error(err);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Submit Request';
        }
    }

    function clearForm() {
        courseSelect.selectedIndex = 0;
        currentGradeDisplay.textContent = '--';
        currentGradeDisplay.className = 'value-display';
        document.getElementById('new-grade').selectedIndex = 0;
        document.getElementById('justification').value = '';
    }

    // ══════════════════════════════════════════
    //  COURSE WITHDRAWAL
    // ══════════════════════════════════════════

    function populateWithdrawalSelect(courses) {
        withdrawalCourseSelect.innerHTML = '';

        if (courses.length === 0) {
            withdrawalCourseSelect.innerHTML = '<option value="" disabled selected>No ongoing courses available</option>';
            withdrawBtn.disabled = true;
            return;
        }

        withdrawalCourseSelect.innerHTML = '<option value="" disabled selected>Select a course to withdraw</option>';
        courses.forEach(course => {
            const opt = document.createElement('option');
            opt.value = course.enrollment_id;
            opt.textContent = `${course.course_code}: ${course.course_name} (${course.semester_name})`;
            withdrawalCourseSelect.appendChild(opt);
        });
    }

    withdrawalCourseSelect.addEventListener('change', (e) => {
        const enrollmentId = parseInt(e.target.value);
        const course = ongoingCourses.find(c => parseInt(c.enrollment_id) === enrollmentId);

        if (course) {
            document.getElementById('wci-name').textContent = `${course.course_code}: ${course.course_name}`;
            document.getElementById('wci-credits').textContent = `${course.credits} Credits`;
            document.getElementById('wci-semester').textContent = course.semester_name;
            withdrawalCourseInfo.classList.remove('hidden');
            withdrawBtn.disabled = false;
        } else {
            withdrawalCourseInfo.classList.add('hidden');
            withdrawBtn.disabled = true;
        }
    });

    withdrawBtn.addEventListener('click', () => {
        const enrollmentId = parseInt(withdrawalCourseSelect.value);
        const course = ongoingCourses.find(c => parseInt(c.enrollment_id) === enrollmentId);
        if (!course) return;

        const reason = document.getElementById('withdrawal-reason').value.trim();

        showConfirmModal(
            'Confirm Course Withdrawal',
            `This action is <strong>permanent</strong>. A grade of "W" will be recorded on your transcript.`,
            `<div class="detail-row"><span>Course</span><strong>${course.course_code}: ${course.course_name}</strong></div>
             <div class="detail-row"><span>Credits</span><strong>${course.credits}</strong></div>
             <div class="detail-row"><span>Semester</span><strong>${course.semester_name}</strong></div>`,
            () => processWithdrawal(enrollmentId, reason),
            'danger'
        );
    });

    async function processWithdrawal(enrollmentId, reason) {
        withdrawBtn.disabled = true;
        withdrawBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Processing...';

        try {
            const res = await fetch('api.php?action=withdraw_course', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    enrollment_id: enrollmentId,
                    reason: reason
                })
            });

            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                document.getElementById('withdrawal-reason').value = '';
                withdrawalCourseInfo.classList.add('hidden');

                // Refresh data
                await loadCourses();
                await loadRegistryData();
            } else {
                showToast(data.error || 'Withdrawal failed', 'error');
            }
        } catch (err) {
            showToast('Network error — please try again', 'error');
            console.error(err);
        } finally {
            withdrawBtn.disabled = false;
            withdrawBtn.innerHTML = '<i class="ph ph-sign-out"></i> Withdraw from Course';
        }
    }

    // ══════════════════════════════════════════
    //  SAVE DRAFT (localStorage)
    // ══════════════════════════════════════════

    saveDraftBtn.addEventListener('click', () => {
        const draft = {
            course_code: courseSelect.value,
            new_grade: document.getElementById('new-grade').value,
            justification: document.getElementById('justification').value,
            saved_at: new Date().toISOString()
        };
        localStorage.setItem('markmetrics_grade_draft', JSON.stringify(draft));
        showToast('Draft saved successfully', 'success');
        saveDraftBtn.innerHTML = '<i class="ph ph-check"></i> Saved!';
        setTimeout(() => {
            saveDraftBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Save Draft';
        }, 2000);
    });

    function restoreDraft() {
        const saved = localStorage.getItem('markmetrics_grade_draft');
        if (!saved) return;

        try {
            const draft = JSON.parse(saved);
            if (draft.course_code) {
                courseSelect.value = draft.course_code;
                courseSelect.dispatchEvent(new Event('change'));
            }
            if (draft.new_grade) {
                document.getElementById('new-grade').value = draft.new_grade;
            }
            if (draft.justification) {
                document.getElementById('justification').value = draft.justification;
            }

            // Show draft notice
            draftNotice.classList.remove('hidden');
        } catch (e) {
            console.error('Failed to restore draft:', e);
        }
    }

    function clearSavedDraft() {
        localStorage.removeItem('markmetrics_grade_draft');
        draftNotice.classList.add('hidden');
    }

    // Clear draft link
    const clearDraftLink = document.getElementById('clear-draft');
    if (clearDraftLink) {
        clearDraftLink.addEventListener('click', (e) => {
            e.preventDefault();
            clearSavedDraft();
            clearForm();
            showToast('Draft cleared', 'success');
        });
    }

    // ══════════════════════════════════════════
    //  ACTIVITY FEED
    // ══════════════════════════════════════════

    function displayActivity(corrections, withdrawals) {
        activityList.innerHTML = '';

        // Combine both into a single timeline
        const items = [];

        corrections.forEach(item => {
            items.push({
                type: 'correction',
                title: item.course_name,
                subtitle: `${item.course_code} • ${item.current_grade} → ${item.new_grade}`,
                status: item.status,
                meta: item.requester_name === 'Student Request' ? 'Self-requested' : `Req by: ${item.requester_name}`,
                date: new Date(item.created_at),
                icon: 'note-pencil'
            });
        });

        withdrawals.forEach(item => {
            items.push({
                type: 'withdrawal',
                title: 'Course Withdrawal',
                subtitle: item.description,
                status: 'Withdrawn',
                meta: '',
                date: new Date(item.created_at),
                icon: 'sign-out'
            });
        });

        // Sort by date descending
        items.sort((a, b) => b.date - a.date);

        if (items.length === 0) {
            activityList.innerHTML = `
                <div class="activity-empty">
                    <i class="ph ph-clipboard-text"></i>
                    <p>No activity yet</p>
                    <span>Your grade corrections and withdrawals will appear here</span>
                </div>
            `;
            return;
        }

        items.slice(0, 8).forEach(item => {
            const timeStr = item.date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const dateStr = item.date.toLocaleDateString();

            const statusClass = `status-${item.status.toLowerCase()}`;

            const div = document.createElement('div');
            div.className = `activity-item fade-in`;
            div.innerHTML = `
                <div class="activity-icon ${item.type === 'withdrawal' ? 'icon-withdrawal' : ''}">
                    <i class="ph ph-${item.icon}"></i>
                </div>
                <div class="activity-details">
                    <div class="activity-title">
                        <h4>${item.title}</h4>
                        <span class="status-badge ${statusClass}">${item.status}</span>
                    </div>
                    <div class="activity-info">${item.subtitle}</div>
                    <div class="activity-meta">
                        <span>${item.meta}</span>
                        <span>${dateStr} ${timeStr}</span>
                    </div>
                </div>
            `;
            activityList.appendChild(div);
        });
    }

    // ══════════════════════════════════════════
    //  TOAST NOTIFICATIONS
    // ══════════════════════════════════════════

    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icons = {
            success: 'check-circle',
            error: 'x-circle',
            info: 'info'
        };

        toast.innerHTML = `
            <i class="ph ph-${icons[type] || 'info'}"></i>
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="ph ph-x"></i>
            </button>
        `;

        container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => toast.classList.add('show'));

        // Auto-dismiss
        setTimeout(() => {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    // ══════════════════════════════════════════
    //  CONFIRMATION MODAL
    // ══════════════════════════════════════════

    let modalConfirmCallback = null;

    function showConfirmModal(title, message, details, onConfirm, variant = 'primary') {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-message').innerHTML = message;
        document.getElementById('modal-details').innerHTML = details;

        const confirmBtn = document.getElementById('modal-confirm');
        confirmBtn.className = `btn btn-${variant}`;
        confirmBtn.textContent = variant === 'danger' ? 'Confirm Withdrawal' : 'Confirm';

        modalConfirmCallback = onConfirm;
        document.getElementById('confirm-modal').classList.add('active');
    }

    document.getElementById('modal-cancel').addEventListener('click', () => {
        document.getElementById('confirm-modal').classList.remove('active');
        modalConfirmCallback = null;
    });

    document.getElementById('modal-confirm').addEventListener('click', () => {
        document.getElementById('confirm-modal').classList.remove('active');
        if (modalConfirmCallback) {
            modalConfirmCallback();
            modalConfirmCallback = null;
        }
    });

    // Close modal on overlay click
    document.getElementById('confirm-modal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            e.currentTarget.classList.remove('active');
            modalConfirmCallback = null;
        }
    });

    // Close modal on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('confirm-modal').classList.remove('active');
            modalConfirmCallback = null;
        }
    });
});
