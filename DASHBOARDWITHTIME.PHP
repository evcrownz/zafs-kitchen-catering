            <?php 
            session_start();
                require_once 'connection.php';
                require_once 'sendmail.php';
                
                // ✅ CHECK IF USER IS LOGGED IN
                    if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
                        // User is not logged in, redirect to auth.php
                        header('Location: auth.php');
                        exit();
                    }

                // Handle booking submission
                if ($_POST && isset($_POST['action']) && $_POST['action'] === 'book_event') {
                    header('Content-Type: application/json');
                    
                    try {
                        // Check if user is logged in
                        if (!isset($_SESSION['user_id'])) {
                            echo json_encode(['success' => false, 'message' => 'Please log in to make a booking.', 'redirect' => 'login.php']);
                            exit;
                        }
                        
                        $user_id = $_SESSION['user_id']; // Get user ID from session
                        
                        // Get and validate form data
                        $full_name = trim($_POST['full_name'] ?? '');
                        $contact_number = trim($_POST['contact_number'] ?? '');
                        $celebrant_name = trim($_POST['celebrant_name'] ?? '');
                        $guest_count = trim($_POST['guest_count'] ?? '');
                        $celebrant_age = trim($_POST['celebrant_age'] ?? '');
                        $food_package = trim($_POST['food_package'] ?? '');
                        $event_type = trim($_POST['event_type'] ?? '');
                        $location = trim($_POST['location'] ?? '');
                        $event_date = trim($_POST['event_date'] ?? '');
                        $start_time = trim($_POST['start_time'] ?? '');
                        $end_time = trim($_POST['end_time'] ?? '');
                        $event_theme = trim($_POST['event_theme'] ?? '');
                        $custom_theme = trim($_POST['custom_theme'] ?? '');
                        $theme_suggestions = trim($_POST['theme_suggestions'] ?? '');
                        $selected_menus = trim($_POST['selected_menus'] ?? '');
                        $total_price = floatval($_POST['total_price'] ?? '0');
                        
                        // Basic validation
                        if (empty($full_name) || empty($contact_number) || empty($celebrant_name) || 
                            empty($guest_count) || empty($food_package) || empty($event_type) || 
                            empty($location) || empty($event_date) || empty($start_time) || empty($end_time)) {
                            echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
                            exit;
                        }
                        
                        // Validate guest count
                        if (!is_numeric($guest_count) || $guest_count < 1) {
                            echo json_encode(['success' => false, 'message' => 'Please enter a valid number of guests.']);
                            exit;
                        }
                        
                        // Validate age if birthday event
                        if ($event_type === 'birthday' && (empty($celebrant_age) || !is_numeric($celebrant_age) || $celebrant_age < 1)) {
                            echo json_encode(['success' => false, 'message' => 'Please enter a valid age for the celebrant.']);
                            exit;
                        }
                        
                        // Force year to be 2025
                        $date_parts = explode('-', $event_date);
                        if (count($date_parts) === 3) {
                            $event_date = '2025-' . $date_parts[1] . '-' . $date_parts[2];
                        }
                        
                        // Validate date is in the future (at least 3 days from now)
                        $min_date = date('Y-m-d', strtotime('+3 days'));
                        if ($event_date < $min_date) {
                            echo json_encode(['success' => false, 'message' => 'Event date must be at least 3 days from today.']);
                            exit;
                        }
                        
                        // Validate time format and duration
                        if (strtotime($start_time) >= strtotime($end_time)) {
                            echo json_encode(['success' => false, 'message' => 'End time must be after start time.']);
                            exit;
                        }
                        
                        // Calculate duration in hours
                        $start_timestamp = strtotime("2000-01-01 $start_time");
                        $end_timestamp = strtotime("2000-01-01 $end_time");
                        $duration_hours = ($end_timestamp - $start_timestamp) / 3600;

                        if ($duration_hours < 4) {
                            echo json_encode(['success' => false, 'message' => 'Event duration must be at least 4 hours.']);
                            exit;
                        }

                        if ($duration_hours > 8) {
                            echo json_encode(['success' => false, 'message' => 'Event duration cannot exceed 8 hours.']);
                            exit;
                        }

                        // Check if date already has 3 bookings
                        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE event_date = ? AND booking_status != 'cancelled'");
                        $checkStmt->execute([$event_date]);
                        $result = $checkStmt->fetch();

                        if ($result['count'] >= 3) {
                            echo json_encode(['success' => false, 'message' => 'This date is fully booked. Maximum 3 events per day allowed.']);
                            exit;
                        }

                        // ✅ NEW: Check time overlap - maximum 2 events can overlap
                        $overlapStmt = $conn->prepare("SELECT COUNT(*) as overlap_count FROM bookings WHERE event_date = ? AND booking_status != 'cancelled' AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))");
                        $overlapStmt->execute([$event_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
                        $overlapResult = $overlapStmt->fetch();

                        if ($overlapResult['overlap_count'] >= 2) {
                            echo json_encode([
                                'success' => false, 
                                'message' => 'Time conflict: Maximum 2 events allowed at the same time. Please choose a different time slot.',
                                'clear_time' => true
                            ]);
                            exit;
                        }
                            
                        // Convert selected_menus to JSON format for JSONB column
                        $selected_menus_json = !empty($selected_menus) ? json_encode(explode(',', $selected_menus)) : null;
                        
                        // Insert booking with user_id and total_price
                        $insertStmt = $conn->prepare("INSERT INTO bookings (
                            user_id, full_name, contact_number, celebrant_name, guest_count, celebrant_age, 
                            food_package, event_type, location, event_date, start_time, end_time, 
                            event_theme, custom_theme, theme_suggestions, selected_menus, total_price, booking_status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");

                        $result = $insertStmt->execute([
                            $user_id,
                            $full_name, 
                            $contact_number, 
                            $celebrant_name, 
                            $guest_count, 
                            ($celebrant_age ?: null), 
                            $food_package, 
                            $event_type, 
                            $location, 
                            $event_date, 
                            $start_time, 
                            $end_time, 
                            $event_theme, 
                            $custom_theme, 
                            $theme_suggestions, 
                            $selected_menus_json,
                            $total_price,
                            'pending'
                        ]);

                        if ($result) {
                            $booking_id = $conn->lastInsertId();
                            error_log("New booking submitted - ID: $booking_id, Customer: $full_name, User ID: $user_id, Total: $total_price");
                            
                            echo json_encode([
                                'success' => true, 
                                'message' => 'Booking submitted successfully! Your booking details have been sent for admin approval.', 
                                'booking_id' => $booking_id,
                                'total_price' => $total_price
                            ]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
                        }
                                
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                    }
                    exit;
                }

                // Get user's own bookings only
                if (isset($_GET['action']) && $_GET['action'] === 'get_my_bookings') {
                    header('Content-Type: application/json');
                    
                    // Check if user is logged in
                    if (!isset($_SESSION['user_id'])) {
                        echo json_encode(['error' => 'Not logged in']);
                        exit;
                    }
                    
                    $user_id = $_SESSION['user_id'];
                    
$stmt = $conn->prepare("SELECT 
    id,
    full_name,
    contact_number,
    celebrant_name,
    guest_count,
    celebrant_age,
    event_type,
    location,
    event_date,
    start_time,
    end_time,
    food_package,
    event_theme,
    custom_theme,
    theme_suggestions,
    total_price,
    booking_status,
    payment_status,
    rejection_reason,
    approved_at,
    created_at,
    updated_at
    FROM bookings 
    WHERE user_id = ?
    ORDER BY event_date DESC, created_at DESC
");
                    $stmt->execute([$user_id]);
                    $bookings = $stmt->fetchAll();
                    
                    echo json_encode($bookings);
                    exit;
                }

                // Get all bookings for admin (add admin check)
                if (isset($_GET['action']) && $_GET['action'] === 'get_all_bookings') {
                    header('Content-Type: application/json');
                    
                    // Check if user is admin
                    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
                        echo json_encode(['error' => 'Admin access required']);
                        exit;
                    }
                    
                    $stmt = $conn->prepare("SELECT 
                        id,
                        full_name,
                        contact_number,
                        celebrant_name,
                        guest_count,
                        celebrant_age,
                        event_type,
                        location,
                        event_date,
                        start_time,
                        end_time,
                        food_package,
                        event_theme,
                        custom_theme,
                        theme_suggestions,
                        total_price,
                        booking_status,
                        payment_status,
                        rejection_reason,
                        created_at,
                        updated_at
                        FROM bookings 
                        WHERE user_id = ?
                        ORDER BY event_date DESC, created_at DESC
                    ");
                    $stmt->execute();
                    $bookings = $stmt->fetchAll();
                    
                    echo json_encode($bookings);
                    exit;
                }

                // Check conflict - ALLOW 2 OVERLAPPING EVENTS, BLOCK 3RD
                if (isset($_GET['action']) && $_GET['action'] === 'check_conflict') {
                    header('Content-Type: application/json');
                    
                    $event_date = $_GET['event_date'] ?? '';
                    $start_time = $_GET['start_time'] ?? '';
                    $end_time = $_GET['end_time'] ?? '';
                    
                    if (!$event_date || !$start_time || !$end_time) {
                        echo json_encode(['conflict' => false]);
                        exit;
                    }
                    
                    $date_parts = explode('-', $event_date);
                    if (count($date_parts) === 3) {
                        $event_date = '2025-' . $date_parts[1] . '-' . $date_parts[2];
                    }
                    
                    // ✅ CHANGED: Check if 2 or more events already overlap with this time
                    $stmt = $conn->prepare("SELECT COUNT(*) as count, STRING_AGG(CONCAT(start_time, ' - ', end_time), ', ') as existing_slots FROM bookings WHERE event_date = ? AND booking_status != 'cancelled' AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))");
                    $stmt->execute([$event_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
                    $result = $stmt->fetch();
                    
                    // ✅ CHANGED: Conflict only if 2 or more events already exist in this time slot
                    echo json_encode([
                        'conflict' => $result['count'] >= 2,
                        'existing_slots' => $result['existing_slots'] ?? ''
                    ]);
                    exit;
                }

                // Calendar data - updated to show user's own bookings vs others
                if (isset($_GET['action']) && $_GET['action'] === 'get_calendar_data') {
                    header('Content-Type: application/json');
                    
                    $month = $_GET['month'] ?? date('n');
                    $year = $_GET['year'] ?? 2025;
                    $current_user_id = $_SESSION['user_id'] ?? null;
                    
                    $stmt = $conn->prepare("SELECT 
                        event_date, 
                        start_time, 
                        end_time, 
                        event_type,
                        location,
                        full_name,
                        booking_status,
                        user_id,
                        total_price,
                        COUNT(*) as booking_count
                        FROM bookings 
                        WHERE EXTRACT(YEAR FROM event_date) = ? 
                        AND EXTRACT(MONTH FROM event_date) = ? 
                        AND booking_status != 'cancelled'
                        GROUP BY event_date, start_time, end_time, event_type, location, full_name, booking_status, user_id, total_price
                        ORDER BY event_date
                    ");
                    $stmt->execute([$year, $month]);
                    $results = $stmt->fetchAll();
                    
                    $calendar_data = [];
                    foreach ($results as $row) {
                        $date = $row['event_date'];
                        
                        $detailStmt = $conn->prepare("SELECT 
                            start_time, 
                            end_time, 
                            event_type,
                            location,
                            full_name,
                            booking_status,
                            user_id,
                            total_price
                            FROM bookings 
                            WHERE event_date = ? 
                            AND booking_status != 'cancelled'
                            ORDER BY start_time
                        ");
                        $detailStmt->execute([$date]);
                        $detailResults = $detailStmt->fetchAll();
                        
                        $bookings = [];
                        foreach ($detailResults as $booking) {
                            $bookings[] = [
                                'start_time' => $booking['start_time'],
                                'end_time' => $booking['end_time'],
                                'event_type' => $booking['event_type'],
                                'location' => $booking['location'],
                                'is_own_booking' => ($current_user_id && $booking['user_id'] == $current_user_id),
                                'full_name' => $booking['full_name'],
                                'booking_status' => $booking['booking_status'],
                                'total_price' => $booking['total_price']
                            ];
                        }
                        
                        $calendar_data[$date] = [
                            'count' => count($bookings),
                            'bookings' => $bookings,
                            'is_full' => count($bookings) >= 3
                        ];
                    }
                    
                    echo json_encode($calendar_data);
                    exit;
                }

                // Cancel booking - only user can cancel their own booking
                if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
                    header('Content-Type: application/json');
                    
                    if (!isset($_SESSION['user_id'])) {
                        echo json_encode(['success' => false, 'message' => 'Please log in to cancel booking.']);
                        exit;
                    }
                    
                    $booking_id = trim($_POST['booking_id'] ?? '');
                    $user_id = $_SESSION['user_id'];
                    
                    if (empty($booking_id)) {
                        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
                        exit;
                    }
                    
                    try {
                        // Check if booking belongs to user
                        $checkStmt = $conn->prepare("SELECT id, booking_status FROM bookings WHERE id = ? AND user_id = ?");
                        $checkStmt->execute([$booking_id, $user_id]);
                        $booking = $checkStmt->fetch();
                        
                        if (!$booking) {
                            echo json_encode(['success' => false, 'message' => 'Booking not found or access denied.']);
                            exit;
                        }
                        
                        if ($booking['booking_status'] === 'cancelled') {
                            echo json_encode(['success' => false, 'message' => 'Booking is already cancelled.']);
                            exit;
                        }
                        
                        // Update booking status to cancelled
                        $updateStmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
                        $result = $updateStmt->execute([$booking_id, $user_id]);
                        
                        if ($result) {
                            echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to cancel booking.']);
                        }
                        
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                    }
                    exit;
                }

// Admin booking rejection endpoint
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reject_booking') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $booking_id = trim($_POST['booking_id'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if (empty($booking_id)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
        exit;
    }
    
    try {
        // ✅ Get booking with user email
        $bookingStmt = $conn->prepare("
            SELECT b.*, u.email, u.name 
            FROM bookings b 
            JOIN usertable u ON b.user_id = u.id 
            WHERE b.id = ?
        ");
        $bookingStmt->execute([$booking_id]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        // Update booking
        $updateStmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', rejection_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $updateStmt->execute([$rejection_reason, $booking_id]);
        
        if ($result) {
            // ✅ Send email
            sendBookingRejectionEmail($booking, $rejection_reason);
            
            echo json_encode(['success' => true, 'message' => 'Booking rejected. Email sent to customer. ✅']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject booking.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}


// Admin booking rejection endpoint with EMAIL
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reject_booking') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $booking_id = trim($_POST['booking_id'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if (empty($booking_id)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
        exit;
    }
    
    try {
        // Get booking details with user info
        $bookingStmt = $conn->prepare("
            SELECT b.*, u.email, u.name 
            FROM bookings b 
            JOIN usertable u ON b.user_id = u.id 
            WHERE b.id = ?
        ");
        $bookingStmt->execute([$booking_id]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        // Update booking
        $updateStmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', rejection_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $updateStmt->execute([$rejection_reason, $booking_id]);
        
        if ($result) {
            // ✅ Send rejection email
            sendBookingRejectionEmail($booking, $rejection_reason);
            
            echo json_encode(['success' => true, 'message' => 'Booking rejected. Customer notified via email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject booking.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

                // ✅ Check event status with dual timers - MOVED TO SEPARATE BLOCK
                if (isset($_GET['action']) && $_GET['action'] === 'check_event_status') {
                    header('Content-Type: application/json');
                    
                    $booking_id = $_GET['booking_id'] ?? '';
                    
                    if (empty($booking_id)) {
                        echo json_encode(['error' => 'Booking ID required']);
                        exit;
                    }
                    
                    if (!isset($_SESSION['user_id'])) {
                        echo json_encode(['error' => 'Not logged in']);
                        exit;
                    }
                    
                    try {
                        $stmt = $conn->prepare("SELECT id, booking_status, payment_status, approved_at, event_date, start_time, end_time FROM bookings WHERE id = ? AND user_id = ?");
                        $stmt->execute([$booking_id, $_SESSION['user_id']]);
                        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$booking) {
                            echo json_encode(['error' => 'Booking not found']);
                            exit;
                        }
                        
                        // If already cancelled, return status
                        if ($booking['booking_status'] === 'cancelled') {
                            echo json_encode(['status' => 'cancelled', 'booking_status' => 'cancelled']);
                            exit;
                        }
                        
                        // Calculate event end datetime
                        $eventEndDatetime = $booking['event_date'] . ' ' . $booking['end_time'];
                        $eventEndTimestamp = strtotime($eventEndDatetime);
                        $currentTimestamp = time();
                        
                        // ✅ PRIORITY 1: Check if event has ended (cleanup)
                        if ($currentTimestamp > $eventEndTimestamp) {
                            $cancelStmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', rejection_reason = 'Event has ended', updated_at = NOW() WHERE id = ?");
                            $cancelStmt->execute([$booking_id]);
                            
                            error_log("Auto-cancelled booking #$booking_id - Event ended at $eventEndDatetime");
                            
                            echo json_encode([
                                'status' => 'auto_cancelled', 
                                'reason' => 'event_ended',
                                'message' => 'Event has ended'
                            ]);
                            exit;
                        }
                        
                        // ✅ PRIORITY 2: Check payment deadline (if approved and not paid)
                        if ($booking['booking_status'] === 'approved' && $booking['payment_status'] !== 'paid' && $booking['approved_at']) {
                            $approvedTimestamp = strtotime($booking['approved_at']);
                            $hoursSinceApproval = ($currentTimestamp - $approvedTimestamp) / 3600;
                            
                            // 20 hour payment deadline
                            if ($hoursSinceApproval >= 20) {
                                $cancelStmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', rejection_reason = 'Payment deadline expired (20 hours)', updated_at = NOW() WHERE id = ?");
                                $cancelStmt->execute([$booking_id]);
                                
                                error_log("Auto-cancelled booking #$booking_id - Payment deadline expired");
                                
                                echo json_encode([
                                    'status' => 'auto_cancelled',
                                    'reason' => 'payment_deadline',
                                    'message' => 'Payment deadline expired'
                                ]);
                                exit;
                            } else {
                                // Calculate remaining time for payment
                                $paymentDeadline = $approvedTimestamp + (20 * 3600);
                                $remainingSeconds = $paymentDeadline - $currentTimestamp;
                                
                                // Calculate event countdown
                                $eventStartDatetime = $booking['event_date'] . ' ' . $booking['start_time'];
                                $eventStartTimestamp = strtotime($eventStartDatetime);
                                $eventCountdown = $eventStartTimestamp - $currentTimestamp;
                                
                                echo json_encode([
                                    'status' => 'awaiting_payment',
                                    'payment_deadline_seconds' => max(0, $remainingSeconds),
                                    'event_start_seconds' => max(0, $eventCountdown),
                                    'event_end_seconds' => max(0, $eventEndTimestamp - $currentTimestamp)
                                ]);
                                exit;
                            }
                        }
                        
                        // Event is active and paid
                        $eventStartDatetime = $booking['event_date'] . ' ' . $booking['start_time'];
                        $eventStartTimestamp = strtotime($eventStartDatetime);
                        $eventCountdown = $eventStartTimestamp - $currentTimestamp;
                        
                        echo json_encode([
                            'status' => 'active',
                            'event_start_seconds' => max(0, $eventCountdown),
                            'event_end_seconds' => max(0, $eventEndTimestamp - $currentTimestamp),
                            'payment_status' => $booking['payment_status']
                        ]);
                        
                    } catch (Exception $e) {
                        error_log("Event status check error: " . $e->getMessage());
                        echo json_encode(['error' => $e->getMessage()]);
                    }
                    exit;
                }

                // ✅ Batch cleanup (optional - for cron job)
                if (isset($_GET['action']) && $_GET['action'] === 'batch_cancel_expired') {
                    header('Content-Type: application/json');
                    
                    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
                        echo json_encode(['error' => 'Admin access required']);
                        exit;
                    }
                    
                    try {
                        // Cancel ended events
                        $endedStmt = $conn->prepare("
                            UPDATE bookings 
                            SET booking_status = 'cancelled', 
                                rejection_reason = 'Event has ended',
                                updated_at = NOW()
                            WHERE booking_status != 'cancelled'
                            AND (event_date || ' ' || end_time)::timestamp < NOW()
                        ");
                        $endedStmt->execute();
                        $endedCount = $endedStmt->rowCount();
                        
                        // Cancel expired payment deadlines
                        $paymentStmt = $conn->prepare("
                            UPDATE bookings 
                            SET booking_status = 'cancelled',
                                rejection_reason = 'Payment deadline expired (20 hours)',
                                updated_at = NOW()
                            WHERE booking_status = 'approved'
                            AND payment_status != 'paid'
                            AND approved_at IS NOT NULL
                            AND approved_at < (NOW() - INTERVAL '20 hours')
                            AND (event_date || ' ' || end_time)::timestamp > NOW()
                        ");
                        $paymentStmt->execute();
                        $paymentCount = $paymentStmt->rowCount();
                        
                        error_log("Batch cancel: $endedCount events ended, $paymentCount payment deadlines expired");
                        
                        echo json_encode([
                            'success' => true,
                            'ended_events' => $endedCount,
                            'payment_expired' => $paymentCount
                        ]);
                        
                    } catch (Exception $e) {
                        error_log("Batch cancel error: " . $e->getMessage());
                        echo json_encode(['error' => $e->getMessage()]);
                    }
                    exit;
                }

            // Get booking statistics for dashboard
            if (isset($_GET['action']) && $_GET['action'] === 'get_booking_stats') {
                    
                    // Check if user is admin
                    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
                        echo json_encode(['success' => false, 'message' => 'Admin access required']);
                        exit;
                    }
                    
                    $booking_id = trim($_POST['booking_id'] ?? '');
                    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                    
                    if (empty($booking_id)) {
                        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
                        exit;
                    }
                    
                    try {
                        $updateStmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', rejection_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $result = $updateStmt->execute([$rejection_reason, $booking_id]);
                        
                        if ($result) {
                            echo json_encode(['success' => true, 'message' => 'Booking rejected successfully.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to reject booking.']);
                        }
                        
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                    }
                    exit;
                }

            // Get booking statistics for dashboard
            if (isset($_GET['action']) && $_GET['action'] === 'get_booking_stats') {
                header('Content-Type: application/json');
                
                $user_id = $_SESSION['user_id'] ?? null;
                
                if (!$user_id) {
                    echo json_encode(['error' => 'Not logged in']);
                    exit;
                }
                
                try {
                    // Get user's booking statistics
                    $statsStmt = $conn->prepare("SELECT 
                        COUNT(*) as total_bookings,
                        COUNT(CASE WHEN booking_status = 'pending' THEN 1 END) as pending_bookings,
                        COUNT(CASE WHEN booking_status = 'approved' THEN 1 END) as approved_bookings,
                        COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled_bookings,
                        COALESCE(SUM(CASE WHEN booking_status = 'approved' THEN total_price ELSE 0 END), 0) as total_spent,
                        COUNT(CASE WHEN event_date >= CURRENT_DATE AND booking_status != 'cancelled' THEN 1 END) as upcoming_events
                        FROM bookings 
                        WHERE user_id = ?
                    ");
                    $statsStmt->execute([$user_id]);
                    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'total_bookings' => (int)($stats['total_bookings'] ?? 0),
                        'pending_bookings' => (int)($stats['pending_bookings'] ?? 0),
                        'approved_bookings' => (int)($stats['approved_bookings'] ?? 0),
                        'cancelled_bookings' => (int)($stats['cancelled_bookings'] ?? 0),
                        'total_spent' => (float)($stats['total_spent'] ?? 0),
                        'upcoming_events' => (int)($stats['upcoming_events'] ?? 0)
                    ]);
                    
                } catch (Exception $e) {
                    error_log("Error getting booking stats: " . $e->getMessage());
                    echo json_encode(['error' => 'Failed to get statistics']);
                }
                exit;
            }


                // Delete booking - only user can delete their own booking (Fixed version)
                if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_booking') {
                    header('Content-Type: application/json');
                    
                    if (!isset($_SESSION['user_id'])) {
                        echo json_encode(['success' => false, 'message' => 'Please log in to delete booking.']);
                        exit;
                    }
                    
                    $booking_id = trim($_POST['booking_id'] ?? '');
                    $user_id = $_SESSION['user_id'];
                    
                    if (empty($booking_id)) {
                        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
                        exit;
                    }
                    
                    try {
                        // Debug logging
                        error_log("Delete attempt - Booking ID: $booking_id, User ID: $user_id");
                        
                        // Check if booking belongs to user and get booking details - FIXED: Use ? parameters
                        $checkStmt = $conn->prepare("SELECT id, booking_status, event_date, start_time, celebrant_name FROM bookings WHERE id = ? AND user_id = ?");
                        $checkStmt->execute([$booking_id, $user_id]);
                        $booking = $checkStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$booking) {
                            error_log("Booking not found - ID: $booking_id, User: $user_id");
                            echo json_encode(['success' => false, 'message' => 'Booking not found or access denied.']);
                            exit;
                        }
                        
                        error_log("Found booking - Status: " . $booking['booking_status']);
                        
                        // Allow deletion if booking is pending OR if approved but more than 24 hours away
                        if ($booking['booking_status'] === 'approved') {
                            $event_datetime = $booking['event_date'] . ' ' . $booking['start_time'];
                            $hours_until_event = (strtotime($event_datetime) - time()) / 3600;
                            
                            if ($hours_until_event <= 24 && $hours_until_event > 0) {
                                echo json_encode(['success' => false, 'message' => 'Cannot delete approved booking within 24 hours of the event. Please contact admin for assistance.']);
                                exit;
                            }
                        }
                        
                        // Allow deletion for pending bookings regardless of timing
                        if ($booking['booking_status'] === 'cancelled') {
                            echo json_encode(['success' => false, 'message' => 'This booking is already cancelled.']);
                            exit;
                        }
                        
                        // Delete the booking record completely - FIXED: Use ? parameters
                        $deleteStmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ?");
                        $result = $deleteStmt->execute([$booking_id, $user_id]);
                        
                        if ($result && $deleteStmt->rowCount() > 0) {
                            error_log("Booking successfully deleted - ID: $booking_id, User ID: $user_id, Celebrant: " . $booking['celebrant_name']);
                            echo json_encode(['success' => true, 'message' => 'Booking cancel successfully.']);
                        } else {
                            error_log("Delete failed - No rows affected");
                            echo json_encode(['success' => false, 'message' => 'Failed to delete booking or booking not found.']);
                        }
                        
                    } catch (PDOException $e) {
                        error_log("Database error deleting booking ID $booking_id: " . $e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
                    } catch (Exception $e) {
                        error_log("General error deleting booking ID $booking_id: " . $e->getMessage());
                        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
                    }
                    exit;
                }

                ?>

            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
            <link rel="icon" type="image/png" href="logo/logo.png">
            <title>Zaf's Kitchen Dashboard</title>

            <!-- Tailwind CSS -->
            <script src="https://cdn.tailwindcss.com"></script>

            <!-- Font Awesome -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" />

            <!-- Poppins Font -->
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
            <style>
            /* Global Styles */
            * {
                font-family: 'Poppins', sans-serif;
            }

            /* Navigation Styles */
            .hover-nav:hover {
                background-color: #DC2626!important;
                color: white !important;
            }

            .active-nav {
                background-color: #DC2626!important;
                color: white !important;
            }

            #price-summary {
                background: white !important;
                border: none !important;
                color: #DC2626!important;
                box-shadow: none !important;
            }

            #price-summary * {
                color: #DC2626!important;
            }

            #price-summary-step2 {
                background: white !important;
                border: none !important;
                color: #DC2626!important;
                box-shadow: none !important;
            }

            #price-summary-step2 * {
                color: #DC2626!important;
            }

            #price-summary-step3 {
                background: white !important;
                border: none !important;
                color: #DC2626!important;
                box-shadow: none !important;
            }

            #price-summary-step3 * {
                color: #DC2626!important;
            }

            /* Override any existing price calculator styles */
            .price-calculator {
                background: white !important;
                color: #DC2626!important;
                border: none !important;
                box-shadow: none !important;
            }

            .price-calculator * {
                color: #DC2626!important;
            }

            /* Enhanced Booking Card Styles */
            .booking-card-enhanced {
                transition: all 0.3s ease;
                border-radius: 16px;
                overflow: hidden;
                background: linear-gradient(145deg, #ffffff, #f8fafc);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            }

            .booking-card-enhanced:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            }

            .booking-price-tag {
                background: linear-gradient(135deg, #DC2626, #B91C1C);
                color: white;
                font-weight: bold;
                font-size: 1.1em;
                padding: 8px 16px;
                border-radius: 20px;
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            }

            .booking-status-indicator {
                width: 6px;
                min-height: 100%;
                position: absolute;
                left: 0;
                top: 0;
            }

            .status-approved .booking-status-indicator {
                background: linear-gradient(180deg, #10b981, #059669);
            }

            .status-pending .booking-status-indicator {
                background: linear-gradient(180deg, #f59e0b, #d97706);
            }

            .status-cancelled .booking-status-indicator {
                background: linear-gradient(180deg, #ef4444, #dc2626);
            }

            /* Loading Animation */
            .calculating {
                animation: pulse 2s ease-in-out infinite;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }

            /* Theme Button Styles */
            .theme-btn.selected {
                border-color: #DC2626!important;
                background-color: #FEF2F2;
                box-shadow: 0 0 0 2px #DC2626;
                transform: scale(1.05);
            }

            .theme-btn {
                transition: all 0.2s ease;
            }

            .theme-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
            }

            /* Form Styles */
            .form-input {
                transition: all 0.2s ease;
            }

            .form-input:focus {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
            }

            /* Custom Scrollbar */
            ::-webkit-scrollbar {
                width: 8px;
            }

            ::-webkit-scrollbar-track {
                background: #f1f5f9;
            }

            ::-webkit-scrollbar-thumb {
                background: #DC2626;
                border-radius: 4px;
            }

            ::-webkit-scrollbar-thumb:hover {
                background: #B91C1C;
            }

            /* Loading Animation */
            .loading-spinner {
                border: 2px solid #f3f4f6;
                border-top: 2px solid #DC2626;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                animation: spin 1s linear infinite;
                display: inline-block;
                margin-right: 8px;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Modal Animations */
            .modal-content {
                animation: slideIn 0.3s ease-out;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Calendar Styles */
            .calendar {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 2px;
                background-color: #e2e8f0;
                border-radius: 8px;
                overflow: hidden;
            }

            .calendar-day {
                background-color: white;
                min-height: 120px;
                padding: 8px;
                position: relative;
                cursor: pointer;
                transition: all 0.2s ease;
                border: 3px solid transparent;
            }

            .calendar-day:hover {
                transform: scale(1.02);
                z-index: 1;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }

            .calendar-day.other-month {
                background-color: #f1f5f9;
                color: #94a3b8;
            }

            .calendar-day.today {
                box-shadow: 0 0 0 2px #f59e0b;
            }

            /* Booking Status Colors */
            .calendar-day.no-bookings,
            .calendar-day.one-booking {
                background-color: #dcfce7;
                border-color: #22c55e;
            }

            .calendar-day.two-bookings {
                background-color: #fef3c7;
                border-color: #f59e0b;
            }

            .calendar-day.three-bookings {
                background-color: #fee2e2;
                border-color: #ef4444;
                cursor: not-allowed;
            }

            .calendar-day.unavailable {
                background-color: #fee2e2;
                border-color: #ef4444;
                cursor: not-allowed;
            }

            .booking-slot {
                font-size: 10px;
                padding: 2px 4px;
                margin: 1px 0;
                border-radius: 3px;
                background-color: #e2e8f0;
                color: #475569;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .booking-slot.own-booking {
                background-color: #dbeafe;
                color: #1e40af;
                border: 1px solid #3b82f6;
            }

            .calendar-header {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 2px;
                background-color: #DC2626;
                border-radius: 8px 8px 0 0;
                overflow: hidden;
            }

            .calendar-header-day {
                background-color: #DC2626;
                color: white;
                padding: 12px 8px;
                text-align: center;
                font-weight: 600;
                font-size: 14px;
            }

            .date-number {
                font-weight: 600;
                font-size: 16px;
                color: #1f2937;
            }

            .booking-count {
                position: absolute;
                top: 4px;
                right: 4px;
                background-color: #DC2626;
                color: white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 600;
            }

            /* Calendar Navigation */
            .calendar-nav {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
                padding: 0 8px;
            }

            .calendar-nav button {
                background-color: #DC2626;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .calendar-nav button:hover {
                background-color: #B91C1C;
                transform: translateY(-1px);
            }

            .calendar-nav button:disabled {
                background-color: #9ca3af;
                cursor: not-allowed;
                transform: none;
            }

            /* Status Badge Styles */
            .status-badge {
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 12px;
                text-transform: uppercase;
            }

            .status-pending {
                background-color: #fef3c7;
                color: #92400e;
                border: 1px solid #f59e0b;
            }

            .status-approved {
                background-color: #d1fae5;
                color: #065f46;
                border: 1px solid #10b981;
            }

            .status-cancelled {
                background-color: #fee2e2;
                color: #991b1b;
                border: 1px solid #ef4444;
            }

            /* Booking Card Styles */
            .booking-card {
                transition: all 0.2s ease;
            }

            .booking-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }

            /* Status Border Colors */
            .booking-card.status-approved {
                border-left: 4px solid #10b981 !important;
            }

            .booking-card.status-pending {
                border-left: 4px solid #f59e0b !important;
            }

            .booking-card.status-cancelled {
                border-left: 4px solid #ef4444 !important;
            }

            /* Past Events */
            .booking-card.past-event {
                opacity: 0.75;
            }

            .booking-card.status-cancelled.past-event {
                opacity: 0.6;
            }

            /* Step Progress Bar Styles */
            .step-progress {
                display: flex;
                justify-content: center;
                align-items: center;
                margin-bottom: 24px;
            }

            .step-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative;
            }

            .step-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                margin-bottom: 8px;
                transition: all 0.3s ease;
            }

            .step-line {
                height: 2px;
                width: 100px;
                margin: 0 16px;
                margin-bottom: 28px;
                transition: all 0.3s ease;
            }

            .step-text {
                font-size: 12px;
                font-weight: 600;
                text-align: center;
                transition: color 0.3s ease;
            }

            .step-item.active .step-circle {
                background-color: #DC2626;
                color: white;
                box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.2);
            }

            .step-item.completed .step-circle {
                background-color: #22c55e;
                color: white;
            }

            .step-item.inactive .step-circle {
                background-color: #e5e7eb;
                color: #9ca3af;
            }

            .step-line.active {
                background-color: #DC2626;
            }

            .step-line.completed {
                background-color: #22c55e;
            }

            .step-line.inactive {
                background-color: #e5e7eb;
            }

            .step-item.active .step-text {
                color: #DC2626;
            }

            .step-item.completed .step-text {
                color: #22c55e;
            }

            .step-item.inactive .step-text {
                color: #9ca3af;
            }

            /* Form Step Animations */
            .form-step {
                opacity: 0;
                transform: translateX(20px);
                transition: all 0.4s ease-in-out;
            }

            .form-step.active {
                opacity: 1;
                transform: translateX(0);
            }

            .form-step.slide-out-left {
                opacity: 0;
                transform: translateX(-20px);
            }

            .form-step.slide-out-right {
                opacity: 0;
                transform: translateX(20px);
            }

            /* Date Input Styling */
            .form-input[type="date"]::-webkit-calendar-picker-indicator {
                opacity: 0.7;
            }

            .form-input[type="date"]:disabled::-webkit-calendar-picker-indicator {
                opacity: 0.3;
            }

            #avatar-modal {
                backdrop-filter: blur(4px);
            }

            #avatar-grid img {
                aspect-ratio: 1/1;
                object-fit: cover;
            }

            .hidden {
                display: none !important;
            }
            
                /* Package Card Animations */
            .package-card {
                transition: all 0.3s ease;
            }

            .package-card:hover {
                box-shadow: 0 20px 25px -5px rgba(220, 38, 38, 0.2), 0 10px 10px -5px rgba(220, 38, 38, 0.1);
            }

            /* Menu Item Card Styles */
            .menu-item-card {
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: transform 0.3s ease;
            }

            .menu-item-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }

            .menu-item-card img {
                width: 100%;
                height: 120px;
                object-fit: cover;
            }

            .menu-item-card .content {
                padding: 12px;
            }

            .menu-item-card h5 {
                font-weight: 600;
                margin-bottom: 4px;
                color: #1f2937;
            }

            .menu-item-card p {
                font-size: 0.875rem;
                color: #6b7280;
                line-height: 1.4;
            }

            /* Modal Animation */
            #menu-modal.show {
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                .package-card {
                    transform: none !important;
                }
                
                .package-card:hover {
                    transform: none !important;
                }
            }

            /* Loading Screen Styles */
        #page-loader {
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        #page-loader.fade-out {
            opacity: 0;
            visibility: hidden;
        }

        .animate-spin {
            animation: spin 1s linear infinite;
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: .5;
            }
        }
            </style>
            </head>
            <body class="bg-gray-100">

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="lg:hidden fixed top-4 left-4 z-30 text-white p-2 rounded-lg shadow-lg" style="background-color:#DC2626;">
                <i class="fas fa-bars w-6 h-6"></i>
            </button>

            <!-- Backdrop -->
            <div id="backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-10 hidden lg:hidden"></div>

            <!-- Sidebar -->
            <aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 bg-gray-200 text-gray-800 flex flex-col justify-between rounded-r-xl z-20 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out"
                style="box-shadow: 6px 0 12px rgba(0, 0, 0, 0.2);">
                <div>
                    <div class="p-6 flex flex-col items-center border-b border-gray-300 shadow-md">
                        <img src="logo/logo-border.png" alt="Logo" class="w-26 h-24 rounded-full object-cover mb-1">
                        <h1 class="text-x6 font-bold text-center">Zaf's Kitchen</h1>
                    </div>

                    <nav class="flex-1 px-4 py-6 space-y-3">
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-calendar-plus text-[1.8rem]"></i>
                            <span class="font-semibold">Book Now</span>
                        </a>
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-list-check text-[1.8rem]"></i>
                            <span class="font-semibold">My Bookings</span>
                        </a>
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-utensils text-[1.8rem]"></i>
                            <span class="font-semibold">Menu Packages</span>
                        </a>
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-image text-[1.8rem]"></i>
                            <span class="font-semibold">Gallery</span>
                        </a>
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-calendar-check text-[1.8rem]"></i>
                            <span class="font-semibold">Available Schedule</span>
                        </a>
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-user-cog text-[1.8rem]"></i>
                            <span class="font-semibold">Profile Settings</span>
                        </a>
                        <a href="#" class="flex items-center gap-4 py-2 px-3 rounded hover-nav transition">
                            <i class="fas fa-circle-info text-[1.8rem]"></i>
                            <span class="font-semibold">About Us</span>
                        </a>
                    </nav>
                </div>
            </aside>

            <main class="lg:ml-64 p-6 lg:p-10 pt-16 lg:pt-10 min-h-screen">
                <!-- Dashboard -->
                <section id="section-dashboard">
                    <h2 class="text-3xl font-bold mb-2">Welcome to Zaf's Kitchen Dashboard</h2>
                    <div class="w-full h-0.5 bg-gray-400 mb-6"></div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-4" style="width: 380px;">
                            <img 
                                src="dashboard/calendar.png" 
                                alt="Book Now" 
                                class="w-[650px] h-[350px] object-cover rounded-[10px] mb-3 transform transition-transform duration-500 ease-in-out hover:scale-105 border border-gray-400"
                                style="box-shadow: 8px 8px 15px rgba(0,0,0,0.3);">
                        </div>
                    </div>
                </section>

                <!-- My Bookings Section -->
            <section id="section-mybookings" class="hidden">
                <h2 class="text-2xl font-bold mb-2">My Bookings</h2>
                <div class="w-full h-0.5 bg-gray-400 mb-6"></div>
                
                <!-- Filter/Status Legend -->
                <div class="bg-white p-4 rounded-lg shadow-lg border-2 border-gray-300 mb-6">
                    <div class="flex flex-wrap gap-4 items-center">
                        <span class="font-semibold text-gray-700">Status Legend:</span>
                        <div class="flex items-center gap-2">
                            <span class="status-badge status-pending">Pending</span>
                            <span class="text-sm text-gray-600">Waiting for admin approval</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="status-badge status-approved">Approved</span>
                            <span class="text-sm text-gray-600">Confirmed by admin</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="status-badge status-cancelled">Cancelled</span>
                            <span class="text-sm text-gray-600">Booking cancelled</span>
                        </div>
                    </div>
                </div>
                
                <!-- Bookings Container -->
                <div id="bookings-container" class="space-y-4">
                    <div class="text-center py-8">
                        <div class="loading-spinner mx-auto"></div>
                        <p class="text-gray-600 mt-2">Loading your bookings...</p>
                    </div>
                </div>
                
                <!-- Refresh Button -->
                <div class="mt-6 text-center">
                    <button id="refresh-bookings" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg shadow-md transition-colors">
                        <i class="fas fa-refresh mr-2"></i>
                        Refresh Status
                    </button>
                </div>
            </section>

            <!-- Delete Confirmation Modal -->
            <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-trash text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Remove</h3>
                        <p class="text-sm text-gray-500 mb-6">
                            Are you sure you want to cancel this booking? This action cannot be undone.
                        </p>
                        <div class="flex gap-3">
                            <button id="delete-modal-cancel" 
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg transition-colors">
                                Keep Booking
                            </button>
                            <button id="delete-modal-confirm" 
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                                Cancel Booking
                            </button>
                        </div>
                    </div>
                </div>
            </div>

                <!-- preview Modal -->
                <div id="preview-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
                    <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 max-h-[95vh] overflow-hidden">
                        <div class="bg-gradient-to-r from-[white] to-[white] p-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-xl font-bold text-red">Book Preview</h3>
                                <button id="close-preview-modal" class="text-white hover:text-gray-200 transition-colors">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div id="preview-content" class="p-8 overflow-y-auto max-h-[70vh] bg-white">
                            <!-- preview content will be inserted here -->
                        </div>
                        
                        <div class="bg-gray-50 p-6 border-t border-gray-200 flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-shield-alt text-[#DC2626] mr-2"></i>
                                Secure Booking • Zaf's Kitchen Service
                            </div>
                            <div class="flex gap-3">
                                <button id="print-preview" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors font-semibold flex items-center gap-2">
                                    <i class="fas fa-print"></i>
                                    Print Book Preview
                                </button>
                                <button id="close-preview-btn" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded-lg transition-colors font-semibold">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ENHANCED Book Now Section with 3 Steps -->
            <section id="section-book" class="hidden">
                <h2 class="text-2xl font-bold mb-2">Book Now</h2>
                <div class="w-full h-0.5 bg-gray-400 mb-4"></div>
                
                <!-- Progress Steps -->
                <div class="bg-white p-6 rounded-lg shadow-lg border-2 border-gray-300 mb-6">
                    <div class="step-progress">
                        <div id="step-1" class="step-item active">
                            <div class="step-circle">1</div>
                            <span class="step-text">Basic Info</span>
                        </div>
                        <div class="step-line inactive"></div>
                        <div id="step-2" class="step-item inactive">
                            <div class="step-circle">2</div>
                            <span class="step-text">Event Details</span>
                        </div>
                        <div class="step-line inactive"></div>
                        <div id="step-3" class="step-item inactive">
                            <div class="step-circle">3</div>
                            <span class="step-text">Theme & Menu</span>
                        </div>
                    </div>
                </div>

                <!-- Booking Form -->
                <div class="w-full">
                    <form id="booking-form" method="POST">
                        <input type="hidden" name="action" value="book_event">
                        <input type="hidden" id="total_price" name="total_price" value="0">
                        
                        <!-- Step 1: Basic Information -->
                        <div id="booking-step1" class="form-step active bg-white p-6 rounded-lg shadow-lg border-2 border-gray-300">
                            <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
                                <i class="fas fa-user-circle text-[#DC2626]"></i>
                                Basic Information
                            </h3>
                            <div class="space-y-4">
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-user mr-2 text-[#DC2626]"></i>
                                            Your Full Name *
                                        </label>
                                        <input id="fullname" name="full_name" type="text" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" 
                                            placeholder="Enter your full name" required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-phone mr-2 text-[#DC2626]"></i>
                                            Contact Number *
                                        </label>
                                        <input id="contact" name="contact_number" type="tel" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" 
                                            placeholder="e.g. +63 912 345 6789" required>
                                    </div>
                                </div>
                                
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-star mr-2 text-[#DC2626]"></i>
                                            Celebrant's Name *
                                        </label>
                                        <input id="celebrant-name" name="celebrant_name" type="text" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" 
                                            placeholder="Name of the person being celebrated" required>
                                        <p class="text-xs text-gray-500 mt-1">For corporate events, you can put company name</p>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-users mr-2 text-[#DC2626]"></i>
                                            Number of Guests *
                                        </label>
                                        <select id="guest-count" name="guest_count" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" 
                                            required>
                                            <option value="">Select number of guests</option>
                                            <option value="30">30 Guests</option>
                                            <option value="40">40 Guests</option>
                                            <option value="50">50 Guests</option>
                                            <option value="60">60 Guests</option>
                                            <option value="70">70 Guests</option>
                                            <option value="80">80 Guests</option>
                                            <option value="90">90 Guests</option>
                                            <option value="100">100 Guests</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Choose from available package sizes</p>
                                    </div>
                                </div>
                                
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-utensils mr-2 text-[#DC2626]"></i>
                                            Food Package *
                                        </label>
                                        <select id="package" name="food_package" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" required>
                                            <option value="">Select a package</option>

                                                <optgroup label="🎂 Birthday/Event Packages">
                                                    <option value="silver" data-price="767">Silver Package - ₱767/person (₱23K-50K)</option>
                                                    <option value="gold" data-price="1367">Gold Package - ₱1,367/person (₱41K-69K)</option>
                                                    <option value="platinum" data-price="1600">Platinum Package - ₱1,600/person (₱48K-82K)</option>
                                                    <option value="diamond" data-price="2233">Diamond Package - ₱2,233/person (₱67K-97K)</option>
                                                </optgroup>

                                                <optgroup label="💍 Wedding Packages">
                                                    <option value="basic_wedding" data-price="1400">Basic Wedding - ₱1,400/person (₱42K-75K)</option>
                                                    <option value="premium_wedding" data-price="2600">Premium Wedding - ₱2,600/person (₱130K-165K)</option>
                                                </optgroup>

                                                <optgroup label="👗 Debut Packages">
                                                    <option value="silver_debut" data-price="800">Silver Debut - ₱800/person (₱24K-52K)</option>
                                                    <option value="gold_debut" data-price="1433">Gold Debut - ₱1,433/person (₱43K-72K)</option>
                                                    <option value="platinum_debut" data-price="1800">Platinum Debut - ₱1,800/person (₱54K-86K)</option>
                                                </optgroup>

                                                <optgroup label="🏢 Corporate Packages">
                                                    <option value="silver_corporate" data-price="833">Silver Corporate - ₱833/person (₱25K-50K)</option>
                                                    <option value="gold_corporate" data-price="1467">Gold Corporate - ₱1,467/person (₱44K-69K)</option>
                                                    <option value="platinum_corporate" data-price="1667">Platinum Corporate - ₱1,667/person (₱50K-80K)</option>
                                                </optgroup>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-calendar-day mr-2 text-[#DC2626]"></i>
                                            Type of Event *
                                        </label>
                                        <select id="eventtype" name="event_type" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" required>
                                            <option value="">Select event type</option>
                                            <option value="birthday">Birthday Party</option>
                                            <option value="wedding">Wedding Reception</option>
                                            <option value="corporate">Corporate Event</option>
                                            <option value="graduation">Graduation Party</option>
                                            <option value="anniversary">Anniversary</option>
                                            <option value="debut">Debut/18th Birthday</option>
                                            <option value="baptismal">Baptismal</option>
                                            <option value="funeral">Funeral Service</option>
                                            <option value="others">Others</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Birthday Age Field -->
                                <div id="age-field" class="hidden">
                                    <label class="block font-semibold mb-1 text-gray-700">
                                        <i class="fas fa-birthday-cake mr-2 text-[#DC2626]"></i>
                                        Celebrant's Age *
                                    </label>
                                    <input id="celebrant-age" name="celebrant_age" type="number" min="1" max="150"
                                        class="form-input w-full md:w-32 border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" 
                                        placeholder="Age">
                                </div>
                                
                                <!-- Integrated Price Calculator -->
                                <div id="price-summary" class="bg-gradient-to-r from-[#DC2626] to-[#d14b1f] text-white p-4 rounded-lg shadow-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm opacity-90 mb-1">
                                                <i class="fas fa-calculator mr-1"></i>
                                                Estimated Cost
                                            </div>
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-sm">
                                                    <span>Base Package:</span>
                                                    <span id="base-price">₱0.00</span>
                                                </div>
                                                <div class="flex justify-between text-sm" id="additional-items-container" style="display: none;">
                                                    <span>Additional Items:</span>
                                                    <span id="additional-price">₱0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold" id="total-display">₱0.00</div>
                                            <div class="text-xs opacity-90">
                                                for <span id="guest-display">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="button" id="next-step1" 
                                        class="text-white px-8 py-3 rounded-lg shadow-md hover:opacity-90 transition-all transform hover:scale-105 font-semibold" 
                                        style="background-color:#DC2626;">
                                        Next Step <i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Event Details -->
                        <div id="booking-step2" class="form-step bg-white p-6 rounded-lg shadow-lg border-2 border-gray-300 hidden">
                            <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
                                <i class="fas fa-calendar-alt text-[#DC2626]"></i>
                                Event Schedule & Details
                            </h3>
                            <div class="space-y-6">
                                <div class="grid md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-calendar mr-2 text-[#DC2626]"></i>
                                            Event Date *
                                        </label>
                                        <input id="event-date" name="event_date" type="date" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" required>
                                        <p class="text-xs text-gray-500 mt-1">Must be at least 3 days from today</p>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-clock mr-2 text-[#DC2626]"></i>
                                            Start Time *
                                        </label>
                                        <input id="start-time" name="start_time" type="time" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" required>
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-gray-700">
                                            <i class="fas fa-clock mr-2 text-[#DC2626]"></i>
                                            End Time *
                                        </label>
                                        <input id="end-time" name="end_time" type="time" 
                                            class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" required>
                                        <p class="text-xs text-gray-500 mt-1">Duration: 4-8 hours</p>
                                    </div>
                                </div>
                                
                                <!-- Location Input -->
                                <div>
                                    <label for="location" class="block font-semibold mb-1 text-gray-700">
                                        <i class="fas fa-map-marker-alt mr-2 text-[#DC2626]"></i>
                                        Event Location *
                                    </label>
                                    <input type="text" id="location" name="location" required
                                        placeholder="Enter full event address (e.g., 123 Main St, City)"
                                        class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black" />
                                    <p class="text-xs text-gray-500 mt-1">Provide the exact address of the event location.</p>
                                </div>
                                
                                <!-- Time Conflict Warning -->
                                <div id="time-conflict-warning" class="hidden bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                        <span class="text-red-700 font-semibold">Time Conflict Detected</span>
                                    </div>
                                    <p class="text-red-600 mt-1 text-sm" id="conflict-details"></p>
                                </div>

                                <!-- Event Summary Preview -->
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <h4 class="font-semibold text-blue-800 mb-2 flex items-center gap-2">
                                        <i class="fas fa-eye"></i>
                                        Event Preview
                                    </h4>
                                    <div id="event-preview" class="text-sm text-blue-700">
                                        <p>Fill in the details above to see your event preview</p>
                                    </div>
                                </div>

                                <!-- Price Summary for Step 2 -->
                                <div id="price-summary-step2" class="bg-white border-2 border-gray-300 text-gray-800 p-4 rounded-lg shadow-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm text-gray-600 mb-1">
                                                <i class="fas fa-calculator mr-1 text-[#DC2626]"></i>
                                                Current Estimate
                                            </div>
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-sm">
                                                    <span>Base Package:</span>
                                                    <span id="base-price-step2">₱0.00</span>
                                                </div>
                                                <div class="flex justify-between text-sm" id="additional-items-container-step2" style="display: none;">
                                                    <span>Additional Items:</span>
                                                    <span id="additional-price-step2">₱0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-[#DC2626]" id="total-display-step2">₱0.00</div>
                                            <div class="text-xs text-gray-600">
                                                for <span id="guest-display-step2">0</span> guests
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between">
                                    <button type="button" id="back-step2" 
                                        class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg shadow-md hover:bg-gray-400 transition-colors font-semibold">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <button type="button" id="next-step2" 
                                        class="text-white px-8 py-3 rounded-lg shadow-md hover:opacity-90 transition-all transform hover:scale-105 font-semibold" 
                                        style="background-color:#DC2626;">
                                        Next Step <i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Theme & Menu Selection -->
                        <div id="booking-step3" class="form-step bg-white p-6 rounded-lg shadow-lg border-2 border-gray-300 hidden">
                            <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
                                <i class="fas fa-palette text-[#DC2626]"></i>
                                Theme & Menu Customization
                            </h3>
                            <div class="space-y-6">
                                <!-- Theme Selection -->
                                <div>
                                    <label class="block font-semibold mb-3 text-gray-700">
                                        <i class="fas fa-paint-brush mr-2 text-[#DC2626]"></i>
                                        Choose Your Event Theme
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-3">
                                        <button type="button" class="theme-btn p-4 border-2 border-gray-300 rounded-lg hover:border-[#DC2626] focus:border-[#DC2626] transition-all" data-theme="elegant">
                                            <i class="fas fa-crown text-3xl mb-2" style="color:#DC2626;"></i>
                                            <div class="font-semibold text-sm">Elegant</div>
                                            <div class="text-xs text-gray-500">Classic & Sophisticated</div>
                                            <input type="radio" name="event_theme" value="elegant" class="hidden">
                                        </button>
                                        <button type="button" class="theme-btn p-4 border-2 border-gray-300 rounded-lg hover:border-[#DC2626] focus:border-[#DC2626] transition-all" data-theme="rustic">
                                            <i class="fas fa-leaf text-3xl mb-2" style="color:#DC2626;"></i>
                                            <div class="font-semibold text-sm">Rustic</div>
                                            <div class="text-xs text-gray-500">Natural & Cozy</div>
                                            <input type="radio" name="event_theme" value="rustic" class="hidden">
                                        </button>
                                        <button type="button" class="theme-btn p-4 border-2 border-gray-300 rounded-lg hover:border-[#DC2626] focus:border-[#DC2626] transition-all" data-theme="modern">
                                            <i class="fas fa-star text-3xl mb-2" style="color:#DC2626;"></i>
                                            <div class="font-semibold text-sm">Modern</div>
                                            <div class="text-xs text-gray-500">Clean & Minimalist</div>
                                            <input type="radio" name="event_theme" value="modern" class="hidden">
                                        </button>
                                        <button type="button" class="theme-btn p-4 border-2 border-gray-300 rounded-lg hover:border-[#DC2626] focus:border-[#DC2626] transition-all" data-theme="tropical">
                                            <i class="fas fa-umbrella-beach text-3xl mb-2" style="color:#DC2626;"></i>
                                            <div class="font-semibold text-sm">Tropical</div>
                                            <div class="text-xs text-gray-500">Bright & Colorful</div>
                                            <input type="radio" name="event_theme" value="tropical" class="hidden">
                                        </button>
                                        <button type="button" class="theme-btn p-4 border-2 border-gray-300 rounded-lg hover:border-[#DC2626] focus:border-[#DC2626] transition-all" data-theme="vintage">
                                            <i class="fas fa-camera-retro text-3xl mb-2" style="color:#DC2626;"></i>
                                            <div class="font-semibold text-sm">Vintage</div>
                                            <div class="text-xs text-gray-500">Retro & Classic</div>
                                            <input type="radio" name="event_theme" value="vintage" class="hidden">
                                        </button>
                                        <button type="button" class="theme-btn p-4 border-2 border-gray-300 rounded-lg hover:border-[#DC2626] focus:border-[#DC2626] transition-all" data-theme="custom">
                                            <i class="fas fa-pencil-alt text-3xl mb-2" style="color:#DC2626;"></i>
                                            <div class="font-semibold text-sm">Custom</div>
                                            <div class="text-xs text-gray-500">Your Own Style</div>
                                            <input type="radio" name="event_theme" value="custom" class="hidden">
                                        </button>
                                    </div>
                                    
                                    <input id="custom-theme" name="custom_theme" type="text" 
                                        class="w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black hidden mb-3" 
                                        placeholder="Describe your custom theme">
                                </div>

                                <!-- Theme Suggestions -->
                                <div>
                                    <label class="block font-semibold mb-2 text-gray-700">
                                        <i class="fas fa-lightbulb mr-2 text-[#DC2626]"></i>
                                        Additional Theme Suggestions or Special Requests
                                    </label>
                                    <textarea id="theme-suggestions" name="theme_suggestions" rows="3"
                                        class="form-input w-full border-2 border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#DC2626] focus:border-[#DC2626] text-black"
                                        placeholder="Tell us about any specific decorations, colors, or special touches..."></textarea>
                                </div>

                                <!-- Menu Selection with Pricing -->
                                <div>
                                    <label class="block font-semibold mb-3 text-gray-700">
                                        <i class="fas fa-utensils mr-2 text-[#DC2626]"></i>
                                        Additional Menu Items (Optional)
                                    </label>
                                    <div class="border-2 border-gray-300 rounded-lg p-4">
                                        <p class="text-sm text-gray-600 mb-4">Add extra items to your package for additional cost:</p>
                                        <div class="grid md:grid-cols-3 gap-6 text-sm">
                                            <div>
                                                <div class="font-semibold text-[#DC2626] mb-3 text-base border-b pb-2">Main Dishes</div>
                                                <div class="space-y-2">
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_main[]" value="lechon_kawali" data-price="50" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Lechon Kawali</span>
                                                        <span class="text-[#DC2626] font-medium">+₱50</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_main[]" value="chicken_adobo" data-price="30" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Chicken Adobo</span>
                                                        <span class="text-[#DC2626] font-medium">+₱30</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_main[]" value="beef_caldereta" data-price="75" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Beef Caldereta</span>
                                                        <span class="text-[#DC2626] font-medium">+₱75</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_main[]" value="sweet_sour_fish" data-price="60" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Sweet & Sour Fish</span>
                                                        <span class="text-[#DC2626] font-medium">+₱60</span>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <div class="font-semibold text-[#DC2626] mb-3 text-base border-b pb-2">Side Dishes</div>
                                                <div class="space-y-2">
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_side[]" value="pancit_canton" data-price="25" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Pancit Canton</span>
                                                        <span class="text-[#DC2626] font-medium">+₱25</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_side[]" value="fried_rice" data-price="20" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Fried Rice</span>
                                                        <span class="text-[#DC2626] font-medium">+₱20</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_side[]" value="lumpiang_shanghai" data-price="35" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Lumpiang Shanghai</span>
                                                        <span class="text-[#DC2626] font-medium">+₱35</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_side[]" value="mixed_vegetables" data-price="15" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Mixed Vegetables</span>
                                                        <span class="text-[#DC2626] font-medium">+₱15</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div>
                                                <div class="font-semibold text-[#DC2626] mb-3 text-base border-b pb-2">Desserts</div>
                                                <div class="space-y-2">
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_dessert[]" value="leche_flan" data-price="40" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Leche Flan</span>
                                                        <span class="text-[#DC2626] font-medium">+₱2240</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_dessert[]" value="halo_halo" data-price="45" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Halo-Halo</span>
                                                        <span class="text-[#DC2626] font-medium">+₱45</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_dessert[]" value="buko_pie" data-price="55" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Buko Pie</span>
                                                        <span class="text-[#DC2626] font-medium">+₱55</span>
                                                    </label>
                                                    <label class="flex items-center hover:bg-gray-50 p-2 rounded transition-colors">
                                                        <input type="checkbox" name="menu_dessert[]" value="ice_cream" data-price="30" class="mr-3 text-[#DC2626] w-4 h-4">
                                                        <span class="flex-1">Ice Cream</span>
                                                        <span class="text-[#DC2626] font-medium">+₱30</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-3">* Additional menu prices are per person and will be added to your base package cost.</p>
                                    </div>
                                </div>

                                <!-- Final Price Summary for Step 3 -->
                                <div id="price-summary-step3" class="bg-white border-2 border-gray-300 text-gray-800 p-4 rounded-lg shadow-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm text-gray-600 mb-1">
                                                <i class="fas fa-calculator mr-1 text-[#DC2626]"></i>
                                                Final Estimate
                                            </div>
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-sm">
                                                    <span>Base Package:</span>
                                                    <span id="base-price-step3">₱0.00</span>
                                                </div>
                                                <div class="flex justify-between text-sm" id="additional-items-container-step3" style="display: none;">
                                                    <span>Additional Items:</span>
                                                    <span id="additional-price-step3">₱0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-gray-800" id="total-display-step3">₱0.00</div>
                                            <div class="text-xs text-gray-600">
                                                for <span id="guest-display-step3">0</span> guests
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-gray-300">
                                        <div class="text-xs text-center text-gray-600">
                                            <i class="fas fa-info-circle mr-1 text-gray-600"></i>
                                            Final price may vary based on specific requirements and location
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between">
                                    <button type="button" id="back-step3" 
                                        class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg shadow-md hover:bg-gray-400 transition-colors font-semibold">
                                        <i class="fas fa-arrow-left mr-2"></i>Back
                                    </button>
                                    <button type="submit" id="submit-booking" 
                                        class="text-white px-8 py-3 rounded-lg shadow-md hover:opacity-90 transition-all transform hover:scale-105 font-semibold text-lg" 
                                        style="background-color:#DC2626;">
                                        <i class="fas fa-paper-plane mr-2"></i>Submit Booking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

                <!-- Available Schedule Section with Calendar -->
                <section id="section-schedule" class="hidden">
                    <h2 class="text-2xl font-bold mb-2">Available Schedule</h2>
                    <div class="w-full h-0.5 bg-gray-400 mb-6"></div>
                    
                    <!-- Calendar Container -->
                    <div class="bg-white rounded-lg shadow-lg border-2 border-gray-300 p-6">
                        <!-- Calendar Navigation -->
                        <div class="calendar-nav">
                            <button id="prev-month" class="flex items-center gap-2">
                                <i class="fas fa-chevron-left"></i>
                                Previous
                            </button>
                            
                            <h3 id="calendar-title" class="text-xl font-bold text-gray-800">January 2025</h3>
                            
                            <button id="next-month" class="flex items-center gap-2">
                                Next
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <!-- Calendar Legend -->
                        <div class="mb-4 flex flex-wrap gap-4 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-green-200 border-2 border-green-500 rounded"></div>
                                <span>Available (0-1 bookings)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-yellow-200 border-2 border-yellow-500 rounded"></div>
                                <span>Busy (2 bookings)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-red-200 border-2 border-red-500 rounded"></div>
                                <span>Fully Booked (3 bookings)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-blue-200 border-2 border-blue-700 rounded"></div>
                                <span>Your Bookings</span>
                            </div>
                        </div>
                        
                        <!-- Calendar Header -->
                        <div class="calendar-header">
                            <div class="calendar-header-day">Sunday</div>
                            <div class="calendar-header-day">Monday</div>
                            <div class="calendar-header-day">Tuesday</div>
                            <div class="calendar-header-day">Wednesday</div>
                            <div class="calendar-header-day">Thursday</div>
                            <div class="calendar-header-day">Friday</div>
                            <div class="calendar-header-day">Saturday</div>
                        </div>
                        
                        <!-- Calendar Grid -->
                        <div id="calendar-grid" class="calendar">
                            <!-- Calendar days will be dynamically generated here -->
                        </div>
                    </div>
                </section>

    <!-- MENU PACKAGES SECTION-->
    <section id="section-menu" class="hidden">
        <h2 class="text-2xl font-bold mb-2">Menu Packages</h2>
        <div class="w-full h-0.5 bg-gray-400 mb-6"></div>
        
        <!-- Package Selection Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

            <!-- Silver Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="silver">
                <div class="relative">
                    <img src="Catering_Photos/red_silver_package.jpg" alt="Silver Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        BIRTHDAY
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Silver Package</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Perfect for intimate birthday celebrations and events</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-utensils mr-2 text-[#DC2626]"></i>
                            Complete
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Gold Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="gold">
                <div class="relative">
                    <img src="Catering_Photos/red_gold_package.jpg" alt="Gold Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        BIRTHDAY
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Gold Package</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Enhanced package with host and entertainment</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-utensils mr-2 text-[#DC2626]"></i>
                            Complete
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Platinum Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="platinum">
                <div class="relative">
                    <img src="Catering_Photos/red_platinum_package.jpg" alt="Platinum Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        BIRTHDAY
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Platinum Package</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Premium experience with Tiffany chairs and grand entrance</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-utensils mr-2 text-[#DC2626]"></i>
                            Premium
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Diamond Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="diamond">
                <div class="relative">
                    <img src="catering_Photos/red_diamond_package.jpg" alt="Diamond Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        BIRTHDAY
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Diamond Package</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Ultimate celebration with elegant balloon ceiling</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-utensils mr-2 text-[#DC2626]"></i>
                            All-Inclusive
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Silver Corporate Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="silver_corporate">
                <div class="relative">
                    <img src="Catering_Photos/silver_corporate_package.jpg" alt="Silver Corporate Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-blue-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        CORPORATE
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Silver Corporate</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Enhanced corporate with host and photography</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-briefcase mr-2 text-[#DC2626]"></i>
                            Professional
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Gold Corporate Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="gold_corporate">
                <div class="relative">
                    <img src="catering_Photos/gold_corporate_package.jpg" alt="Gold Corporate Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-blue-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        CORPORATE
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Gold Corporate</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Enhanced corporate with host and photography</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-briefcase mr-2 text-[#DC2626]"></i>
                            Enhanced
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Platinum Corporate Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="platinum_corporate">
                <div class="relative">
                    <img src="catering_Photos/platinum_corporate_package.jpg" alt="Platinum Corporate Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-blue-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        CORPORATE
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Platinum Corporate</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Premium corporate with Tiffany chairs and entrance arch</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-briefcase mr-2 text-[#DC2626]"></i>
                            Premium
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Basic Wedding Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="basic_wedding">
                <div class="relative">
                    <img src="catering_Photos/basic_wedding_package.jpg" alt="Basic Wedding Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-pink-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        WEDDING
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Basic Wedding</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Beautiful wedding catering for intimate celebrations</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-heart mr-2 text-[#DC2626]"></i>
                            Complete
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Premium Wedding Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="premium_wedding">
                <div class="relative">
                    <img src="catering_Photos/premium_wedding_package.jpg" alt="Premium Wedding Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-pink-600 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        WEDDING
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Premium Wedding</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Ultimate wedding with full photo/video and coordination</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-heart mr-2 text-[#DC2626]"></i>
                            Luxury
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Silver Debut Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="silver_debut">
                <div class="relative">
                    <img src="catering_Photos/silver_debut_package.jpg" alt="Silver Debut Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-500 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        DEBUT
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Silver Debut</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Perfect debut package for 18th birthday</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-star mr-2 text-[#DC2626]"></i>
                            Essential
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Gold Debut Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="gold_debut">
                <div class="relative">
                    <img src="catering_Photos/gold_debut_package.jpg" alt="Gold Debut Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-500 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        DEBUT
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Gold Debut</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Enhanced debut with professional host and photography</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-star mr-2 text-[#DC2626]"></i>
                            Enhanced
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Platinum Debut Package -->
            <div class="package-card bg-white rounded-lg shadow-lg overflow-hidden border-2 border-gray-200 hover:border-[#DC2626] transition-all cursor-pointer" data-package="platinum_debut">
                <div class="relative">
                    <img src="catering_Photos/platinum_debut_package.jpg" alt="Platinum Debut Package" class="w-full h-48 object-cover">
                    <div class="absolute top-0 left-0 bg-purple-500 text-white px-3 py-1 text-xs font-bold rounded-br-lg">
                        DEBUT
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Platinum Debut</h3>
                    <p class="text-gray-600 mb-4 text-sm leading-relaxed">Ultimate debut with Tiffany chairs and photo/video</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-star mr-2 text-[#DC2626]"></i>
                            Premium
                        </span>
                        <button class="view-menu-btn bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors text-sm font-medium">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Menu Package Details Modal -->
<div id="menu-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b flex justify-between items-center">
            <div>
                <h3 id="modal-package-name" class="text-2xl font-bold text-gray-800"></h3>
                <p id="modal-package-price" class="text-lg text-[#DC2626] font-semibold"></p>
            </div>
            <button id="close-menu-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            <div class="mb-6">
                <h4 class="text-lg font-semibold mb-2">Description</h4>
                <div id="modal-description" class="text-gray-700"></div>
            </div>
            
            <div class="mb-6">
                <h4 class="text-lg font-semibold mb-4">Menu Items</h4>
                <div id="modal-menu-items" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
            </div>
            
            <div class="mb-6">
                <h4 class="text-lg font-semibold mb-2">Package Inclusions</h4>
                <ul id="modal-inclusions" class="list-disc list-inside space-y-2 text-gray-700"></ul>
            </div>

            <div class="mb-6">
                <h4 class="text-lg font-semibold mb-2">Pricing</h4>
                <div id="modal-pricing" class="bg-gray-50 p-4 rounded-lg">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Number of Guests</th>
                                <th class="text-right py-2">Price</th>
                            </tr>
                        </thead>
                        <tbody id="pricing-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t bg-gray-50">
            <button id="book-package-btn" class="w-full bg-[#DC2626] hover:bg-[#B91C1C] text-white py-3 px-6 rounded-lg font-semibold text-lg transition-colors">
                <i class="fas fa-calendar-plus mr-2"></i>
                Book This Package
            </button>
        </div>
    </div>
</div>



                <section id="section-gallery" class="hidden">
                    <h2 class="text-2xl font-bold mb-2">Gallery</h2>
                    <div class="w-full h-0.5 bg-gray-400 mb-4"></div>
                    <p>Gallery content here...</p>
                </section>
                
            <section id="section-settings" class="hidden">
                <h2 class="text-2xl font-bold mb-2">Profile Settings</h2>
                <div class="w-full h-0.5 bg-gray-400 mb-6"></div>
                
                <div class="mx-auto">
                    <!-- Profile Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <div class="flex flex-col md:flex-row items-center gap-6">
                            <!-- Avatar Selection -->
                            <div class="flex flex-col items-center">
                                <div class="relative">
                                    <img id="profile-avatar" 
                                        src="<?php 
                                        if (!empty($_SESSION['avatar_url'])) {
                                            echo htmlspecialchars($_SESSION['avatar_url']);
                                        } else {
                                            echo 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . ($_SESSION['user_id'] ?? 'default');
                                        }
                                        ?>" 
                                        alt="Profile Avatar" 
                                        class="w-32 h-32 rounded-full border-4 border-blue-500 shadow-lg">
                                    <button id="change-avatar-btn" 
                                            class="absolute bottom-0 right-0 bg-blue-500 text-white p-2 rounded-full hover:bg-blue-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-sm text-gray-600 mt-2">Click to change avatar</p>
                            </div>

                            <!-- User Information -->
                            <div class="flex-1 text-center md:text-left">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h3 class="text-2xl font-bold text-gray-800" id="profile-name">
                                            <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
                                        </h3>
                                        <p class="text-gray-600 mt-1" id="profile-email">
                                            <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
                                        </p>
                                    </div>
                                    <div class="relative self-start">
                                        <button id="profile-menu-btn" class="text-gray-600 hover:text-gray-800 p-2 rounded-full hover:bg-gray-100 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                            </svg>
                                        </button>
                                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                            <button id="change-password-btn" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 transition border-b border-gray-100">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                                <span class="text-gray-700">Change Password</span>
                                            </button>
                                            <button id="toggle-darkmode" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 transition border-b border-gray-100">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                                </svg>
                                                <span class="text-gray-700">Dark Mode</span>
                                            </button>
                                            <button id="dropdown-signout" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 rounded-b-lg transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                                </svg>
                                                <span class="text-red-600 font-semibold">Sign Out</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Booking Statistics -->
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <p class="text-2xl font-bold text-blue-600" id="total-bookings">0</p>
                                        <p class="text-xs text-gray-600">Total Events</p>
                                    </div>
                                    <div class="bg-purple-50 rounded-lg p-3 cursor-pointer hover:bg-purple-100 transition" id="upcoming-events-card">
                                        <p class="text-2xl font-bold text-purple-600" id="upcoming-events">0</p>
                                        <p class="text-xs text-gray-600">Upcoming</p>
                                    </div>
                                    <div class="bg-green-50 rounded-lg p-3">
                                        <p class="text-2xl font-bold text-green-600" id="total-spent">₱0.00</p>
                                        <p class="text-xs text-gray-600">Total Spent</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Event Details -->
                    <div id="next-event-card" class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg shadow-md p-6 mb-6 hidden">
                        <div class="flex items-center gap-3 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <h4 class="text-lg font-semibold text-gray-800">Next Event</h4>
                        </div>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Event Date</p>
                                <p class="text-lg font-bold text-purple-700" id="next-event-date">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Time</p>
                                <p class="text-lg font-bold text-purple-700" id="next-event-time">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Celebrant</p>
                                <p class="text-lg font-bold text-gray-800" id="next-event-celebrant">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Event Type</p>
                                <p class="text-lg font-bold text-gray-800 capitalize" id="next-event-type">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Location</p>
                                <p class="text-lg font-bold text-gray-800" id="next-event-location">-</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Days Until Event</p>
                                <p class="text-lg font-bold text-pink-600" id="next-event-countdown">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Info -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h4 class="text-lg font-semibold mb-4">Account Information</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Member Since:</span>
                                <span class="font-semibold"><?php echo date('F Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Change Password Modal -->
                    <div id="password-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                            <div class="p-6 border-b flex items-center justify-between">
                                <h3 class="text-xl font-bold">Change Password</h3>
                                <button id="close-password-modal" class="text-gray-500 hover:text-gray-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <form id="change-password-form" class="p-6">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                    <input type="password" id="current-password" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                    <input type="password" id="new-password" required minlength="11"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <p class="text-xs text-gray-500 mt-1">Minimum 11 characters</p>
                                </div>
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                    <input type="password" id="confirm-password" required minlength="11"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" id="cancel-password-btn" 
                                            class="flex-1 px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                        Cancel
                                    </button>
                                    <button type="submit" 
                                            class="flex-1 px-6 py-2 text-white rounded-lg hover:opacity-90 transition"
                                            style="background-color: #DC2626;">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Popup Modal -->
                    <div id="password-popup-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]">
                        <div class="bg-white rounded-lg shadow-xl w-80 p-6 text-center">
                            <div id="password-popup-icon" class="flex justify-center mb-3"></div>
                            <p id="password-popup-message" class="text-base font-semibold text-gray-800 mb-4"></p>
                            <button id="close-password-popup" class="px-6 py-2 text-white rounded-lg hover:opacity-90 w-full" style="background-color: #DC2626;">
                                OK
                            </button>
                        </div>
                    </div>

                    <!-- Avatar Selection Modal -->
                    <div id="avatar-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                            <div class="p-6 border-b">
                                <h3 class="text-xl font-bold">Choose Your Avatar</h3>
                            </div>
                            <div class="p-6 overflow-y-auto max-h-[60vh]">
                                <div class="grid grid-cols-3 sm:grid-cols-4 gap-4" id="avatar-grid"></div>
                            </div>
                            <div class="p-6 border-t flex justify-end gap-3">
                                <button id="cancel-avatar-btn" 
                                        class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sign Out Modal -->
                    <div id="signout-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                        <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-80 text-center">
                            <h3 class="text-lg font-semibold mb-4">Are you sure you want to sign out?</h3>
                            <div class="flex justify-center gap-4">
                                <button id="cancel-signout" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 w-24">NO</button>
                                <button id="confirm-signout" class="px-4 py-2 rounded text-white w-24" style="background-color:#DC2626;">YES</button>
                            </div>
                        </div>
                    </div>
                </section>

<section id="section-about" class="hidden">
    <h2 class="text-3xl font-bold mb-2 text-gray-800">About Zaf's Kitchen</h2>
    <div class="w-full h-0.5 bg-gray-400 mb-6"></div>
    
    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-[#DC2626] to-[#B91C1C] rounded-xl shadow-lg p-8 mb-8 text-white">
        <div class="max-w-4xl mx-auto text-center">
            <h3 class="text-3xl font-bold mb-4">Excellence in Catering Since 2020</h3>
            <p class="text-lg opacity-90">Creating memorable experiences through exceptional food and service</p>
        </div>
    </div>

    <!-- Vision & Mission Grid -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Vision Card -->
        <div class="bg-white rounded-lg shadow-lg border-2 border-gray-200 p-6 hover:shadow-xl transition-shadow">
            <div class="flex items-center mb-4">
                <div class="bg-[#DC2626] rounded-full p-3 mr-4">
                    <i class="fas fa-eye text-white text-2xl"></i>
                </div>
                <h4 class="text-2xl font-bold text-gray-800">Vision</h4>
            </div>
            <p class="text-gray-700 leading-relaxed">
                To provide quality food and services that exceeds the expectations of our valued customers and clients.
            </p>
        </div>

        <!-- Mission Card -->
        <div class="bg-white rounded-lg shadow-lg border-2 border-gray-200 p-6 hover:shadow-xl transition-shadow">
            <div class="flex items-center mb-4">
                <div class="bg-[#DC2626] rounded-full p-3 mr-4">
                    <i class="fas fa-bullseye text-white text-2xl"></i>
                </div>
                <h4 class="text-2xl font-bold text-gray-800">Mission</h4>
            </div>
            <p class="text-gray-700 leading-relaxed">
                Our main aim is to build long term relationship with clients through exceptional service, quality food and competitive pricing.
            </p>
        </div>
    </div>

    <!-- Core Values Section -->
    <div class="bg-white rounded-lg shadow-lg border-2 border-gray-200 p-8 mb-8">
        <div class="flex items-center mb-6">
            <div class="bg-[#DC2626] rounded-full p-3 mr-4">
                <i class="fas fa-heart text-white text-2xl"></i>
            </div>
            <h4 class="text-2xl font-bold text-gray-800">Core Values</h4>
        </div>
        <p class="text-gray-700 leading-relaxed mb-6">
            We are serious about creating a productive, cooperative and rewarding environment for our staff and maintaining the quality service and consistency for our customers. Our team believe in living our values, some of which are:
        </p>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-lg p-6 border border-red-200">
                <div class="text-[#DC2626] mb-3">
                    <i class="fas fa-handshake text-4xl"></i>
                </div>
                <h5 class="font-bold text-gray-800 mb-2">Respect</h5>
                <p class="text-sm text-gray-600">We believe in treating our customers with respect.</p>
            </div>
            <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-lg p-6 border border-red-200">
                <div class="text-[#DC2626] mb-3">
                    <i class="fas fa-user-check text-4xl"></i>
                </div>
                <h5 class="font-bold text-gray-800 mb-2">Attentiveness</h5>
                <p class="text-sm text-gray-600">We make sure to be attentive to the needs of our customers.</p>
            </div>
            <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-lg p-6 border border-red-200">
                <div class="text-[#DC2626] mb-3">
                    <i class="fas fa-lightbulb text-4xl"></i>
                </div>
                <h5 class="font-bold text-gray-800 mb-2">Innovation</h5>
                <p class="text-sm text-gray-600">We grow through creativity and innovation in order to keep us updated to modern designs and setup.</p>
            </div>
        </div>
    </div>

    <!-- Background Story -->
    <div class="bg-white rounded-lg shadow-lg border-2 border-gray-200 p-8 mb-8">
        <div class="flex items-center mb-6">
            <div class="bg-[#DC2626] rounded-full p-3 mr-4">
                <i class="fas fa-book-open text-white text-2xl"></i>
            </div>
            <h4 class="text-2xl font-bold text-gray-800">Background</h4>
        </div>
        <div class="prose max-w-none">
            <p class="text-gray-700 leading-relaxed mb-4">
                Zaf's Kitchen was established on <strong>June 18, 2020</strong>, during the pandemic. We started on selling Spam Musubi and Sushi online and eventually shifted to Party Trays. Since dining out was not allowed during the pandemic, we had an idea of offering food packages and deliveries.
            </p>
            <p class="text-gray-700 leading-relaxed mb-4">
                Due to high demand of food deliveries that time, our business became a hit. More and more people started to know our business. They were actually the ones who gave us the idea of offering Catering Service too.
            </p>
            <p class="text-gray-700 leading-relaxed">
                As time goes by and restrictions were lifted, we started Catering Service. Up until now, we are still in the business of Party Trays and Catering Service.
            </p>
        </div>
    </div>

    <!-- Rules and Regulations -->
    <div class="bg-white rounded-lg shadow-lg border-2 border-gray-200 p-8 mb-8">
        <div class="flex items-center mb-6">
            <div class="bg-[#DC2626] rounded-full p-3 mr-4">
                <i class="fas fa-clipboard-check text-white text-2xl"></i>
            </div>
            <h4 class="text-2xl font-bold text-gray-800">Rules and Regulations</h4>
        </div>
        <div class="space-y-4">
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">1.</div>
                <p class="text-gray-700">We ensure the cleanliness and safety of the food we serve, all our staff are required to wear hairnet in the kitchen and gloves during catering service.</p>
            </div>
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">2.</div>
                <p class="text-gray-700">All staff must wear their complete uniform during on duty.</p>
            </div>
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">3.</div>
                <p class="text-gray-700">Make sure to leave no trash on venue after event. No left overs on sink.</p>
            </div>
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">4.</div>
                <p class="text-gray-700">All staff on duty are required to prepare the things needed a day before the event.</p>
            </div>
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">5.</div>
                <p class="text-gray-700">All losses and breakage on equipment has a corresponding charge.</p>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions -->
    <div class="bg-white rounded-lg shadow-lg border-2 border-gray-200 p-8 mb-8">
        <div class="flex items-center mb-6">
            <div class="bg-[#DC2626] rounded-full p-3 mr-4">
                <i class="fas fa-file-contract text-white text-2xl"></i>
            </div>
            <h4 class="text-2xl font-bold text-gray-800">Terms and Conditions</h4>
        </div>
        
        <div class="space-y-6">
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">1.</div>
                <p class="text-gray-700">The duration of the catering service shall be limited to four (4) hours from the above stated start of event.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">2.</div>
                <p class="text-gray-700">An additional One Thousand Five Hundred Pesos (PhP 1,500.00) service fee for Silver Package and Two Thousand Five Hundred Pesos (PhP 2,500.00) for Gold, Platinum and Diamond Packages, shall be charged for every hour in excess thereof.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">3.</div>
                <p class="text-gray-700">Zaf's Kitchen shall be responsible for setting up all tables, chairs, kitchen/dining tools, and other necessary and related equipment, (including but not limited to, utensils, dishes, drinking glass) that will be used by the guests. For avoidance of doubt, Zaf's Kitchen shall set up such number of tables and chairs as is necessary to accommodate the guaranteed number of guests, as defined above. If additional tables and chairs are needed to accommodate guests in excess of the guaranteed number of guests, as defined, client shall be charged an additional One Thousand Pesos (PhP1,000.00) to Three Thousand Pesos (PhP 3,000.00) depending on the number of added equipment.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">4.</div>
                <p class="text-gray-700">The guaranteed number of guests, as defined above, shall be strictly followed. If the actual number of guests in attendance is greater than what was declared, Zaf's Kitchen cannot guarantee that sufficient food and beverage will be available for all guests in attendance. Furthermore, client shall be charged an additional Three Hundred Fifty Pesos (PhP350.00) per head for every excess guest in the guaranteed number of guests who was/were served with food and/or beverage. Head Count will be based on the number of plates used.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">5.</div>
                <p class="text-gray-700">Any change in the theme of the event, as defined above, should be communicated to Zaf's Kitchen in writing no less than twenty-one days (21) days before the date of the event.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">6.</div>
                <p class="text-gray-700">If the event is held other than the ground floor, the client shall be charged an additional Lifting fee of One Thousand Five Hundred Pesos (PhP 1,500.00).</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">7.</div>
                <p class="text-gray-700">Zaf's Kitchen shall charge additional transportation fee depending on the location of the venue.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">8.</div>
                <p class="text-gray-700">Loss or breakage of catering tools and equipment, including kitchen/dining tools, due to the fault of the client or any of the guests, whether by accident or otherwise, shall be charged accordingly.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">9.</div>
                <p class="text-gray-700">The client is solely responsible for all costs and/or deposits relating to the use of the venue, and for obtaining any necessary permissions or authorizations that are needed for the use of the venue. This includes without limitation, venue/entrance fee, parking fees, of Zaf's Kitchen.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">10.</div>
                <p class="text-gray-700">Client shall pay 3% service charge for bookings on Holidays.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">11.</div>
                <p class="text-gray-700">Crew meals shall be provided by the client. Alternatively, Client shall pay for the above-mentioned crew meals in cash amounting to Two Hundred Pesos (PhP 200.00)/head.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">12.</div>
                <p class="text-gray-700">The client understands that Zaf's Kitchen shall exclusively provide the food and beverage for the booked event. Should client wish to add food and/or beverage not provided by Zaf's Kitchen ("Outside Food"), the Client shall inform Zaf's Kitchen by providing a list enumerating all such Outside Food. Should Client fail to inform Zaf's Kitchen of the Outside Food, Client shall hold Zaf's Kitchen free and harmless from any damage, liability, cost, loss or penalty arising directly or indirectly from, or in connection with food and/or beverage poisoning.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">13.</div>
                <p class="text-gray-700">Zaf's Kitchen staff are not responsible for the preparation and serving of "Outside Food". If assistance is needed, a service fee of Php 500.00 shall be charged.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">14.</div>
                <p class="text-gray-700">For outdoor venues with high risk of unpredictable weather such as rain, and the client refuses to provide tents and other indoor solutions, an "Inclement Weather Fee" of Php 3,000.00 shall be charged to the client. This is to cover the potential damages to equipment, laundry fees, additional labor, delay and cleanup of the catering equipment.</p>
            </div>
            
            <div class="flex items-start">
                <div class="text-[#DC2626] font-bold mr-4 flex-shrink-0 mt-1">15.</div>
                <p class="text-gray-700">Client may opt to take-out left over food, considering Zaf's Kitchen will not be held liable for any circumstance it may cause.</p>
            </div>
        </div>
        
        <!-- Cancellation/Rescheduling Section -->
        <div class="mt-8 pt-6 border-t border-gray-300">
            <h5 class="text-xl font-bold text-gray-800 mb-4">CANCELLATION/RESCHEDULING</h5>
            <p class="text-gray-700 mb-4">No cancellation/washdown/rescheduling of this agreement shall take except upon written notice in accordance with terms and conditions set forth herein:</p>
            <ul class="list-disc pl-5 text-gray-700 space-y-2 mb-6">
                <li>Reservation Fee is non-refundable</li>
                <li>Cancellation not allowed once client has paid the down payment. Signed Contract must be returned right after being sent/presented.</li>
                <li>Re-scheduling of event or function due to force majeure shall be allowed provided the unexpected event crucially affects the schedule date and time of the function.</li>
                <li>Re-scheduling of events should only be allowed provided the advice is made two (2) months before the event date. However, re-scheduling must be within two (2) months of the original function date.</li>
                <li>Should the client decide to downgrade the package, he/she will still be charged the same amount, as defined under the Billing Arrangement clause of this Contract, and subject to all other terms and conditions mentioned herein.</li>
            </ul>
        </div>
        
        <!-- Termination Section -->
        <div class="mt-6 pt-6 border-t border-gray-300">
            <h5 class="text-xl font-bold text-gray-800 mb-4">TERMINATION</h5>
            <p class="text-gray-700 mb-6">Either Party may, upon written notice to the other Party at least thirty (30) days before the scheduled event, terminate this Agreement with or without cause. If the Client terminates the Agreement within the foregoing period, Client shall be liable to pay Zaf's Kitchen an amount equivalent to twenty percent (20%) of the total contract price. Any cancellation made outside of the foregoing period shall not entitle the Client to any refund.</p>
        </div>
        
        <!-- Limit of Liability Section -->
        <div class="mt-6 pt-6 border-t border-gray-300">
            <h5 class="text-xl font-bold text-gray-800 mb-4">LIMIT OF LIABILITY</h5>
            <p class="text-gray-700 mb-6">ZAF'S Kitchen shall not be liable for its failure to comply with its obligations under this contract in cases of natural disaster, fortuitous events and such other cause/s unforeseeable or beyond the control of the service provider.</p>
        </div>
        
        <!-- Agreement Section -->
        <div class="mt-6 pt-6 border-t border-gray-300">
            <p class="text-gray-700 mb-6 italic">I HAVE READ AND UNDERSTOOD THE FOREGOING TERMS AND CONDITIONS AND HEREBY AGREE TO COMPLY WITH THE SAME.</p>
            <div class="flex justify-between">
                <div>
                    <p class="text-gray-800 font-bold">CONFORME:</p>
                    <p class="text-gray-700">Ms. Arian Aubrey Sin Juan-Tulud & Mr. Renz Charles Tulud</p>
                </div>
                <div>
                    <p class="text-gray-800 font-bold">ZAFS KITCHEN</p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-gray-800 font-bold">Client</p>
            </div>
        </div>
    </div>

    <!-- Contact CTA -->
    <div class="mt-8 text-center bg-gradient-to-r from-[#DC2626] to-[#B91C1C] rounded-xl shadow-lg p-8 text-white">
        <h3 class="text-2xl font-bold mb-4">Ready to Book Your Event?</h3>
        <p class="mb-6 opacity-90">Let us make your celebration unforgettable with our exceptional catering service</p>
        <button onclick="showBookNowSection()" class="bg-white text-[#DC2626] px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors shadow-lg">
            <i class="fas fa-calendar-plus mr-2"></i>
            Book Now
        </button>
    </div>
</section>
            </main>

            <div id="booking-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
                <div class="modal-content bg-white p-6 rounded-lg shadow-lg w-96 max-h-96 overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Bookings for <span id="selected-date"></span></h3>
                        <button id="close-booking-details" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div id="booking-details-content">
                        <!-- Booking details will be populated here -->
                    </div>
                </div>
            </div>

                <!-- Loading Screen -->
            <div id="page-loader" class="fixed inset-0 bg-white z-[9999] flex items-center justify-center">
                <div class="text-center">
                    <div class="relative">
                        <img src="logo/logo-border.png" alt="Zaf's Kitchen" class="w-32 h-32 mx-auto mb-4 animate-pulse">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-40 h-40 border-4 border-[#DC2626] border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Zaf's Kitchen</h2>
                    <p class="text-gray-600">Loading your dashboard...</p>
                </div>
            </div>
            

<!-- JavaScript -->
<script>
// Package pricing based on exact rates from your packages
const PACKAGE_PRICES = {
// Birthday/Event Packages
silver: {
    30: 23000, 40: 27000, 50: 30000, 60: 33000,
    70: 36000, 80: 40000, 90: 45000, 100: 50000
},
gold: {
    30: 41000, 40: 45000, 50: 48000, 60: 52000,
    70: 56000, 80: 60000, 90: 64000, 100: 69000
},
platinum: {
    30: 52000, 40: 54000, 50: 58000, 60: 63000,
    70: 67000, 80: 72000, 90: 77000, 100: 82000
},
diamond: {
    30: 67000, 40: 69000, 50: 73000, 60: 78000,
    70: 82000, 80: 87000, 90: 92000, 100: 97000
},

// Wedding Packages
basic_wedding: {
    30: 42000, 40: 47000, 50: 50000, 60: 55000,
    70: 60000, 80: 65000, 90: 70000, 100: 75000
},
premium_wedding: {
    50: 130000, 60: 135000, 70: 145000, 80: 155000,
    90: 160000, 100: 165000
},

// Debut Packages
silver_debut: {
    30: 24000, 40: 27000, 50: 30000, 60: 34000,
    70: 38000, 80: 42000, 90: 47000, 100: 52000
},
gold_debut: {
    30: 43000, 40: 46000, 50: 49000, 60: 53000,
    70: 57000, 80: 62000, 90: 67000, 100: 72000
},
platinum_debut: {
    30: 54000, 40: 57000, 50: 61000, 60: 65000,
    70: 70000, 80: 75000, 90: 81000, 100: 86000
},

// Corporate Packages
silver_corporate: {
    30: 25000, 40: 27000, 50: 30000, 60: 34000,
    70: 38000, 80: 42000, 90: 46000, 100: 50000
},
gold_corporate: {
    30: 44000, 40: 46000, 50: 49000, 60: 53000,
    70: 57000, 80: 61000, 90: 65000, 100: 69000
},
platinum_corporate: {
    30: 50000, 40: 53000, 50: 56000, 60: 60000,
    70: 65000, 80: 70000, 90: 75000, 100: 80000
}
};

let currentPriceData = {
    basePrice: 0,
    additionalPrice: 0,
    guestCount: 0,
    packageType: ''
};

function updatePriceCalculator() {
    const guestCount = parseInt(document.getElementById('guest-count')?.value) || 0;
    const packageSelect = document.getElementById('package');
    const packageType = packageSelect?.value || '';
    
    // Update all guest displays across all steps
    updateGuestDisplays(guestCount);
    
    if (!packageType || !guestCount) {
        resetPriceDisplay();
        return;
    }
    
    // Get base price from package pricing table
    let basePrice = 0;
    const packagePricing = PACKAGE_PRICES[packageType];
    
    if (packagePricing && packagePricing[guestCount]) {
        // Exact match for guest count
        basePrice = packagePricing[guestCount];
    } else {
        console.error(`No pricing found for ${packageType} with ${guestCount} guests`);
        showMessage('error', 'Pricing Error', `No pricing available for ${guestCount} guests with this package. Please select a different guest count.`);
        resetPriceDisplay();
        return;
    }
    
    currentPriceData.basePrice = basePrice;
    currentPriceData.guestCount = guestCount;
    currentPriceData.packageType = packageType;
    
    // Calculate additional items (per person)
    let additionalPrice = 0;
    document.querySelectorAll('input[name^="menu_"]:checked').forEach(checkbox => {
        const itemPrice = parseInt(checkbox.dataset.price) || 0;
        additionalPrice += itemPrice * guestCount;
    });
    currentPriceData.additionalPrice = additionalPrice;
    
    // Update all price displays across all steps
    updateAllPriceDisplays(basePrice, additionalPrice, guestCount);
    
    // Update hidden input for form submission
    const totalPrice = basePrice + additionalPrice;
    document.getElementById('total_price').value = totalPrice;
    
    // Add animation to all total displays
    animatePriceUpdate();
}

function updateGuestDisplays(guestCount) {
    const guestTexts = [`${guestCount} guests`, `${guestCount} guests`, `${guestCount} guests`];
    
    document.getElementById('guest-display').textContent = guestTexts[0];
    
    const step2Display = document.getElementById('guest-display-step2');
    if (step2Display) step2Display.textContent = guestTexts[1];
    
    const step3Display = document.getElementById('guest-display-step3');
    if (step3Display) step3Display.textContent = guestTexts[2];
}

function updateAllPriceDisplays(basePrice, additionalPrice, guestCount) {
    const totalPrice = basePrice + additionalPrice;
    const formattedBase = `₱${basePrice.toLocaleString()}.00`;
    const formattedAdditional = `₱${additionalPrice.toLocaleString()}.00`;
    const formattedTotal = `₱${totalPrice.toLocaleString()}.00`;
    
    // Step 1 displays
    document.getElementById('base-price').textContent = formattedBase;
    const additionalContainer1 = document.getElementById('additional-items-container');
    if (additionalPrice > 0) {
        additionalContainer1.style.display = 'flex';
        document.getElementById('additional-price').textContent = formattedAdditional;
    } else {
        additionalContainer1.style.display = 'none';
    }
    document.getElementById('total-display').textContent = formattedTotal;
    
    // Step 2 displays
    const basePriceStep2 = document.getElementById('base-price-step2');
    if (basePriceStep2) basePriceStep2.textContent = formattedBase;
    
    const additionalContainer2 = document.getElementById('additional-items-container-step2');
    if (additionalContainer2) {
        if (additionalPrice > 0) {
            additionalContainer2.style.display = 'flex';
            const additionalPriceStep2 = document.getElementById('additional-price-step2');
            if (additionalPriceStep2) additionalPriceStep2.textContent = formattedAdditional;
        } else {
            additionalContainer2.style.display = 'none';
        }
    }
    
    const totalDisplayStep2 = document.getElementById('total-display-step2');
    if (totalDisplayStep2) totalDisplayStep2.textContent = formattedTotal;
    
    // Step 3 displays
    const basePriceStep3 = document.getElementById('base-price-step3');
    if (basePriceStep3) basePriceStep3.textContent = formattedBase;
    
    const additionalContainer3 = document.getElementById('additional-items-container-step3');
    if (additionalContainer3) {
        if (additionalPrice > 0) {
            additionalContainer3.style.display = 'flex';
            const additionalPriceStep3 = document.getElementById('additional-price-step3');
            if (additionalPriceStep3) additionalPriceStep3.textContent = formattedAdditional;
        } else {
            additionalContainer3.style.display = 'none';
        }
    }
    
    const totalDisplayStep3 = document.getElementById('total-display-step3');
    if (totalDisplayStep3) totalDisplayStep3.textContent = formattedTotal;
}

function animatePriceUpdate() {
    // Add animation class to all total displays
    const totalDisplays = [
        'total-display',
        'total-display-step2', 
        'total-display-step3'
    ];
    
    totalDisplays.forEach(displayId => {
        const element = document.getElementById(displayId);
        if (element) {
            element.classList.add('calculating');
            setTimeout(() => {
                element.classList.remove('calculating');
            }, 1000);
        }
    });
}

function resetPriceDisplay() {
    const priceElements = [
        { id: 'base-price', value: '₱0.00' },
        { id: 'base-price-step2', value: '₱0.00' },
        { id: 'base-price-step3', value: '₱0.00' },
        { id: 'additional-price', value: '₱0.00' },
        { id: 'additional-price-step2', value: '₱0.00' },
        { id: 'additional-price-step3', value: '₱0.00' },
        { id: 'total-display', value: '₱0.00' },
        { id: 'total-display-step2', value: '₱0.00' },
        { id: 'total-display-step3', value: '₱0.00' },
        { id: 'guest-display', value: '0 guests' },
        { id: 'guest-display-step2', value: '0 guests' },
        { id: 'guest-display-step3', value: '0 guests' }
    ];
    
    priceElements.forEach(({ id, value }) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    });
    
    // Hide additional items containers
    const additionalContainers = [
        'additional-items-container',
        'additional-items-container-step2',
        'additional-items-container-step3'
    ];
    
    additionalContainers.forEach(containerId => {
        const container = document.getElementById(containerId);
        if (container) container.style.display = 'none';
    });
    
    document.getElementById('total_price').value = '0';
    currentPriceData = { basePrice: 0, additionalPrice: 0, guestCount: 0, packageType: '' };
}

// Enhanced step navigation with price sync
function goToStep(stepNumber) {
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(step => {
        step.classList.add('hidden');
        step.classList.remove('active');
    });
    
    // Show target step
    const targetStep = document.getElementById(`booking-step${stepNumber}`);
    if (targetStep) {
        targetStep.classList.remove('hidden');
        targetStep.classList.add('active');
    }
    
    // Update progress indicators
    updateStepProgress(stepNumber);
    
    // Sync price displays when navigating
    updatePriceCalculator();
    
    // Scroll to top of form
    document.getElementById('section-book').scrollIntoView({ behavior: 'smooth' });
}

function updateStepProgress(activeStep) {
    for (let i = 1; i <= 3; i++) {
        const stepItem = document.getElementById(`step-${i}`);
        const stepLine = stepItem.nextElementSibling;
        
        if (i < activeStep) {
            stepItem.classList.add('completed');
            stepItem.classList.remove('active', 'inactive');
            if (stepLine && stepLine.classList.contains('step-line')) {
                stepLine.classList.add('completed');
                stepLine.classList.remove('inactive');
            }
        } else if (i === activeStep) {
            stepItem.classList.add('active');
            stepItem.classList.remove('completed', 'inactive');
        } else {
            stepItem.classList.add('inactive');
            stepItem.classList.remove('active', 'completed');
            if (stepLine && stepLine.classList.contains('step-line')) {
                stepLine.classList.add('inactive');
                stepLine.classList.remove('completed');
            }
        }
    }
}

// Enhanced Booking Card Generation with Price Display and Delete Button
function generateBookingCardWithPrice(booking, isPast = false) {
    const eventDate = new Date(booking.event_date);
    const formattedDate = eventDate.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    const startTime12 = formatTimeTo12Hour(booking.start_time.substring(0, 5));
    const endTime12 = formatTimeTo12Hour(booking.end_time.substring(0, 5));
    const timeRange = `${startTime12} - ${endTime12}`;
    
    const createdDate = new Date(booking.created_at);
    const formattedCreatedDate = createdDate.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Calculate price if total_price exists, otherwise estimate
    let displayPrice = '₱0.00';
    if (booking.total_price && booking.total_price > 0) {
        displayPrice = `₱${parseFloat(booking.total_price).toLocaleString()}`;
    } else if (booking.guest_count && booking.food_package) {
        // Estimate price based on package and guest count from pricing table
        const packagePricing = PACKAGE_PRICES[booking.food_package];
        const guestCount = parseInt(booking.guest_count);
        
        if (packagePricing && packagePricing[guestCount]) {
            const estimatedPrice = packagePricing[guestCount];
            displayPrice = `₱${estimatedPrice.toLocaleString()}`;
        }
    }
    
    const statusClass = `status-${booking.booking_status}`;
    const cardOpacity = isPast ? 'opacity-75' : '';
    
    // Status messages and icons
    let statusIcon = '';
    let statusMessage = '';
    
    switch(booking.booking_status) {
        case 'pending':
            statusIcon = '<i class="fas fa-clock text-yellow-600"></i>';
            statusMessage = isPast ? 'Was pending approval' : 'Waiting for admin approval';
            break;
        case 'approved':
            statusIcon = '<i class="fas fa-check-circle text-green-600"></i>';
            statusMessage = isPast ? 'Event completed successfully' : 'Confirmed! Your event is approved';
            break;
        case 'cancelled':
            statusIcon = '<i class="fas fa-times-circle text-red-600"></i>';
            statusMessage = 'This booking was cancelled';
            break;
    }
    
    const ageDisplay = booking.event_type === 'birthday' && booking.celebrant_age ? 
        ` (${booking.celebrant_age} years old)` : '';
    
    // Only pending bookings can be deleted
    const canDelete = (booking.booking_status === 'pending');
    
    return `
        <div class="booking-card-enhanced ${statusClass} ${cardOpacity} relative p-6">
            <div class="booking-status-indicator"></div>
            
            <div class="flex justify-between items-start mb-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-xl font-semibold text-gray-800 capitalize">${booking.event_type}${ageDisplay}</h3>
                        <span class="status-badge ${statusClass}">${booking.booking_status}</span>
                        ${isPast ? '<span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">PAST EVENT</span>' : ''}
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                        ${statusIcon}
                        <span>${statusMessage}</span>
                    </div>
                    <div class="text-lg font-medium text-[#DC2626] mb-1">
                        <i class="fas fa-star mr-2"></i>
                        Celebrating: ${booking.celebrant_name}
                    </div>
                </div>
                <div class="text-right flex flex-col items-end gap-2">
                    <div class="booking-price-tag">
                        ${displayPrice}
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Booking ID</div>
                        <div class="font-mono text-sm">#${booking.id.toString().padStart(4, '0')}</div>
                    </div>
            <div class="mt-2 flex gap-2">
                <button onclick='showpreviewModal(${JSON.stringify(booking)})' 
                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-5 py-1 text-xs rounded-lg transition-colors" 
                    title="View preview">
                    <i class="fas fa-preview mr-1"></i>Book Preview
                </button>
                ${canDelete ? `
                <button onclick="showDeleteModal(${booking.id}, '${booking.celebrant_name}', '${booking.event_type}')" 
                    class="flex-1 bg-red-500 hover:bg-red-600 text-white px-3 py-1 text-xs rounded-lg transition-colors" 
                    title="Delete this booking">
                    <i class="fas fa-trash mr-1"></i>Cancel     
                </button>
                ` : ''}
            </div>
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-calendar text-[#DC2626] w-4"></i>
                        <span class="font-medium">Date:</span>
                        <span>${formattedDate}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-clock text-[#DC2626] w-4"></i>
                        <span class="font-medium">Time:</span>
                        <span>${timeRange}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-users text-[#DC2626] w-4"></i>
                        <span class="font-medium">Guests:</span>
                        <span>${booking.guest_count} people</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-utensils text-[#DC2626] w-4"></i>
                        <span class="font-medium">Package:</span>
                        <span class="capitalize">${booking.food_package}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-user text-[#DC2626] w-4"></i>
                        <span class="font-medium">Contact:</span>
                        <span>${booking.full_name}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-map-marker-alt text-[#DC2626] w-4"></i>
                        <span class="font-medium">Location:</span>
                        <span>${booking.location || 'Not specified'}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-palette text-[#DC2626] w-4"></i>
                        <span class="font-medium">Theme:</span>
                        <span class="capitalize">${booking.event_theme === 'custom' ? (booking.custom_theme || 'Custom') : booking.event_theme}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-calendar-plus text-[#DC2626] w-4"></i>
                        <span class="font-medium">Booked:</span>
                        <span>${formattedCreatedDate}</span>
                    </div>
                </div>
            </div>
            
            ${booking.theme_suggestions ? `
            <div class="mt-4 p-3 bg-gray-50 border-l-4 border-[#DC2626] rounded">
                <div class="flex items-start gap-2 text-sm">
                    <i class="fas fa-lightbulb text-[#DC2626] mt-0.5"></i>
                    <div>
                        <span class="font-medium text-gray-700">Special Requests:</span>
                        <p class="text-gray-600 mt-1">${booking.theme_suggestions}</p>
                    </div>
                </div>
            </div>
            ` : ''}
            
${!isPast ? (booking.booking_status === 'approved' ? `
<div class="mt-4 p-4 bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-lg">
    <div class="flex items-center gap-3">
        <div class="flex-shrink-0">
            <i class="fas fa-check-circle text-green-600 text-xl"></i>
        </div>
        <div class="flex-1">
            <div class="font-semibold text-green-800">Event Confirmed!</div>
            
            ${booking.payment_status !== 'paid' ? `
                <!-- Payment Countdown -->
                <div class="mt-2 p-3 bg-yellow-50 border border-yellow-300 rounded-lg">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-sm"></i>
                        <span class="font-semibold text-yellow-800 text-xs">⏰ Payment Deadline</span>
                    </div>
                    <div class="text-xs text-yellow-700 mb-1">Complete downpayment within:</div>
                    <div id="payment-countdown-${booking.id}" class="text-base font-bold text-yellow-800 payment-countdown" 
                         data-booking-id="${booking.id}">
                        Calculating...
                    </div>
                    <p class="text-xs text-yellow-600 mt-1">⚠️ Booking will auto-cancel if not paid</p>
                </div>
            ` : ''}
            
            <!-- Event Countdown -->
            <div class="mt-2 p-3 bg-blue-50 border border-blue-300 rounded-lg">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-calendar-check text-blue-600 text-sm"></i>
                    <span class="font-semibold text-blue-800 text-xs">📅 Event Countdown</span>
                </div>
                <div id="event-countdown-${booking.id}" class="text-base font-bold text-blue-800 event-countdown" 
                     data-booking-id="${booking.id}">
                    Calculating...
                </div>
                <p class="text-xs text-blue-600 mt-1">${formattedDate} at ${startTime12}</p>
            </div>
        </div>
        <div class="text-right">
            <div class="text-sm text-green-600 font-medium">Total: ${displayPrice}</div>
        </div>
    </div>
</div>
            ` : booking.booking_status === 'pending' ? `
            <div class="mt-4 p-4 bg-gradient-to-r from-yellow-50 to-yellow-100 border border-yellow-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-hourglass-half text-yellow-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-semibold text-yellow-800">Pending Approval</div>
                        <p class="text-sm text-yellow-700 mt-1">We're reviewing your booking. You'll be notified once it's approved!</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-yellow-600 font-medium">Est. Total: ${displayPrice}</div>
                    </div>
                </div>
            </div>
            ` : `
            <div class="mt-4 p-4 bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-ban text-red-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-semibold text-red-800">Booking Cancelled</div>
                        <p class="text-sm text-red-700 mt-1">This booking was cancelled. Contact us if you have questions.</p>
                    </div>
                </div>
            </div>  
            `) : ''}
        </div>
    `;
}

// Enhanced booking display function
function displayBookingsWithPrice(bookings) {
    const container = document.getElementById('bookings-container');
    
    if (bookings.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-calendar-times text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold mb-2">No Bookings Yet</h3>
                <p class="text-gray-500 mb-6">You haven't made any bookings yet. Start by booking your first event!</p>
                <button onclick="showBookNowSection()" class="bg-[#DC2626] hover:bg-[#B91C1C] text-white px-6 py-3 rounded-lg shadow-md transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Make Your First Booking
                </button>
            </div>
        `;
        return;
    }

    // ✅ Initialize all countdown timers
function initializeCountdowns() {
    const countdownElements = document.querySelectorAll('.payment-countdown, .event-countdown');
    
    countdownElements.forEach(element => {
        const bookingId = element.dataset.bookingId;
        updateCountdown(element, bookingId);
        
        // Update every second
        setInterval(() => updateCountdown(element, bookingId), 1000);
    });
}

// ✅ Universal countdown updater
function updateCountdown(element, bookingId) {
    fetch(`?action=check_event_status&booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            // Handle auto-cancellations
            if (data.status === 'auto_cancelled') {
                if (data.reason === 'payment_deadline') {
                    const paymentEl = document.getElementById(`payment-countdown-${bookingId}`);
                    if (paymentEl) {
                        paymentEl.innerHTML = '<span class="text-red-600 font-bold animate-pulse">⏰ PAYMENT EXPIRED</span>';
                    }
                } else if (data.reason === 'event_ended') {
                    const eventEl = document.getElementById(`event-countdown-${bookingId}`);
                    if (eventEl) {
                        eventEl.innerHTML = '<span class="text-gray-600 font-bold">🏁 EVENT ENDED</span>';
                    }
                }
                // Reload bookings after 2 seconds
                setTimeout(() => loadMyBookings(), 2000);
                return;
            }
            
            // Update payment countdown (if unpaid)
            if (data.status === 'awaiting_payment' && data.payment_deadline_seconds !== undefined) {
                const paymentEl = document.getElementById(`payment-countdown-${bookingId}`);
                if (paymentEl) {
                    const seconds = data.payment_deadline_seconds;
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = Math.floor(seconds % 60);
                    
                    let colorClass = 'text-green-600';
                    if (hours < 5) colorClass = 'text-yellow-600';
                    if (hours < 2) colorClass = 'text-orange-600';
                    if (hours < 1) colorClass = 'text-red-600 animate-pulse';
                    
                    paymentEl.innerHTML = `<span class="${colorClass}">${hours}h ${minutes}m ${secs}s</span>`;
                }
            }
            
            // Update event countdown
            const eventSeconds = data.event_start_seconds || data.event_end_seconds || 0;
            const eventEl = document.getElementById(`event-countdown-${bookingId}`);
            
            if (eventEl) {
                // Check if event is ongoing
                if (data.event_start_seconds !== undefined && data.event_start_seconds <= 0 && data.event_end_seconds > 0) {
                    const endSeconds = data.event_end_seconds;
                    const hours = Math.floor(endSeconds / 3600);
                    const minutes = Math.floor((endSeconds % 3600) / 60);
                    const secs = Math.floor(endSeconds % 60);
                    
                    eventEl.innerHTML = `<span class="text-green-600 font-bold animate-pulse">🎉 ONGOING - Ends in ${hours}h ${minutes}m ${secs}s</span>`;
                } else if (data.event_start_seconds !== undefined && data.event_start_seconds > 0) {
                    // Event is upcoming
                    const seconds = data.event_start_seconds;
                    const days = Math.floor(seconds / 86400);
                    const hours = Math.floor((seconds % 86400) / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = Math.floor(seconds % 60);
                    
                    let colorClass = 'text-blue-600';
                    if (days === 0 && hours < 24) colorClass = 'text-purple-600';
                    if (days === 0 && hours < 6) colorClass = 'text-orange-600 font-bold';
                    
                    if (days > 0) {
                        eventEl.innerHTML = `<span class="${colorClass}">${days}d ${hours}h ${minutes}m</span>`;
                    } else {
                        eventEl.innerHTML = `<span class="${colorClass}">${hours}h ${minutes}m ${secs}s</span>`;
                    }
                }
            }
            
        })
        .catch(error => console.error('Countdown update error:', error));
}
    
    // Separate bookings
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const upcomingBookings = bookings.filter(booking => {
        const eventDate = new Date(booking.event_date);
        eventDate.setHours(0, 0, 0, 0);
        return eventDate >= today;
    }).sort((a, b) => new Date(a.event_date) - new Date(b.event_date));
    
    const pastBookings = bookings.filter(booking => {
        const eventDate = new Date(booking.event_date);
        eventDate.setHours(0, 0, 0, 0);
        return eventDate < today;
    }).sort((a, b) => new Date(b.event_date) - new Date(a.event_date));
    
    let bookingsHtml = '';
    
    // Calculate total spent
    let totalSpent = 0;
    bookings.forEach(booking => {
        if (booking.booking_status === 'approved' && booking.total_price) {
            totalSpent += parseFloat(booking.total_price) || 0;
        }
    });

    
    // Upcoming bookings
    if (upcomingBookings.length > 0) {
        bookingsHtml += `
            <div class="mb-8">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-calendar-week text-[#DC2626]"></i>
                    Upcoming Events (${upcomingBookings.length})
                </h3>
                <div class="space-y-6">
        `;
        
        upcomingBookings.forEach(booking => {
            bookingsHtml += generateBookingCardWithPrice(booking);
        });
        
        bookingsHtml += `</div></div>`;
    }
    
    // Past bookings
    if (pastBookings.length > 0) {
        bookingsHtml += `
            <div class="mb-8">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-gray-600"></i>
                    Past Events (${pastBookings.length})
                </h3>
                <div class="space-y-6">
        `;
        
        pastBookings.forEach(booking => {
            bookingsHtml += generateBookingCardWithPrice(booking, true);
        });
        
        bookingsHtml += `</div></div>`;
    }
    
container.innerHTML = bookingsHtml;

// ✅ Initialize countdown timers
setTimeout(() => initializeCountdowns(), 100);
}

// Global variable for tracking delete booking ID
let currentDeleteBookingId = null;

// Show delete confirmation modal
function showDeleteModal(bookingId, celebrantName, eventType) {
    currentDeleteBookingId = bookingId;
    
    const modal = document.getElementById('delete-modal');
    if (modal) {
        const modalContent = modal.querySelector('p');
        if (modalContent) {
            modalContent.innerHTML = `
                Are you sure you want to cancel the <strong>${eventType}</strong> booking for <strong>${celebrantName}</strong>? 
                This action cannot be undone.
            `;
        }
        modal.classList.remove('hidden');
    } else {
        console.error('Delete modal not found. Make sure you have the modal HTML in your page.');
    }
}

// Hide delete confirmation modal
function hideDeleteModal() {
    const modal = document.getElementById('delete-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
    currentDeleteBookingId = null;
}

// Delete booking function
function deleteBooking(bookingId) {
    const formData = new FormData();
    formData.append('action', 'delete_booking');
    formData.append('booking_id', bookingId);
    
    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('success', 'Success', data.message);
            loadMyBookings(); // Refresh the bookings list
        } else {
            showMessage('error', 'Delete Failed', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('error', 'Network Error', 'Please check your connection and try again.');
    });
}

function showSuccessModal(bookingData) {
    // Remove any existing modal
    const existingModal = document.getElementById('success-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const totalCost = currentPriceData.basePrice + currentPriceData.additionalPrice;
    
    const modalHTML = `
        <div id="success-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-8 rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <i class="fas fa-check text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Booking Submitted Successfully!</h3>
                    <div class="text-sm text-gray-500 mb-6">
                        <p class="mb-2">Your booking has been submitted with a total cost of <strong>₱${totalCost.toLocaleString()}</strong>.</p>
                        <div class="text-left bg-gray-50 p-4 rounded-lg">
                            <p class="font-semibold mb-2">What happens next:</p>
                            <ul class="text-xs space-y-1">
                                <li>• Our admin will review your booking details</li>
                                <li>• You'll receive confirmation once approved</li>
                                <li>• Payment details will be provided upon approval</li>
                            </ul>
                            <p class="italic mt-2 text-xs">Thank you for choosing Zaf's Kitchen!</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button id="success-modal-ok" 
                            class="flex-1 bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg transition-colors">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Store booking data for invoice viewing
    const modal = document.getElementById('success-modal');
    modal.dataset.bookingData = JSON.stringify(bookingData);
    
    // Add event listener for OK button - FIXED TO NAVIGATE TO MY BOOKINGS
    const okButton = document.getElementById('success-modal-ok');
    if (okButton) {
        okButton.addEventListener('click', function handleModalClose() {
            // Remove event listener to prevent duplicates
            okButton.removeEventListener('click', handleModalClose);
            
            // Remove modal
            modal.remove();
            
            // Save the section to localStorage before navigating
            saveCurrentSection('section-mybookings');
            
            // Navigate to My Bookings section
            hideAllSections();
            
            // Remove active class from all nav links
            document.querySelectorAll("nav a").forEach(link => {
                link.classList.remove("active-nav");
            });
            
            // Add active class to My Bookings nav link (2nd nav item)
            const navLinks = document.querySelectorAll("nav a");
            if (navLinks[1]) {
                navLinks[1].classList.add("active-nav");
            }
            
            // Show My Bookings section
            const myBookingsSection = document.getElementById("section-mybookings");
            if (myBookingsSection) {
                myBookingsSection.classList.remove("hidden");
                
                // Load bookings data
                loadMyBookings();
                
                // Scroll to top of page
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    }
}

// Show preview modal - INCREASED PADDING
function showpreviewModal(booking) {
    const modal = document.getElementById('preview-modal');
    const content = document.getElementById('preview-content');
    
    if (!modal || !content) return;
    
    // Format dates and times
    const eventDate = new Date(booking.event_date);
    const formattedDate = eventDate.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    const startTime12 = formatTimeTo12Hour(booking.start_time.substring(0, 5));
    const endTime12 = formatTimeTo12Hour(booking.end_time.substring(0, 5));
    const bookedDate = new Date(booking.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // Price formatting
    let priceDisplay = 'To be confirmed';
    let priceNote = 'Final price subject to admin review and location assessment';
    
    if (booking.total_price && booking.total_price > 0) {
        priceDisplay = `₱${parseFloat(booking.total_price).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
        priceNote = 'Estimated total (final amount may vary based on requirements)';
    }
    
    // Status configuration
    const statusConfig = {
        'approved': { 
            class: 'text-green-600', 
            text: 'CONFIRMED',
            icon: 'fa-check-circle'
        },
        'pending': { 
            class: 'text-yellow-600', 
            text: 'PENDING APPROVAL',
            icon: 'fa-clock'
        },
        'cancelled': { 
            class: 'text-red-600', 
            text: 'CANCELLED',
            icon: 'fa-times-circle'
        }
    };
    
    const paymentConfig = {
        'paid': { 
            class: 'text-green-600', 
            text: 'PAID',
            icon: 'fa-credit-card'
        },
        'unpaid': { 
            class: 'text-gray-600', 
            text: 'UNPAID',
            icon: 'fa-exclamation-circle'
        }
    };
    
    const status = statusConfig[booking.booking_status] || statusConfig.pending;
    const paymentStatus = paymentConfig[booking.payment_status || 'unpaid'];
    
    // Contact number handling
    const contactNumber = booking.contact_number ? booking.contact_number : 'Not provided';

    content.innerHTML = `
        <div class="space-y-8 px-8">
            <!-- Header Section -->
            <div class="text-center">
                <div class="flex justify-center items-center mb-4">
                    <img src="logo/logo-border.png" alt="Zaf's Kitchen" class="w-16 h-16">
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">BOOKING CONFIRMATION</h1>
                <div class="flex justify-center items-center space-x-4 text-xs text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-1 text-[#DC2626]"></i>
                        <span>Issued: ${bookedDate}</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-hashtag mr-1 text-[#DC2626]"></i>
                        <span>Ref: #${booking.id.toString().padStart(6, '0')}</span>
                    </div>
                </div>
            </div>

            <!-- Status Badges -->
            <div class="flex justify-center space-x-4 mb-6">
                <div class="flex items-center space-x-1">
                    <i class="fas ${status.icon} ${status.class} text-sm"></i>
                    <span class="font-semibold text-xs ${status.class}">${status.text}</span>
                </div>
                <div class="flex items-center space-x-1">
                    <i class="fas ${paymentStatus.icon} ${paymentStatus.class} text-sm"></i>
                    <span class="font-semibold text-xs ${paymentStatus.class}">${paymentStatus.text}</span>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
                <!-- Client Information -->
                <div class="space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 border-b pb-2">
                        Client Information
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Full Name:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${booking.full_name || 'Not provided'}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Contact Number:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${contactNumber}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Celebrant:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${booking.celebrant_name || 'Not provided'}</span>
                        </div>
                        ${booking.event_type === 'birthday' && booking.celebrant_age ? `
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Age:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${booking.celebrant_age} years old</span>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Event Details -->
                <div class="space-y-4">
                    <h3 class="text-base font-semibold text-gray-900 border-b pb-2">
                        Event Details
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Event Type:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1 capitalize">${booking.event_type || 'Not specified'}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Date:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${formattedDate}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Time:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${startTime12} - ${endTime12}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Location:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${booking.location || 'To be confirmed'}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600 w-32">Guests:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1">${booking.guest_count || '0'} persons</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Package Section -->
            <div class="space-y-4">
                <h3 class="text-base font-semibold text-gray-900 border-b pb-2">
                    Service Package
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-600">Package Type:</span>
                                <span class="font-semibold text-[#DC2626] capitalize">${booking.food_package || 'Not specified'}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-600">Event Theme:</span>
                                <span class="font-semibold text-gray-900 capitalize">${booking.event_theme === 'custom' ? (booking.custom_theme || 'Custom Theme') : (booking.event_theme || 'Not specified')}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${booking.theme_suggestions ? `
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <div class="flex justify-between items-start">
                            <span class="font-medium text-gray-600">Special Requests:</span>
                            <span class="font-semibold text-gray-900 text-right flex-1 ml-4">${booking.theme_suggestions}</span>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>

            <!-- Pricing Section -->
            <div class="space-y-3">
                <h3 class="text-base font-semibold text-gray-900 border-b pb-2">
                    Payment Summary
                </h3>
                <div class="text-center">
                    <div class="mb-2">
                        <div class="text-2xl font-bold text-[#DC2626]">${priceDisplay}</div>
                        <div class="text-xs text-gray-600 mt-2">${priceNote}</div>
                    </div>
                    <div class="text-xs text-gray-500">
                        For ${booking.guest_count || '0'} guests • Inclusive of service charges
                    </div>
                </div>
            </div>

            <!-- Important Information -->
            <div class="space-y-3">
                <h4 class="text-base font-semibold text-gray-900 border-b pb-2">Important Information</h4>
                <div class="grid grid-cols-1 gap-3 text-xs text-gray-600">
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-check text-green-500 mt-0.5 text-xs"></i>
                        <span>This is an official booking confirmation Book Preview</span>
                    </div>
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-check text-green-500 mt-0.5 text-xs"></i>
                        <span>Final pricing may be adjusted based on requirements</span>
                    </div>
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-check text-green-500 mt-0.5 text-xs"></i>
                        <span>Payment instructions provided upon approval</span>
                    </div>
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-check text-green-500 mt-0.5 text-xs"></i>
                        <span>Please present this Book Preview for verification</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center border-t border-gray-200 pt-6">
                <div class="flex justify-center items-center space-x-6 mb-3 text-xs text-gray-500">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-phone"></i>
                        <span>+63 912 345 6789</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-envelope"></i>
                        <span>info@zafskitchen.com</span>
                    </div>
                </div>
                <p class="text-xs text-gray-400">
                    This is an electronically generated Book Preview • Valid without signature<br>
                    Thank you for choosing Zaf's Kitchen Catering Services
                </p>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

// Close preview modal
function closepreviewModal() {
    const modal = document.getElementById('preview-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Print preview
function printpreview() {
    const content = document.getElementById('preview-content');
    if (!content) return;
    
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Booking preview</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
    printWindow.document.write('.bg-gray-50 { background-color: #f9fafb; }');
    printWindow.document.write('.p-4 { padding: 1rem; }');
    printWindow.document.write('.rounded-lg { border-radius: 0.5rem; }');
    printWindow.document.write('.space-y-2 > * + * { margin-top: 0.5rem; }');
    printWindow.document.write('.space-y-3 > * + * { margin-top: 0.75rem; }');
    printWindow.document.write('.grid-cols-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// preview Modal functionality
document.getElementById('close-preview-modal')?.addEventListener('click', closepreviewModal);
document.getElementById('close-preview-btn')?.addEventListener('click', closepreviewModal);
document.getElementById('print-preview')?.addEventListener('click', printpreview);

// Close preview modal when clicking outside
document.getElementById('preview-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closepreviewModal();
    }
});

// ========================================
// COMPLETE MENU PACKAGES DATA
// ========================================

const menuPackages = {
    
    // BIRTHDAY PACKAGES
    silver: {
        name: "Silver Package",
        category: "Birthday & Events",
        image: "Catering_Photos/red_silver_package.jpg",
        priceRange: "₱23,000 - ₱50,000",
        description: "Perfect for intimate celebrations with complete buffet setup and themed decorations.",
        
        catering: ["Rice", "3 Main Courses", "1 Vegetable", "1 Pasta", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Themed Backdrop Design", "Cake Table", "Souvenir Rack"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 23000 },
            { pax: 40, price: 27000 },
            { pax: 50, price: 30000 },
            { pax: 60, price: 33000 },
            { pax: 70, price: 36000 },
            { pax: 80, price: 40000 },
            { pax: 90, price: 45000 },
            { pax: 100, price: 50000 }
        ]
    },
    
    gold: {
        name: "Gold Package",
        category: "Birthday & Events",
        image: "Catering_Photos/red_gold_package.jpg",
        priceRange: "₱41,000 - ₱69,000",
        description: "Enhanced package with professional host, lights & sounds, and photographer or photobooth.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Backdrop Design", "Cake Table", "Souvenir Rack"],
        
        otherInclusions: ["Host/Magician", "Lights & Sounds", "Photographer or Photobooth"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 41000 },
            { pax: 40, price: 45000 },
            { pax: 50, price: 48000 },
            { pax: 60, price: 52000 },
            { pax: 70, price: 56000 },
            { pax: 80, price: 60000 },
            { pax: 90, price: 64000 },
            { pax: 100, price: 69000 }
        ]
    },
    
    platinum: {
        name: "Platinum Package",
        category: "Birthday & Events",
        image: "Catering_Photos/red_platinum_package.jpg",
        priceRange: "₱48,000 - ₱82,000",
        description: "Premium package with Tiffany chairs, lighted entrance arch, welcome board, and basic balloon ceilings.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Backdrop Design", "Cake Table", "Souvenir Rack", "Tiffany Chairs"],
        
        otherInclusions: ["Host/Magician", "Lights & Sounds", "Photographer or Photobooth", "Lighted Entrance Arch", "Welcome Board", "Basic Balloon Ceilings"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Tiffany Chairs", image: "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 52000 },
            { pax: 40, price: 54000 },
            { pax: 50, price: 58000 },
            { pax: 60, price: 63000 },
            { pax: 70, price: 67000 },
            { pax: 80, price: 72000 },
            { pax: 90, price: 77000 },
            { pax: 100, price: 82000 }
        ]
    },
    
    diamond: {
        name: "Diamond Package",
        category: "Birthday & Events",
        image: "Catering_Photos/red_diamond_package.jpg",
        priceRange: "₱67,000 - ₱97,000",
        description: "Ultimate package with elegant balloon ceilings, complete entertainment, and premium styling.",
        
        catering: ["Rice", "3 Main Courses", "1 Vegetable", "1 Pasta", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Themed Backdrop Design", "Cake Table", "Souvenir Rack"],
        
        otherInclusions: ["Host/Magician", "Lights & Sounds", "Photographer or Photobooth", "Lighted Entrance Arch", "Welcome Board", "Elegant Balloon Ceilings"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Tiffany Chairs", image: "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 67000 },
            { pax: 40, price: 69000 },
            { pax: 50, price: 73000 },
            { pax: 60, price: 78000 },
            { pax: 70, price: 82000 },
            { pax: 80, price: 87000 },
            { pax: 90, price: 92000 },
            { pax: 100, price: 97000 }
        ]
    },
    
    // WEDDING PACKAGES
    basic_wedding: {
        name: "Basic Wedding Package",
        category: "Wedding",
        image: "Catering_Photos/basic_wedding_package.jpg",
        priceRange: "₱42,000 - ₱75,000",
        description: "Beautiful wedding catering with elegant styling and complete setup.",
        
        catering: ["Rice", "Soup", "Appetizer", "3 Main Courses", "1 Vegetable Dish", "1 Pasta", "Dessert", "Juice/Water"],
        
        styling: ["Backdrop Design", "Couple Couch", "Tables with Centerpieces", "Chairs and Buffet Setup", "Reveal Arch", "Red Carpet", "Couple Table", "Cake Table", "Gift Table"],
        
        inclusions: ["Uniformed Attendants", "Complete Catering Equipment"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 42000 },
            { pax: 40, price: 47000 },
            { pax: 50, price: 50000 },
            { pax: 60, price: 55000 },
            { pax: 70, price: 60000 },
            { pax: 80, price: 65000 },
            { pax: 90, price: 70000 },
            { pax: 100, price: 75000 }
        ]
    },
    
    premium_wedding: {
        name: "Premium Wedding Package",
        category: "Wedding",
        image: "Catering_Photos/premium_wedding_package.jpg",
        priceRange: "₱130,000 - ₱165,000",
        description: "Ultimate wedding with host, lights & sounds, hair & makeup, coordination, and photo/video coverage (SDE).",
        
        catering: ["Rice", "Soup", "Appetizer", "3 Main Courses", "1 Vegetable Dish", "1 Pasta", "1 Dessert", "Juice/Water"],
        
        styling: ["Backdrop Design", "Couple Couch", "Tables with Centerpieces", "Chairs and Buffet Setup", "Reveal Arch", "Red Carpet", "Couple Table", "Cake Table", "Gift Table"],
        
        otherInclusions: ["Host", "Lights & Sounds + Projector", "Hair & Makeup Artist", "On-the-day Coordination", "Photovideo Coverage (SDE)", "Uniformed Attendants", "Complete Catering Equipment"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 50, price: 130000 },
            { pax: 60, price: 135000 },
            { pax: 70, price: 145000 },
            { pax: 80, price: 155000 },
            { pax: 90, price: 160000 },
            { pax: 100, price: 165000 }
        ]
    },
    
    // DEBUT PACKAGES
    silver_debut: {
        name: "Silver Debut Package",
        category: "Debut (18th Birthday)",
        image: "Catering_Photos/silver_debut_package.jpg",
        priceRange: "₱24,000 - ₱52,000",
        description: "Perfect debut package with essential styling and complete catering.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Backdrop Design", "Cake Table", "Souvenir Rack", "Number Standee"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 24000 },
            { pax: 40, price: 27000 },
            { pax: 50, price: 30000 },
            { pax: 60, price: 34000 },
            { pax: 70, price: 38000 },
            { pax: 80, price: 42000 },
            { pax: 90, price: 47000 },
            { pax: 100, price: 52000 }
        ]
    },
    
    gold_debut: {
        name: "Gold Debut Package",
        category: "Debut (18th Birthday)",
        image: "Catering_Photos/gold_debut_package.jpg",
        priceRange: "₱43,000 - ₱72,000",
        description: "Enhanced debut with host, lights & sounds, and photographer from preparation to reception.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Backdrop Design", "Cake Table", "Souvenir Rack", "Number Standee"],
        
        otherInclusions: ["Host", "Lights & Sounds", "Photographer (Preparation to Reception)"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 43000 },
            { pax: 40, price: 46000 },
            { pax: 50, price: 49000 },
            { pax: 60, price: 53000 },
            { pax: 70, price: 57000 },
            { pax: 80, price: 62000 },
            { pax: 90, price: 67000 },
            { pax: 100, price: 72000 }
        ]
    },
    
    platinum_debut: {
        name: "Platinum Debut Package",
        category: "Debut (18th Birthday)",
        image: "Catering_Photos/platinum_debut_package.jpg",
        priceRange: "₱54,000 - ₱86,000",
        description: "Ultimate debut with Tiffany chairs and photo/video coverage (Non-SDE).",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Styro Name", "Celebrant Chair", "Backdrop Design", "Cake Table", "Souvenir Rack", "Number Standee", "Tiffany Chairs"],
        
        otherInclusions: ["Host", "Lights & Sounds", "Photo/Video Coverage (Non-SDE)", "Preparation to Reception"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Tiffany Chairs", image: "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 54000 },
            { pax: 40, price: 57000 },
            { pax: 50, price: 61000 },
            { pax: 60, price: 65000 },
            { pax: 70, price: 70000 },
            { pax: 80, price: 75000 },
            { pax: 90, price: 81000 },
            { pax: 100, price: 86000 }
        ]
    },
    
    // CORPORATE PACKAGES
    silver_corporate: {
        name: "Silver Corporate Package",
        category: "Corporate Events",
        image: "Catering_Photos/silver_corporate_package.jpg",
        priceRange: "₱25,000 - ₱50,000",
        description: "Professional corporate package with complete setup and catering.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Company Name Made with Styro", "Backdrop Design", "Tables & Chairs Setup", "Buffet Setup", "Uniformed Attendants", "Complete Catering Equipment"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 25000 },
            { pax: 40, price: 27000 },
            { pax: 50, price: 30000 },
            { pax: 60, price: 34000 },
            { pax: 70, price: 38000 },
            { pax: 80, price: 42000 },
            { pax: 90, price: 46000 },
            { pax: 100, price: 50000 }
        ]
    },
    
    gold_corporate: {
        name: "Gold Corporate Package",
        category: "Corporate Events",
        image: "Catering_Photos/gold_corporate_package.jpg",
        priceRange: "₱44,000 - ₱69,000",
        description: "Enhanced corporate with host, lights & sounds, and photographer.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Company Name Made with Styro", "Backdrop Design", "Tables & Chairs Setup", "Buffet Setup", "Uniformed Attendants", "Complete Catering Equipment"],
        
        otherInclusions: ["Host", "Lights & Sounds", "Photographer"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Chairs", image: "https://images.unsplash.com/photo-1519167758481-83f29da8c799?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 44000 },
            { pax: 40, price: 46000 },
            { pax: 50, price: 49000 },
            { pax: 60, price: 53000 },
            { pax: 70, price: 57000 },
            { pax: 80, price: 61000 },
            { pax: 90, price: 65000 },
            { pax: 100, price: 69000 }
        ]
    },
    
    platinum_corporate: {
        name: "Platinum Corporate Package",
        category: "Corporate Events",
        image: "Catering_Photos/platinum_corporate_package.jpg",
        priceRange: "₱50,000 - ₱80,000",
        description: "Premium corporate with Tiffany chairs, entrance arch, and welcome board.",
        
        catering: ["Rice", "Pasta", "3 Main Courses", "1 Vegetable Dish", "Juice/Water", "Dessert"],
        
        inclusions: ["Company Name Made with Styro", "Backdrop Design", "Tables & Tiffany Chairs Setup", "Buffet Setup", "Uniformed Attendants", "Complete Catering Equipment"],
        
        otherInclusions: ["Host", "Lights & Sounds", "Photographer", "Entrance Arch & Welcome Board"],
        
        photos: [
            { name: "Buffet Setup", image: "https://images.unsplash.com/photo-1555244162-803834f70033?w=400" },
            { name: "Tables & Tiffany Chairs", image: "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=400" },
            { name: "Complete Equipment", image: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=400" }
        ],
        
        rates: [
            { pax: 30, price: 50000 },
            { pax: 40, price: 53000 },
            { pax: 50, price: 56000 },
            { pax: 60, price: 60000 },
            { pax: 70, price: 65000 },
            { pax: 80, price: 70000 },
            { pax: 90, price: 75000 },
            { pax: 100, price: 80000 }
        ]
    }
};

// Initialize Menu Package Functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeMenuPackages();
});

function initializeMenuPackages() {
    // Add click event to all package cards
    const packageCards = document.querySelectorAll('.package-card');
    packageCards.forEach(card => {
        const viewBtn = card.querySelector('.view-menu-btn');
        viewBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const packageType = card.getAttribute('data-package');
            openMenuModal(packageType);
        });
    });

    // Close modal button
    const closeBtn = document.getElementById('close-menu-modal');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMenuModal);
    }

    // Close modal when clicking outside
    const modal = document.getElementById('menu-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeMenuModal();
            }
        });
    }
}

function openMenuModal(packageType) {
    const modal = document.getElementById('menu-modal');
    const packageData = menuPackages[packageType];
    
    if (!packageData || !modal) return;

    // Store current package type for booking
    modal.setAttribute('data-current-package', packageType);

    // Update modal header
    document.getElementById('modal-package-name').textContent = packageData.name;
    document.getElementById('modal-package-price').textContent = packageData.priceRange;

    // Update description
    document.getElementById('modal-description').innerHTML = `
        <p class="text-gray-700 leading-relaxed">${packageData.description}</p>
    `;

    // Update menu items
    const menuItemsContainer = document.getElementById('modal-menu-items');
    menuItemsContainer.innerHTML = '';
    
    // Combine all inclusions for display
    const allInclusions = [
        ...(packageData.catering || []),
        ...(packageData.inclusions || []),
        ...(packageData.otherInclusions || []),
        ...(packageData.styling || [])
    ];

    allInclusions.forEach(item => {
        const menuItem = document.createElement('div');
        menuItem.className = 'menu-item-card';
        menuItem.innerHTML = `
            <div class="content">
                <h5>${item}</h5>
                <p>Included in package</p>
            </div>
        `;
        menuItemsContainer.appendChild(menuItem);
    });

    // Update inclusions
    const inclusionsContainer = document.getElementById('modal-inclusions');
    inclusionsContainer.innerHTML = '';
    
    allInclusions.forEach(inclusion => {
        const li = document.createElement('li');
        li.innerHTML = `<i class="fas fa-check mr-2"></i>${inclusion}`;
        inclusionsContainer.appendChild(li);
    });

    // Show modal with animation
    modal.classList.remove('hidden');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeMenuModal() {
    const modal = document.getElementById('menu-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

function navigateToBooking() {
    const modal = document.getElementById('menu-modal');
    const selectedPackage = modal ? modal.getAttribute('data-current-package') : null;
    
    closeMenuModal();
    
    // Navigate to Book Now section
    hideAllSections();
    document.querySelectorAll("nav a").forEach(l => l.classList.remove("active-nav"));
    
    // Find and activate the Book Now nav item (assuming it's the first nav item)
    const navLinks = document.querySelectorAll("nav a");
    if (navLinks[0]) {
        navLinks[0].classList.add("active-nav");
    }
    
    // Show Book Now section
    document.getElementById("section-book").classList.remove("hidden");
    
    // If a package was selected, auto-select it in the form
    if (selectedPackage) {
        const packageSelect = document.getElementById('package');
        if (packageSelect) {
            packageSelect.value = selectedPackage;
            updatePriceCalculator();
        }
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ========================================
// ESSENTIAL FUNCTIONS
// ========================================

// Format time to 12-hour format
function formatTimeTo12Hour(time24) {
    const [hours, minutes] = time24.split(':');
    let hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    hour = hour ? hour : 12; // the hour '0' should be '12'
    return `${hour}:${minutes} ${ampm}`;
}

// Show message function
function showMessage(type, title, message) {
    // You can implement a toast notification system here
    console.log(`${type.toUpperCase()}: ${title} - ${message}`);
    alert(`${title}: ${message}`);
}

// Load my bookings function
function loadMyBookings() {
    const container = document.getElementById('bookings-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="text-center py-8">
            <div class="loading-spinner mx-auto"></div>
            <p class="text-gray-600 mt-2">Loading your bookings...</p>
        </div>
    `;
    
fetch('?action=get_my_bookings')
    .then(response => response.json())
    .then(bookings => {
        displayBookingsWithPrice(bookings);
        
        // ✅ Auto-refresh every 30 seconds
        setTimeout(() => {
            const section = document.getElementById('section-mybookings');
            if (section && !section.classList.contains('hidden')) {
                loadMyBookings();
            }
        }, 300000);
    })
        .catch(error => {
            console.error('Error loading bookings:', error);
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <h3 class="text-lg font-semibold mb-2">Failed to Load Bookings</h3>
                    <p class="text-gray-600 mb-4">Please try again later.</p>
                    <button onclick="loadMyBookings()" class="bg-[#DC2626] hover:bg-[#B91C1C] text-white px-4 py-2 rounded-lg">
                        Retry
                    </button>
                </div>
            `;
        });
}

// Navigation functions
function hideAllSections() {
    const sections = document.querySelectorAll('main > section');
    sections.forEach(section => {
        section.classList.add('hidden');
    });
}

function showBookNowSection() {
    hideAllSections();
    document.getElementById('section-book').classList.remove('hidden');
    
    // Reset form to step 1
    goToStep(1);
    
    // Update navigation
    updateNavigation('section-book');
}

function saveCurrentSection(sectionId) {
    localStorage.setItem('currentSection', sectionId);
}

function updateNavigation(activeSection) {
    // Remove active class from all nav items
    document.querySelectorAll('nav a').forEach(link => {
        link.classList.remove('active-nav');
    });
    
    // Add active class to corresponding nav item
    const navMap = {
        'section-dashboard': 0,
        'section-mybookings': 1,
        'section-book': 0, // Book Now is first nav item
        'section-menu': 2,
        'section-gallery': 3,
        'section-schedule': 4,
        'section-settings': 5,
        'section-about': 6
    };
    
    const navIndex = navMap[activeSection];
    const navLinks = document.querySelectorAll('nav a');
    if (navLinks[navIndex]) {
        navLinks[navIndex].classList.add('active-nav');
    }
}

// Initialize dashboard
function initializeDashboard() {
    // Load booking statistics
    loadBookingStats();
    
    // Set up navigation
    setupNavigation();
    
    // Initialize form functionality
    initializeBookingForm();
    
    // Initialize calendar
    initializeCalendar();
    
    // Load initial bookings if on my bookings page
    if (!document.getElementById('section-mybookings').classList.contains('hidden')) {
        loadMyBookings();
    }
}

function loadBookingStats() {
    fetch('?action=get_booking_stats')
        .then(response => response.json())
        .then(stats => {
            if (!stats.error) {
                document.getElementById('total-bookings').textContent = stats.total_bookings;
                document.getElementById('upcoming-events').textContent = stats.upcoming_events;
                document.getElementById('total-spent').textContent = `₱${stats.total_spent.toLocaleString()}`;
            }
        })
        .catch(error => console.error('Error loading stats:', error));
}

function setupNavigation() {
    // Navigation click handlers
    document.querySelectorAll('nav a').forEach((link, index) => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all
            document.querySelectorAll('nav a').forEach(l => l.classList.remove('active-nav'));
            // Add active to clicked
            this.classList.add('active-nav');
            
            hideAllSections();
            
            switch(index) {
                case 0: // Book Now
                    document.getElementById('section-book').classList.remove('hidden');
                    goToStep(1);
                    break;
                case 1: // My Bookings
                    document.getElementById('section-mybookings').classList.remove('hidden');
                    loadMyBookings();
                    break;
                case 2: // Menu Packages
                    document.getElementById('section-menu').classList.remove('hidden');
                    break;
                case 3: // Gallery
                    document.getElementById('section-gallery').classList.remove('hidden');
                    break;
                case 4: // Available Schedule
                    document.getElementById('section-schedule').classList.remove('hidden');
                    loadCalendar();
                    break;
                case 5: // Profile Settings
                    document.getElementById('section-settings').classList.remove('hidden');
                    loadBookingStats();
                    break;
                case 6: // About Us
                    document.getElementById('section-about').classList.remove('hidden');
                    break;
            }
            
            // Close mobile menu if open
            closeMobileMenu();
        });
    });
}

function initializeBookingForm() {
    // Event type change handler
    const eventTypeSelect = document.getElementById('eventtype');
    const ageField = document.getElementById('age-field');
    
    if (eventTypeSelect && ageField) {
        eventTypeSelect.addEventListener('change', function() {
            if (this.value === 'birthday') {
                ageField.classList.remove('hidden');
            } else {
                ageField.classList.add('hidden');
                document.getElementById('celebrant-age').value = '';
            }
        });
    }
    
    // Package and guest count change handlers
    const packageSelect = document.getElementById('package');
    const guestCountSelect = document.getElementById('guest-count');
    
    if (packageSelect) {
        packageSelect.addEventListener('change', updatePriceCalculator);
    }
    if (guestCountSelect) {
        guestCountSelect.addEventListener('change', updatePriceCalculator);
    }
    
    // Menu item change handlers
    document.querySelectorAll('input[name^="menu_"]').forEach(checkbox => {
        checkbox.addEventListener('change', updatePriceCalculator);
    });
    
    // Theme button handlers
    document.querySelectorAll('.theme-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.theme-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            this.classList.add('selected');
            
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                
                // Show/hide custom theme input
                const customThemeInput = document.getElementById('custom-theme');
                if (customThemeInput) {
                    if (radio.value === 'custom') {
                        customThemeInput.classList.remove('hidden');
                    } else {
                        customThemeInput.classList.add('hidden');
                        customThemeInput.value = '';
                    }
                }
            }
        });
    });
    
    // Step navigation
    document.getElementById('next-step1')?.addEventListener('click', () => goToStep(2));
    document.getElementById('back-step2')?.addEventListener('click', () => goToStep(1));
    document.getElementById('next-step2')?.addEventListener('click', () => goToStep(3));
    document.getElementById('back-step3')?.addEventListener('click', () => goToStep(2));
    
    // Form submission
    const bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBookingForm(this);
        });
    }
}

function submitBookingForm(form) {
    const submitBtn = document.getElementById('submit-booking');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<div class="loading-spinner"></div> Submitting...';
    submitBtn.disabled = true;
    
    const formData = new FormData(form);
    
    // Collect selected menu items
    const selectedMenus = [];
    document.querySelectorAll('input[name^="menu_"]:checked').forEach(checkbox => {
        selectedMenus.push(checkbox.value);
    });
    formData.set('selected_menus', selectedMenus.join(','));
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessModal(data);
            form.reset();
            resetPriceDisplay();
            goToStep(1);
        } else {
            showMessage('error', 'Booking Failed', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('error', 'Network Error', 'Please check your connection and try again.');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Calendar functionality
function initializeCalendar() {
    // Calendar navigation
    document.getElementById('prev-month')?.addEventListener('click', () => changeMonth(-1));
    document.getElementById('next-month')?.addEventListener('click', () => changeMonth(1));
}

let currentCalendarMonth = 1; // January
let currentCalendarYear = 2025;

function loadCalendar() {
    fetch(`?action=get_calendar_data&month=${currentCalendarMonth}&year=${currentCalendarYear}`)
        .then(response => response.json())
        .then(calendarData => {
            generateCalendarGrid(calendarData);
        })
        .catch(error => console.error('Error loading calendar:', error));
}

function generateCalendarGrid(calendarData) {
    // Implementation for calendar grid generation
    console.log('Calendar data:', calendarData);
    // You'll need to implement the actual calendar grid generation here
}

function changeMonth(direction) {
    currentCalendarMonth += direction;
    if (currentCalendarMonth > 12) {
        currentCalendarMonth = 1;
        currentCalendarYear++;
    } else if (currentCalendarMonth < 1) {
        currentCalendarMonth = 12;
        currentCalendarYear--;
    }
    loadCalendar();
}

// Mobile menu functionality
function closeMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('backdrop');
    if (sidebar && backdrop) {
        sidebar.classList.remove('translate-x-0');
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }
}

// ========================================
// EVENT LISTENERS
// ========================================

// Event listeners for modal functionality
document.addEventListener('DOMContentLoaded', function() {
    // Delete modal
    document.getElementById('delete-modal-cancel')?.addEventListener('click', hideDeleteModal);
    document.getElementById('delete-modal-confirm')?.addEventListener('click', function() {
        if (currentDeleteBookingId) {
            deleteBooking(currentDeleteBookingId);
            hideDeleteModal();
        }
    });

    // Refresh bookings
    document.getElementById('refresh-bookings')?.addEventListener('click', loadMyBookings);

    // Mobile menu
    document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('backdrop');
        if (sidebar && backdrop) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            backdrop.classList.remove('hidden');
        }
    });

    // Backdrop click
    document.getElementById('backdrop')?.addEventListener('click', closeMobileMenu);

    // Book package button
    document.getElementById('book-package-btn')?.addEventListener('click', navigateToBooking);

    // Initialize dashboard
    initializeDashboard();

    // Hide loading screen
    setTimeout(() => {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 300);
        }
    }, 1000);
});
</script>
            </body>
            </html>