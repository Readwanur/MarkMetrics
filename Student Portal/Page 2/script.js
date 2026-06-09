async function fetchProfile() {
    try {
        const response = await fetch('api.php?action=get_profile');
        const data = await response.json();

        if (data.error) {
            console.error(data.error);
            return;
        }

        document.getElementById('profile-name').textContent = data.name;
        document.getElementById('profile-major').textContent = data.major;
        document.getElementById('profile-cgpa').textContent = data.cumulative_gpa;
        document.getElementById('profile-year').textContent = data.academic_year;
        document.getElementById('profile-term').textContent = data.enrollment_term;
        document.getElementById('profile-major-gpa').textContent = data.major_gpa;

        document.getElementById('credits-earned').textContent = data.total_credits_earned;
        document.getElementById('credits-total').textContent = data.total_credits;
        document.getElementById('deans-list').textContent = data.deans_list;

        document.getElementById('major-comp').textContent = `${data.major_completion}%`;
        document.getElementById('gen-comp').textContent = `${data.general_electives}%`;

    } catch (error) {
        console.error('Error fetching profile:', error);
    }
}

async function fetchEnrollments() {
    try {
        const response = await fetch('api.php?action=get_enrollments');
        const data = await response.json();

        if (data.error) {
            console.error(data.error);
            return;
        }

        allEnrollmentsData = data;

        const container = document.getElementById('enrollments-list');
        container.innerHTML = '';

        data.forEach((term, index) => {
            const isRunning = term.status === 'RUNNING';
            const isExpanded = index === 0; 

            const card = document.createElement('div');
            card.className = `term-card ${isRunning ? 'running' : ''} ${isExpanded ? 'expanded' : ''}`;

            let coursesHtml = '';
            if (term.courses && term.courses.length > 0) {
                term.courses.forEach(course => {
                    coursesHtml += `
                        <tr>
                            <td class="course-code">${course.course_code}</td>
                            <td class="course-title">${course.course_name}</td>
                            <td>${course.credits}</td>
                            <td><span class="course-grade">${course.grade}</span></td>
                            <td>${course.points}</td>
                            <td class="course-remarks">${course.status === 'Running Course' ? 'Running Course' : ''}</td>
                        </tr>
                    `;
                });
            } else {
                coursesHtml = '<tr><td colspan="6" style="text-align: center;">No courses found for this term.</td></tr>';
            }

            card.innerHTML = `
                <div class="term-header">
                    <div class="term-info">
                        <div class="term-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div class="term-title">
                            <h3>${term.term} ${isRunning ? '<span class="badge-running">RUNNING</span>' : ''}</h3>
                            <div class="term-dates">${term.dates || ''}</div>
                        </div>
                    </div>
                    <div class="term-stats">
                        <div class="term-gpa">
                            <div class="label">TERM GPA</div>
                            <div class="value">${term.term_gpa}</div>
                        </div>
                        <div class="expand-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </div>
                    </div>
                </div>
                <div class="term-body">
                    <table class="course-table">
                        <thead>
                            <tr>
                                <th>CODE</th>
                                <th>COURSE TITLE</th>
                                <th>CREDITS</th>
                                <th>GRADE</th>
                                <th>POINTS</th>
                                <th style="text-align: right;">REMARKS</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${coursesHtml}
                        </tbody>
                    </table>
                </div>
            `;

            const header = card.querySelector('.term-header');
            header.addEventListener('click', () => {
                card.classList.toggle('expanded');
            });

            container.appendChild(card);
        });
        // Populate the GPA velocity bar chart from real data
        populateBarChart(data);

    } catch (error) {
        console.error('Error fetching enrollments:', error);
    }
}

