document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Sidebar Dropdown Functionality
    const dropdowns = document.querySelectorAll(".dropdown");
    
    // Set initial state
    const submenus = document.querySelectorAll(".submenu");
    submenus.forEach(menu => {
        if (!menu.classList.contains("show")) {
            menu.style.display = "none";
        } else {
            menu.style.display = "block";
        }
    });

    dropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('a');
        if(trigger) {
            trigger.addEventListener("click", function(e) {
                // Only prevent default if it's the trigger for the dropdown itself (has a submenu next to it)
                const submenu = this.nextElementSibling;
                if(submenu && submenu.classList.contains("submenu")) {
                    e.preventDefault();
                    
                    // Remove active from all other items to prevent multiple orange markers
                    document.querySelectorAll(".menu > li").forEach(li => {
                        if (li !== dropdown) {
                            li.classList.remove("active");
                            // Also close other submenus if we want
                            const otherSub = li.querySelector(".submenu");
                            if (otherSub) {
                                otherSub.classList.remove("show");
                                otherSub.style.display = "none";
                            }
                        }
                    });
                    
                    // Toggle parent active state
                    dropdown.classList.toggle("active");
                    
                    // Toggle submenu
                    submenu.classList.toggle("show");
                    if (submenu.classList.contains("show")) {
                        submenu.style.display = "block";
                    } else {
                        submenu.style.display = "none";
                    }
                }
            });
        }
    });

    // 2. Accordion Logic for Withdraw Management
    const accordions = document.querySelectorAll(".accordion-item");
    accordions.forEach(item => {
        const header = item.querySelector(".accordion-header");
        if(header) {
            header.addEventListener("click", () => {
                // Close all others
                accordions.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove("active");
                        const otherContent = otherItem.querySelector(".accordion-content");
                        if(otherContent) otherContent.style.display = "none";
                    }
                });

                // Toggle current
                item.classList.toggle("active");
                const content = item.querySelector(".accordion-content");
                if (item.classList.contains("active")) {
                    content.style.display = "block";
                } else {
                    content.style.display = "none";
                }
            });
        }
    });

    // 3. Chart.js Initialization for Academic Performance
    // We only want to run this if the canvas elements exist on the page
    const velocityCanvas = document.getElementById('velocityChart');
    const aptitudeCanvas = document.getElementById('aptitudeChart');

    if (velocityCanvas && typeof Chart !== 'undefined') {
        new Chart(velocityCanvas, {
            type: 'line',
            data: {
                labels: ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [
                    {
                        label: 'Current Period',
                        data: [65, 80, 50, 75, 45, 80, 50, 75, 45, 75, 50],
                        borderColor: '#ff6b00',
                        tension: 0, // straight lines
                        borderWidth: 2,
                        pointRadius: 0
                    },
                    {
                        label: 'Class Average',
                        data: [45, 55, 75, 45, 75, 45, 75, 45, 70, 55, 75],
                        borderColor: '#3b82f6',
                        tension: 0,
                        borderWidth: 2,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { display: false, min: 0, max: 100 },
                    x: { 
                        grid: { display: false, color: '#2b2b36' },
                        ticks: { color: '#8f8f9d', font: { size: 10 } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { color: '#8f8f9d', boxWidth: 12, font: { size: 10 } }
                    }
                }
            }
        });
    }

    if (aptitudeCanvas && typeof Chart !== 'undefined') {
        new Chart(aptitudeCanvas, {
            type: 'radar',
            data: {
                labels: ['Calculus', 'Spkl', 'Oop', 'Math'],
                datasets: [{
                    label: 'Subject Aptitude',
                    data: [90, 85, 70, 80],
                    backgroundColor: 'rgba(255, 107, 0, 0.2)',
                    borderColor: '#ff6b00',
                    pointBackgroundColor: '#ff6b00',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#ff6b00'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: { color: '#2b2b36' },
                        grid: { color: '#2b2b36' },
                        pointLabels: { color: '#8f8f9d', font: { size: 10 } },
                        ticks: { display: false, min: 0, max: 100 }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Dynamic Course Name for Marks Entry Page
    const urlParams = new URLSearchParams(window.location.search);
    const courseCode = urlParams.get('course');
    const courseName = urlParams.get('name');
    
    if (courseCode && courseName) {
        // Look for the specific paragraph in marks-entry page to update
        const headerParas = document.querySelectorAll('.page-header p');
        headerParas.forEach(p => {
            if (p.textContent.includes('Data-intensive academic evaluation for Course:')) {
                // Decode URI component (though URLSearchParams handles basic decoding, spaces are +)
                const decodedName = courseName.replace(/\+/g, ' ');
                p.innerHTML = `Data-intensive academic evaluation for Course: ${decodedName} (${courseCode}). Last synced 4 minutes ago.`;
            }
        });
    }

});
