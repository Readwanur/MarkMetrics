<?php
// --- Ensure required DB objects exist ---

// Add is_read column to grade_correction_requests if missing
@mysqli_query($conn, "ALTER TABLE grade_correction_requests ADD COLUMN IF NOT EXISTS is_read TINYINT(1) NOT NULL DEFAULT 0");

// Create withdraw_requests table if missing
@mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS withdraw_requests (
        request_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        course_code VARCHAR(20) NOT NULL,
        semester_id INT NOT NULL,
        reason TEXT,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE,
        FOREIGN KEY (semester_id) REFERENCES semesters(semester_id)
    )
");

// Count GCR pending requests
$pending_gcr_count = 0;
$pending_gcr_q = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM grade_correction_requests gcr 
    JOIN courses c ON gcr.course_code = c.course_code 
    WHERE c.teacher_id = '$teacher_id' AND gcr.status = 'Pending' AND gcr.is_read = 0
");
if ($pending_gcr_q) {
    $pending_gcr_count = (int)(mysqli_fetch_assoc($pending_gcr_q)['total'] ?? 0);
}

// Count WR pending requests
$pending_wr_count = 0;
$pending_wr_q = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM withdraw_requests wr 
    JOIN courses c ON wr.course_code = c.course_code 
    WHERE c.teacher_id = '$teacher_id' AND wr.status = 'Pending' AND wr.is_read = 0
");
if ($pending_wr_q) {
    $pending_wr_count = (int)(mysqli_fetch_assoc($pending_wr_q)['total'] ?? 0);
}

$total_pending_actions = $pending_gcr_count + $pending_wr_count;

// Resolve links based on current folder location
$is_subpage = (strpos($_SERVER['SCRIPT_NAME'], '/pages/') !== false);
$gcr_link = $is_subpage ? 'grade-management.php' : 'pages/grade-management.php';
$wr_link = $is_subpage ? 'withdraw-request.php' : 'pages/withdraw-request.php';
?>


<!-- Global Notifications Dropdown Panel -->
<div id="globalNotificationDropdown" style="display: none; position: absolute; top: calc(100% + 10px); right: 0; width: 320px; background: rgba(26, 26, 36, 0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 10000; padding: 15px; animation: slideDown 0.2s cubic-bezier(0.4, 0, 0.2, 1); text-align: left;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px;">
        <span style="font-size: 14px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-bell text-orange" style="color: var(--primary-orange);"></i> Notifications
        </span>
        <span style="font-size: 11px; background: rgba(245, 130, 32, 0.1); color: var(--primary-orange); padding: 2px 8px; border-radius: 20px; font-weight: 600;">
            <?php echo $total_pending_actions; ?> Pending
        </span>
    </div>
    
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <?php if ($pending_gcr_count > 0): ?>
            <a href="<?php echo $gcr_link; ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='var(--primary-orange)';" onmouseout="this.style.background='rgba(255,255,255,0.02)'; this.style.borderColor='rgba(255,255,255,0.04)';">
                <span style="display: flex; align-items: center; gap: 10px; color: #fff; font-size: 13px;">
                    <i class="fa-solid fa-graduation-cap text-orange" style="color: var(--primary-orange);"></i> Grade Correction Requests
                </span>
                <span style="font-size: 11px; background: var(--color-red); color: #fff; padding: 1px 6px; border-radius: 10px; font-weight: 700;"><?php echo $pending_gcr_count; ?></span>
            </a>
        <?php endif; ?>
        
        <?php if ($pending_wr_count > 0): ?>
            <a href="<?php echo $wr_link; ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); text-decoration: none; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='var(--primary-orange)';" onmouseout="this.style.background='rgba(255,255,255,0.02)'; this.style.borderColor='rgba(255,255,255,0.04)';">
                <span style="display: flex; align-items: center; gap: 10px; color: #fff; font-size: 13px;">
                    <i class="fa-solid fa-file-signature text-blue" style="color: var(--color-blue);"></i> Withdrawal Requests
                </span>
                <span style="font-size: 11px; background: var(--color-red); color: #fff; padding: 1px 6px; border-radius: 10px; font-weight: 700;"><?php echo $pending_wr_count; ?></span>
            </a>
        <?php endif; ?>
        
        <?php if ($total_pending_actions === 0): ?>
            <div style="text-align: center; padding: 20px 0; color: var(--text-secondary); font-size: 13px;">
                <i class="fa-solid fa-circle-check" style="color: var(--color-green); font-size: 18px; margin-bottom: 8px; display: block;"></i>
                No pending requests to review!
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleNotiModal(event) {
        if (event) {
            event.stopPropagation();
        }
        const dropdown = document.getElementById('globalNotificationDropdown');
        if (dropdown.style.display === 'none' || dropdown.style.display === '') {
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    }

    // Auto-close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('globalNotificationDropdown');
        const bellBtn = document.getElementById('notiBellBtn');
        if (dropdown && dropdown.style.display === 'block') {
            if (!dropdown.contains(event.target) && (!bellBtn || !bellBtn.contains(event.target))) {
                dropdown.style.display = 'none';
            }
        }
    });
</script>

<style>
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
