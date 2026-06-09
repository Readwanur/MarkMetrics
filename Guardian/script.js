const ctx = document.getElementById('resultChart');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Fall 23', 'Spring 24', 'Fall 24', 'Spring 25'],
        datasets: [
            {
                label: 'GPA',
                data: [3.2, 2.8, 3.5, 3.9],
                borderColor: '#4da6ff',
                backgroundColor: '#4da6ff',
                tension: 0.4
            },
            {
                label: 'CGPA',
                data: [3.3, 3.1, 3.4, 3.5],
                borderColor: '#F58220',
                backgroundColor: '#F58220',
                tension: 0.4
            }
        ]
    },

    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: {
                    color: '#E5E2E1'
                }
            }
        },

        scales: {
            x: {
                ticks: {
                    color: '#E5E2E1'
                }
            },
            y: {
                ticks: {
                    color: '#E5E2E1'
                }
            }
        }
    }
});