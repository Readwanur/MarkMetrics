document.addEventListener('DOMContentLoaded', () => {
    fetchDashboardData();
});


async function fetchDashboardData() {
    try {
        const response = await fetch('api.php');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();

        populateHeader(data);
        populateTopCards(data);
        populateChart(data);
        populatePendingExams(data);

    } catch (error) {
        console.error('Failed to fetch dashboard data:', error);

    }
}

function populateHeader(data) {
    document.getElementById('userName').textContent = data.user.name;
}

function populateTopCards(data) {

    document.getElementById('cumulativeGpa').textContent = data.gpa.cumulative.toFixed(2);
    document.getElementById('gpaChange').textContent = data.gpa.change;
    document.getElementById('gpaPercentile').textContent = data.gpa.percentile;

    document.getElementById('creditsEarned').textContent = data.credits.earned;
    document.getElementById('creditsProgressText').textContent = data.credits.progress + '%';

    setTimeout(() => {
        const progressBar = document.getElementById('creditsProgressBar');
        if (progressBar) {
            progressBar.style.width = data.credits.progress + '%';
        }
    }, 150);

    document.getElementById('semesterName').textContent = data.semester.name;
    document.getElementById('coursesEnrolled').textContent = data.semester.coursesEnrolled + ' Courses Enrolled';
    document.getElementById('nextExam').textContent = data.semester.nextExam;
}

function populateChart(data) {
    const barsContainer = document.getElementById('velocityBars');
    barsContainer.innerHTML = '';

    data.velocity.forEach((item, index) => {

        const majorPercentage = (item.majorGpa / 4.0) * 100;
        const overallPercentage = (item.overallGpa / 4.0) * 100;

        const barGroup = document.createElement('div');
        barGroup.className = 'bar-group';

        const wrapper = document.createElement('div');
        wrapper.className = 'bar-wrapper';

        const overallBar = document.createElement('div');
        overallBar.className = 'bar overall';
        overallBar.style.height = '0%';

        const majorBar = document.createElement('div');
        majorBar.className = 'bar major';
        majorBar.style.height = '0%';

        const label = document.createElement('span');
        label.className = 'bar-label';
        label.textContent = item.semester;

        wrapper.appendChild(overallBar);
        wrapper.appendChild(majorBar);
        barGroup.appendChild(wrapper);
        barGroup.appendChild(label);

        barsContainer.appendChild(barGroup);

        setTimeout(() => {
            overallBar.style.height = overallPercentage + '%';
            majorBar.style.height = majorPercentage + '%';
        }, 150 + (index * 100));
    });
}

function populatePendingExams(data) {
    const listContainer = document.getElementById('examList');
    listContainer.innerHTML = '';

    data.pendingExams.forEach(exam => {
        const item = document.createElement('div');
        item.className = 'exam-item';
        item.style.borderLeftColor = exam.color;

        item.innerHTML = `
            <div class="exam-course">
                <h4>${exam.course}</h4>
                <p>${exam.type}</p>
            </div>
            <div class="exam-datetime">
                <h4 style="color: ${exam.color}">${exam.date}</h4>
                <p>${exam.time}</p>
            </div>
        `;
        listContainer.appendChild(item);
    });
}