function populateBarChart(enrollmentData) {
    const barChart = document.querySelector('.bar-chart');
    if (!barChart || !enrollmentData || enrollmentData.length === 0) return;

    barChart.innerHTML = '';

    // Reverse so oldest semester is on the left
    const reversed = [...enrollmentData].reverse();

    reversed.forEach((term, index) => {
        const gpa = parseFloat(term.term_gpa) || 0;
        const heightPercent = (gpa / 4.0) * 100;
        const isLatest = index === reversed.length - 1;

        // Create short label from term name (e.g., "Spring 2024" -> "S24")
        const parts = term.term.split(' ');
        let shortLabel = term.term;
        if (parts.length >= 2) {
            shortLabel = parts[0].charAt(0) + parts[1].slice(-2);
        }

        const bar = document.createElement('div');
        bar.className = `bar${isLatest ? ' highlight' : ''}`;
        bar.style.height = '0%';
        bar.innerHTML = `<span>${shortLabel}</span>`;
        barChart.appendChild(bar);

        // Animate bar height
        setTimeout(() => {
            bar.style.height = heightPercent + '%';
        }, 150 + (index * 100));
    });
}

let allEnrollmentsData = [];

document.addEventListener('DOMContentLoaded', () => {
    const downloadBtn = document.getElementById('download-transcript-btn');
    const modal = document.getElementById('transcript-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    const cancelBtn = document.getElementById('cancel-download-btn');
    const confirmBtn = document.getElementById('confirm-download-btn');
    const checkboxList = document.getElementById('semester-checkboxes');

    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            checkboxList.innerHTML = '';
            const terms = [...new Set(allEnrollmentsData.map(e => e.term))];

            terms.forEach(term => {
                const item = document.createElement('div');
                item.className = 'checkbox-item';
                item.innerHTML = `
                    <input type="checkbox" id="sem-${term.replace(/\s+/g, '-')}" value="${term}" checked>
                    <label for="sem-${term.replace(/\s+/g, '-')}">${term}</label>
                `;
                checkboxList.appendChild(item);
            });

            modal.classList.add('active');
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', () => modal.classList.remove('active'));
    if (cancelBtn) cancelBtn.addEventListener('click', () => modal.classList.remove('active'));

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            const selectedSemesters = Array.from(checkboxList.querySelectorAll('input:checked')).map(cb => cb.value);

            if (selectedSemesters.length === 0) {
                alert('Please select at least one semester.');
                return;
            }

            generatePDF(selectedSemesters);
            modal.classList.remove('active');
        });
    }

    fetchProfile();
    fetchEnrollments();
});

function generatePDF(selectedSemesters) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(22);
    doc.setTextColor(255, 138, 0);
    doc.text('MarkMetrics', 105, 20, { align: 'center' });

    doc.setFontSize(16);
    doc.setTextColor(0, 0, 0);
    doc.text('OFFICIAL ACADEMIC TRANSCRIPT', 105, 30, { align: 'center' });

    doc.setFontSize(11);
    doc.text(`Student Name: ${document.getElementById('profile-name').textContent}`, 20, 45);
    doc.text(`Major: ${document.getElementById('profile-major').textContent}`, 20, 52);
    doc.text(`CGPA: ${document.getElementById('profile-cgpa').textContent}`, 20, 59);
    doc.text(`Date of Issue: ${new Date().toLocaleDateString()}`, 140, 45);

    let yPos = 70;

    selectedSemesters.forEach(semName => {
        const termData = allEnrollmentsData.find(t => t.term === semName);
        if (!termData) return;

        doc.setFontSize(12);
        doc.setFont(undefined, 'bold');
        doc.text(`${termData.term} (GPA: ${termData.term_gpa})`, 20, yPos);
        yPos += 5;

        const tableData = termData.courses.map(c => [
            c.course_code,
            c.course_name,
            c.credits,
            c.grade,
            c.points
        ]);

        doc.autoTable({
            startY: yPos,
            head: [['Code', 'Course Title', 'Credits', 'Grade', 'Points']],
            body: tableData,
            theme: 'striped',
            headStyles: { fillColor: [255, 138, 0] },
            margin: { left: 20, right: 20 }
        });

        yPos = doc.lastAutoTable.finalY + 15;

        if (yPos > 250) {
            doc.addPage();
            yPos = 20;
        }
    });

    doc.save(`Transcript_${document.getElementById('profile-name').textContent.replace(/\s+/g, '_')}.pdf`);
}
