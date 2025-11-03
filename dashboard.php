<?php
session_start();
require_once 'connection.php';

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

// ✅ Step 1: Check if 2 events already overlap at this exact time (BLOCK IMMEDIATELY)
$overlapStmt = $conn->prepare("
    SELECT COUNT(*) as overlap_count
    FROM bookings 
    WHERE event_date = ? 
    AND booking_status != 'cancelled' 
    AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
");
$overlapStmt->execute([$event_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
$overlapResult = $overlapStmt->fetch();

if ($overlapResult['overlap_count'] >= 2) {
    echo json_encode([
        'success' => false, 
        'message' => 'Time conflict: Maximum 2 events can overlap at the same time. Please choose a different time slot.',
        'clear_time' => true
    ]);
    exit;
}

// ✅ Step 2: If exactly 2 events exist on this date, check 4-hour gap rule
$totalEventsStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE event_date = ? AND booking_status != 'cancelled'");
$totalEventsStmt->execute([$event_date]);
$totalEvents = $totalEventsStmt->fetch()['total'];

if ($totalEvents == 2) {
    // Get all existing events for this date
    $allEventsStmt = $conn->prepare("SELECT start_time, end_time FROM bookings WHERE event_date = ? AND booking_status != 'cancelled' ORDER BY start_time");
    $allEventsStmt->execute([$event_date]);
    $existingEvents = $allEventsStmt->fetchAll();
    
    $newStart = strtotime("2000-01-01 $start_time");
    $newEnd = strtotime("2000-01-01 $end_time");
    $fourHoursInSeconds = 4 * 3600; // 4 hours = 14400 seconds
    
    $hasValidGap = false;
    $nearestGap = PHP_INT_MAX;
    
    foreach ($existingEvents as $event) {
        $existingStart = strtotime("2000-01-01 " . $event['start_time']);
        $existingEnd = strtotime("2000-01-01 " . $event['end_time']);
        
        // Calculate gaps (in seconds)
        $gapBefore = $existingStart - $newEnd; // Gap between new event END and existing event START
        $gapAfter = $newStart - $existingEnd;  // Gap between existing event END and new event START
        
        // Track the smallest gap for error message
        if ($gapBefore > 0 && $gapBefore < $nearestGap) {
            $nearestGap = $gapBefore;
        }
        if ($gapAfter > 0 && $gapAfter < $nearestGap) {
            $nearestGap = $gapAfter;
        }
        
        // Check if either gap is >= 4 hours
        if ($gapBefore >= $fourHoursInSeconds || $gapAfter >= $fourHoursInSeconds) {
            $hasValidGap = true;
            break;
        }
    }
    
    if (!$hasValidGap) {
        // Format existing times for error message
        $existingTimesFormatted = [];
        foreach ($existingEvents as $event) {
            $start12 = date('g:i A', strtotime("2000-01-01 " . $event['start_time']));
            $end12 = date('g:i A', strtotime("2000-01-01 " . $event['end_time']));
            $existingTimesFormatted[] = "$start12 - $end12";
        }
        $timesStr = implode(' and ', $existingTimesFormatted);
        
        // Calculate how many hours short
        $hoursShort = $nearestGap < PHP_INT_MAX ? round(($fourHoursInSeconds - $nearestGap) / 3600, 1) : 0;
        
        echo json_encode([
            'success' => false,
            'message' => "⚠️ 4-Hour Gap Required!\n\nTwo events already booked on this date:\n$timesStr\n\nYour 3rd event needs at least 4 hours gap before OR after existing events for equipment preparation and cleaning.\n\nCurrent gap: Only " . round($nearestGap / 3600, 1) . " hours (need " . $hoursShort . " more hours)",
            'clear_time' => true
        ]);
        exit;
    }
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
    approved_at,
    created_at,
    updated_at
    FROM bookings 
    ORDER BY event_date DESC, created_at DESC
");
$stmt->execute();
                    $stmt->execute();
                    $bookings = $stmt->fetchAll();
                    
                    echo json_encode($bookings);
                    exit;
                }

// ✅ FIXED: Check conflict with proper 4-hour gap validation
if (isset($_GET['action']) && $_GET['action'] === 'check_conflict') {
    header('Content-Type: application/json');
    
    $event_date = $_GET['event_date'] ?? '';
    $start_time = $_GET['start_time'] ?? '';
    $end_time = $_GET['end_time'] ?? '';
    
    if (!$event_date || !$start_time || !$end_time) {
        echo json_encode(['conflict' => false]);
        exit;
    }
    
    // Force year to 2025
    $date_parts = explode('-', $event_date);
    if (count($date_parts) === 3) {
        $event_date = '2025-' . $date_parts[1] . '-' . $date_parts[2];
    }
    
    // Step 1: Check direct time overlap (max 2 events can overlap)
    $overlapStmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM bookings 
        WHERE event_date = ? 
        AND booking_status != 'cancelled' 
        AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
    ");
    $overlapStmt->execute([$event_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
    $overlapCount = $overlapStmt->fetch()['count'];
    
    // If 2+ events already overlap with this time, conflict immediately
    if ($overlapCount >= 2) {
        // Get the conflicting times for display
        $conflictingStmt = $conn->prepare("
            SELECT start_time, end_time 
            FROM bookings 
            WHERE event_date = ? 
            AND booking_status != 'cancelled' 
            AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?) OR (start_time >= ? AND end_time <= ?))
            ORDER BY start_time
        ");
        $conflictingStmt->execute([$event_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
        $conflicting = $conflictingStmt->fetchAll();
        
        $conflictTimes = [];
        foreach ($conflicting as $c) {
            $s = date('g:i A', strtotime("2000-01-01 " . $c['start_time']));
            $e = date('g:i A', strtotime("2000-01-01 " . $c['end_time']));
            $conflictTimes[] = "$s - $e";
        }
        
        echo json_encode([
            'conflict' => true,
            'reason' => 'overlap',
            'message' => 'Maximum 2 events can overlap. Conflicting times: ' . implode(', ', $conflictTimes),
            'existing_slots' => implode(', ', $conflictTimes)
        ]);
        exit;
    }
    
    // Step 2: Check if 2 events already exist (then check 4-hour gap)
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE event_date = ? AND booking_status != 'cancelled'");
    $totalStmt->execute([$event_date]);
    $totalEvents = $totalStmt->fetch()['total'];
    
    if ($totalEvents == 2) {
        // Get all existing events
        $allEventsStmt = $conn->prepare("SELECT start_time, end_time FROM bookings WHERE event_date = ? AND booking_status != 'cancelled' ORDER BY start_time");
        $allEventsStmt->execute([$event_date]);
        $existingEvents = $allEventsStmt->fetchAll();
        
        $newStart = strtotime("2000-01-01 $start_time");
        $newEnd = strtotime("2000-01-01 $end_time");
        $fourHours = 4 * 3600;
        
        $hasValidGap = false;
        $nearestGap = PHP_INT_MAX;
        
        foreach ($existingEvents as $event) {
            $existingStart = strtotime("2000-01-01 " . $event['start_time']);
            $existingEnd = strtotime("2000-01-01 " . $event['end_time']);
            
            $gapBefore = $existingStart - $newEnd;
            $gapAfter = $newStart - $existingEnd;
            
            // Track smallest gap
            if ($gapBefore > 0 && $gapBefore < $nearestGap) {
                $nearestGap = $gapBefore;
            }
            if ($gapAfter > 0 && $gapAfter < $nearestGap) {
                $nearestGap = $gapAfter;
            }
            
            if ($gapBefore >= $fourHours || $gapAfter >= $fourHours) {
                $hasValidGap = true;
                break;
            }
        }
        
        if (!$hasValidGap) {
            // Format times
            $timesFormatted = [];
            foreach ($existingEvents as $event) {
                $start12 = date('g:i A', strtotime("2000-01-01 " . $event['start_time']));
                $end12 = date('g:i A', strtotime("2000-01-01 " . $event['end_time']));
                $timesFormatted[] = "$start12 - $end12";
            }
            
            $currentGapHours = $nearestGap < PHP_INT_MAX ? round($nearestGap / 3600, 1) : 0;
            
            echo json_encode([
                'conflict' => true,
                'reason' => 'gap',
                'message' => "4-hour gap needed. Existing: " . implode(', ', $timesFormatted) . " (Current gap: {$currentGapHours}hrs)",
                'existing_slots' => implode(', ', $timesFormatted),
                'gap_hours' => $currentGapHours
            ]);
            exit;
        }
    }
    
    // No conflict
    echo json_encode([
        'conflict' => false,
        'existing_slots' => ''
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

// Admin booking approval endpoint
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'approve_booking') {
    header('Content-Type: application/json');
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
    
    $booking_id = trim($_POST['booking_id'] ?? '');
    
    if (empty($booking_id)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
        exit;
    }
    
    try {
        // Get booking details with user email
        $bookingStmt = $conn->prepare("
            SELECT 
                b.id,
                b.full_name,
                b.contact_number,
                b.celebrant_name,
                b.guest_count,
                b.celebrant_age,
                b.event_type,
                b.location,
                b.event_date,
                b.start_time,
                b.end_time,
                b.food_package,
                b.event_theme,
                b.custom_theme,
                b.theme_suggestions,
                b.total_price,
                b.booking_status,
                b.payment_status,
                b.created_at,
                u.email,
                COALESCE(u.name, b.full_name) as name
            FROM bookings b 
            JOIN usertable u ON b.user_id = u.id 
            WHERE b.id = ?
        ");
        $bookingStmt->execute([$booking_id]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }
        
        // Validate email exists
        if (empty($booking['email'])) {
            echo json_encode(['success' => false, 'message' => 'Customer email not found.']);
            exit;
        }
        
        // Update booking status
        $updateStmt = $conn->prepare("
            UPDATE bookings 
            SET booking_status = 'approved', 
                approved_at = CURRENT_TIMESTAMP, 
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $result = $updateStmt->execute([$booking_id]);
        
        if ($result) {
            // Include email function
            require_once 'sendmail.php';
            
            // Send approval email
            $emailSent = sendBookingApprovalEmail($booking);
            
            if ($emailSent) {
                echo json_encode([
                    'success' => true, 
                    'message' => '✅ Booking approved! Email sent to ' . $booking['email']
                ]);
            } else {
                echo json_encode([
                    'success' => true, 
                    'message' => '⚠️ Booking approved but email failed. Check error logs.'
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to approve booking.']);
        }
        
    } catch (Exception $e) {
        error_log("Approval error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

                // Admin booking rejection endpoint
                if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reject_booking') {
                    header('Content-Type: application/json');
                    
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

// ✅ Check event status with dual timers
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
        
        // Get current timestamp
        $currentTimestamp = time();
        
        // Calculate event start and end timestamps
        $eventStartDatetime = $booking['event_date'] . ' ' . $booking['start_time'];
        $eventEndDatetime = $booking['event_date'] . ' ' . $booking['end_time'];
        $eventStartTimestamp = strtotime($eventStartDatetime);
        $eventEndTimestamp = strtotime($eventEndDatetime);
        
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
        if ($booking['booking_status'] === 'approved' && $booking['payment_status'] !== 'paid' && !empty($booking['approved_at'])) {
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
                $remainingSeconds = max(0, $paymentDeadline - $currentTimestamp);
                
                // Calculate event countdown
                $eventCountdown = max(0, $eventStartTimestamp - $currentTimestamp);
                $eventEndCountdown = max(0, $eventEndTimestamp - $currentTimestamp);
                
                echo json_encode([
                    'status' => 'awaiting_payment',
                    'payment_deadline_seconds' => $remainingSeconds,
                    'event_start_seconds' => $eventCountdown,
                    'event_end_seconds' => $eventEndCountdown,
                    'approved_at' => $booking['approved_at'],
                    'current_time' => date('Y-m-d H:i:s')
                ]);
                exit;
            }
        }
        
        // Event is active (paid or pending)
        $eventCountdown = max(0, $eventStartTimestamp - $currentTimestamp);
        $eventEndCountdown = max(0, $eventEndTimestamp - $currentTimestamp);
        
        echo json_encode([
            'status' => $booking['booking_status'] === 'approved' ? 'active' : 'pending',
            'event_start_seconds' => $eventCountdown,
            'event_end_seconds' => $eventEndCountdown,
            'payment_status' => $booking['payment_status'],
            'booking_status' => $booking['booking_status'],
            'current_time' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Event status check error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
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


/* ============================================
   PROFESSIONAL MOBILE RESPONSIVE - ACCURATE & CLEAN
   ============================================ */

@media (max-width: 768px) {
    /* ========== GLOBAL MOBILE ADJUSTMENTS ========== */
    * {
        -webkit-tap-highlight-color: transparent;
    }

    body {
        overflow-x: hidden;
        font-size: 14px;
    }
    
    main {
        padding: 0.75rem;
        padding-top: 65px;
        min-height: 100vh;
    }

    /* ========== MOBILE MENU BUTTON ========== */
    #mobile-menu-btn {
        position: fixed;
        top: 0.75rem;
        left: 0.75rem;
        z-index: 30;
        background-color: #DC2626;
        padding: 0.6rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }

    #mobile-menu-btn i {
        font-size: 1.2rem;
    }

    /* ========== SIDEBAR ========== */
    #sidebar {
        width: 260px;
    }

    #sidebar .p-6 {
        padding: 0.75rem;
    }

    #sidebar img {
        width: 70px;
        height: 65px;
    }

    #sidebar h1 {
        font-size: 1rem;
    }

    #sidebar nav {
        padding: 0.75rem 0.5rem;
    }

    #sidebar nav a {
        font-size: 0.8rem;
        padding: 0.6rem 0.75rem;
        gap: 0.75rem;
    }

    #sidebar nav a i {
        font-size: 1.1rem;
    }

    /* ========== HEADINGS - ACCURATE SIZING ========== */
    h2.text-3xl {
        font-size: 1.4rem !important;
        margin-bottom: 0.5rem;
    }

    h2.text-2xl {
        font-size: 1.25rem !important;
        margin-bottom: 0.5rem;
    }

    h3.text-xl {
        font-size: 1rem !important;
    }

    h3.text-2xl {
        font-size: 1.15rem !important;
    }

    h4.text-lg {
        font-size: 0.9rem !important;
    }

    .text-lg {
        font-size: 0.9rem !important;
    }

    .text-base {
        font-size: 0.85rem !important;
    }

    .text-sm {
        font-size: 0.75rem !important;
    }

    .text-xs {
        font-size: 0.7rem !important;
    }

    /* ========== DASHBOARD ========== */
    #section-dashboard .grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    #section-dashboard img {
        width: 100% !important;
        height: auto !important;
        max-height: 220px;
        object-fit: cover;
    }

    #section-dashboard > div {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* ========== BOOKING FORM - STEP PROGRESS ========== */
    .step-progress {
        flex-direction: row;
        justify-content: space-between;
        padding: 0 0.5rem;
        margin-bottom: 1rem;
        overflow-x: auto;
    }

    .step-item {
        flex-direction: column;
        align-items: center;
        min-width: fit-content;
        flex: 1;
    }

    .step-circle {
        width: 28px !important;
        height: 28px !important;
        font-size: 0.75rem !important;
        margin-bottom: 0.3rem;
        font-weight: 700;
    }

    .step-text {
        font-size: 0.65rem !important;
        text-align: center;
        white-space: nowrap;
        font-weight: 600;
    }

    .step-line {
        width: 40px !important;
        height: 2px;
        margin: 0 0.25rem;
        margin-bottom: 1.2rem;
    }

    /* ========== FORM CONTAINERS ========== */
    .bg-white.p-6.rounded-lg.shadow-lg.border-2 {
        padding: 0.75rem !important;
        border-width: 1px !important;
    }

    .form-step {
        padding: 0.75rem !important;
    }

    .form-step.active {
        animation: slideIn 0.3s ease-out;
    }

    /* ========== FORM LABELS & INPUTS ========== */
    label {
        font-size: 0.8rem !important;
        margin-bottom: 0.3rem !important;
        font-weight: 600;
    }

    label i {
        font-size: 0.75rem !important;
    }

    .form-input,
    input[type="text"],
    input[type="tel"],
    input[type="number"],
    input[type="date"],
    input[type="time"],
    input[type="password"],
    select,
    textarea {
        font-size: 0.8rem !important;
        padding: 0.5rem 0.6rem !important;
        height: auto !important;
        min-height: 38px;
    }

    textarea {
        min-height: 80px !important;
    }

    select {
        background-position: right 0.5rem center;
        background-size: 16px;
    }

    /* Helper text */
    .text-xs.text-gray-500 {
        font-size: 0.65rem !important;
        margin-top: 0.2rem;
    }

    /* ========== GRID LAYOUTS ========== */
    .grid.md\\:grid-cols-2,
    .grid.md\\:grid-cols-3 {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .grid.gap-4 {
        gap: 0.75rem !important;
    }

    .grid.gap-6 {
        gap: 0.75rem !important;
    }

    /* ========== THEME SELECTION BUTTONS ========== */
    .theme-btn {
        padding: 0.6rem !important;
        border-width: 1.5px !important;
    }

    .theme-btn i {
        font-size: 1.8rem !important;
        margin-bottom: 0.3rem;
    }

    .theme-btn .font-semibold {
        font-size: 0.75rem !important;
    }

    .theme-btn .text-xs {
        font-size: 0.65rem !important;
    }

    /* ========== MENU SELECTION ========== */
    .grid.md\\:grid-cols-3.gap-6 {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .font-semibold.text-\\[\\#DC2626\\].mb-3 {
        font-size: 0.85rem !important;
        margin-bottom: 0.5rem !important;
    }

    label.flex.items-center {
        padding: 0.4rem 0.5rem !important;
        font-size: 0.75rem !important;
    }

    label input[type="checkbox"] {
        width: 0.9rem !important;
        height: 0.9rem !important;
        margin-right: 0.5rem !important;
    }

    /* ========== PRICE SUMMARY CARDS ========== */
    #price-summary,
    #price-summary-step2,
    #price-summary-step3 {
        padding: 0.6rem 0.75rem !important;
    }

    #price-summary .text-sm,
    #price-summary-step2 .text-sm,
    #price-summary-step3 .text-sm {
        font-size: 0.7rem !important;
    }

    #total-display,
    #total-display-step2,
    #total-display-step3 {
        font-size: 1.3rem !important;
        font-weight: 700;
    }

    #base-price,
    #base-price-step2,
    #base-price-step3,
    #additional-price,
    #additional-price-step2,
    #additional-price-step3 {
        font-size: 0.75rem !important;
    }

    .price-calculator {
        padding: 0.6rem !important;
    }

    /* ========== BUTTONS - ACCURATE SIZING ========== */
    button,
    .btn {
        font-size: 0.8rem !important;
        padding: 0.6rem 1rem !important;
        min-height: 38px;
        border-radius: 0.5rem;
        font-weight: 600;
    }

    button i {
        font-size: 0.75rem !important;
    }

    /* Step navigation buttons */
    #next-step1,
    #next-step2,
    #back-step2,
    #back-step3,
    #submit-booking {
        font-size: 0.85rem !important;
        padding: 0.65rem 1.25rem !important;
        font-weight: 600;
    }

    /* Button groups */
    .flex.justify-between button {
        min-width: 90px;
        font-size: 0.8rem !important;
    }

    .flex.justify-end button {
        width: 100%;
    }

    .flex.gap-3 button {
        font-size: 0.8rem !important;
    }

    /* ========== EVENT PREVIEW ========== */
    #event-preview {
        font-size: 0.75rem !important;
        padding: 0.6rem;
    }

    #event-preview p {
        margin-bottom: 0.3rem;
        font-size: 0.75rem !important;
    }

    #event-preview i {
        font-size: 0.7rem !important;
    }

    /* ========== CONFLICT WARNING ========== */
    #time-conflict-warning {
        padding: 0.6rem 0.75rem !important;
        font-size: 0.75rem !important;
    }

    #time-conflict-warning i {
        font-size: 0.9rem !important;
    }

    #conflict-details {
        font-size: 0.75rem !important;
    }

    /* ========== MY BOOKINGS SECTION - FIXED LAYOUT ========== */
    
    /* Status Legend */
    .flex.flex-wrap.gap-4.items-center {
        flex-direction: row !important;
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
        align-items: center !important;
        justify-content: flex-start !important;
    }

    .flex.flex-wrap.gap-4.items-center > div {
        width: auto !important;
        flex: 0 0 auto !important;
    }

    .status-badge {
        font-size: 0.6rem !important;
        padding: 2px 6px !important;
        font-weight: 700;
        white-space: nowrap;
    }

    /* ========== UPCOMING EVENTS (IMAGE 1) ========== */
    
    /* Upcoming Events Card Container */
    .booking-card-enhanced {
        padding: 0.65rem !important;
        margin-bottom: 0.75rem;
        position: relative;
        border-radius: 12px;
        overflow: visible; /* Allow elements to overflow */
    }

    /* Header Section - Keep Status Badge at Top Right */
    .booking-card-enhanced > .flex.justify-between:first-child {
        flex-direction: column !important;
        gap: 0.3rem !important;
        margin-bottom: 0.6rem !important;
        position: relative;
    }

    /* Status Badge - TOP RIGHT (Image 1) */
    .booking-card-enhanced .status-badge {
        position: absolute !important;
        top: -0.5rem !important;
        right: -0.5rem !important;
        z-index: 10 !important;
        font-size: 0.65rem !important;
        padding: 3px 8px !important;
        border-radius: 12px !important;
        font-weight: 700 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15) !important;
    }

    /* Booking ID - BOTTOM LEFT (Image 1) */
    .booking-card-enhanced > .flex.justify-between:first-child > .text-right {
        position: absolute !important;
        bottom: -2rem !important;
        left: 0.65rem !important;
        right: auto !important;
        text-align: left !important;
        margin: 0 !important;
    }

    .booking-card-enhanced > .flex.justify-between:first-child > .text-right .text-sm {
        font-size: 0.55rem !important;
        color: #6b7280 !important;
    }

    .booking-card-enhanced > .flex.justify-between:first-child > .text-right .font-mono {
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        color: #374151 !important;
    }

    /* Price Tag - TOP RIGHT (below status) */
    .booking-price-tag {
        position: absolute !important;
        top: 1.5rem !important;
        right: -0.5rem !important;
        font-size: 0.75rem !important;
        padding: 0.3rem 0.6rem !important;
        z-index: 9 !important;
    }

    /* Event Title Section */
    .booking-card-enhanced .flex.items-center.gap-3 {
        flex-wrap: wrap !important;
        gap: 0.3rem !important;
        padding-right: 5rem !important; /* Space for price tag */
        margin-bottom: 2rem !important; /* Space for booking ID */
    }

    .booking-card-enhanced .text-xl {
        font-size: 0.95rem !important;
        line-height: 1.2 !important;
    }

    .booking-card-enhanced .text-lg {
        font-size: 0.85rem !important;
    }

    .booking-card-enhanced .text-sm {
        font-size: 0.7rem !important;
    }

    /* Data Grid - SINGLE COLUMN, TIGHT SPACING */
    .booking-card-enhanced .grid.md\\:grid-cols-2 {
        grid-template-columns: 1fr !important;
        gap: 0.25rem !important;
        margin-bottom: 0.5rem !important;
        margin-top: 0.5rem !important;
    }

    .booking-card-enhanced .space-y-2 {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.25rem !important;
    }

    .booking-card-enhanced .space-y-2 > * + * {
        margin-top: 0 !important;
    }

    .booking-card-enhanced .flex.items-center.gap-2 {
        gap: 0.3rem !important;
        padding: 0.15rem 0 !important;
    }

    .booking-card-enhanced i {
        font-size: 0.7rem !important;
        min-width: 0.9rem !important;
    }

    /* Action Buttons - SIDE BY SIDE */
    .booking-card-enhanced .flex.gap-2:has(button) {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 0.4rem !important;
        width: 100% !important;
        margin-top: 0.5rem !important;
    }

    .booking-card-enhanced .flex.gap-2 button {
        width: 100% !important;
        padding: 0.45rem 0.5rem !important;
        font-size: 0.7rem !important;
        white-space: nowrap !important;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
    }

    .booking-card-enhanced .flex.gap-2 button i {
        font-size: 0.65rem !important;
        margin-right: 0.2rem !important;
    }

    /* Status Sections - Compact */
    .booking-card-enhanced > div[class*="bg-gradient-to-r"] {
        padding: 0.5rem !important;
        margin-top: 0.5rem !important;
        border-radius: 0.4rem !important;
    }

    .booking-card-enhanced > div[class*="bg-gradient-to-r"] .flex {
        gap: 0.4rem !important;
    }

    .booking-card-enhanced > div[class*="bg-gradient-to-r"] p,
    .booking-card-enhanced > div[class*="bg-gradient-to-r"] div {
        font-size: 0.7rem !important;
        line-height: 1.3 !important;
    }

    .booking-card-enhanced > div[class*="bg-gradient-to-r"] .font-semibold {
        font-size: 0.75rem !important;
    }

    /* Countdown Timers - Compact */
    .booking-card-enhanced [id^="payment-countdown-"],
    .booking-card-enhanced [id^="event-countdown-"] {
        font-size: 0.8rem !important;
        padding: 0.3rem !important;
    }

    /* Special Requests Section */
    .booking-card-enhanced .p-3.bg-gray-50 {
        padding: 0.5rem !important;
        margin-top: 0.5rem !important;
    }

    /* Refresh Button */
    #refresh-bookings {
        font-size: 0.75rem !important;
        padding: 0.5rem 0.85rem !important;
    }

    /* ========== PROFILE SETTINGS (IMAGE 2) ========== */
    
    /* Profile Card Container */
    #section-settings .bg-white.rounded-lg.shadow-md.p-6 {
        padding: 0.75rem !important;
        position: relative;
    }

    /* Profile Layout - CENTERED COLUMN */
    .flex.flex-col.md\\:flex-row.items-center.gap-6 {
        flex-direction: column !important;
        align-items: center !important;
        gap: 0.6rem !important;
        position: relative;
        text-align: center;
    }

    /* Avatar Section - Centered */
    .flex.flex-col.items-center:has(#profile-avatar) {
        flex-direction: column !important;
        align-items: center !important;
        gap: 0.5rem !important;
        width: 100% !important;
    }

    #profile-avatar {
        width: 80px !important;
        height: 80px !important;
        border-width: 3px !important;
        flex-shrink: 0 !important;
        border-color: #DC2626 !important;
    }

    #change-avatar-btn {
        padding: 0.3rem !important;
        bottom: 2px !important;
        right: 2px !important;
        background: #DC2626 !important;
    }

    #change-avatar-btn i {
        font-size: 0.7rem !important;
    }

    .flex.flex-col.items-center:has(#profile-avatar) p {
        display: block !important;
        font-size: 0.7rem !important;
        color: #6b7280;
        margin-top: 0.3rem;
    }

    /* User Info Section - Centered */
    .flex-1.text-center.md\\:text-left {
        flex: 1 !important;
        text-align: center !important;
        width: 100% !important;
        padding-right: 0 !important;
    }

    .flex.items-center.justify-between {
        flex-direction: column !important;
        align-items: center !important;
        gap: 0.3rem !important;
        width: 100% !important;
    }

    /* Name and Email - Centered */
    #profile-name {
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        line-height: 1.2 !important;
        margin: 0 !important;
        text-align: center;
    }

    #profile-email {
        font-size: 0.75rem !important;
        color: #6b7280 !important;
        margin: 0 !important;
        text-align: center;
    }

    /* Profile Menu Button - TOP RIGHT (Image 2) */
    .relative.self-start {
        position: absolute !important;
        top: 0.75rem !important;
        right: 0.75rem !important;
    }

    #profile-menu-btn {
        padding: 0.35rem !important;
        background: #f3f4f6 !important;
        border-radius: 0.4rem !important;
    }

    #profile-menu-btn svg {
        width: 1.1rem !important;
        height: 1.1rem !important;
    }

    /* Profile Dropdown */
    #profile-dropdown {
        right: 0 !important;
        top: calc(100% + 0.4rem) !important;
        min-width: 150px !important;
        font-size: 0.7rem !important;
    }

    #profile-dropdown button {
        padding: 0.45rem 0.6rem !important;
        font-size: 0.7rem !important;
    }

    #profile-dropdown i,
    #profile-dropdown svg {
        width: 0.9rem !important;
        height: 0.9rem !important;
    }

    /* Statistics Grid - COMPACT 3 COLUMNS (Image 2) */
    .grid.grid-cols-2.md\\:grid-cols-3.gap-4.mt-4 {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.4rem !important;
        margin-top: 0.5rem !important;
        width: 100% !important;
    }

    .grid.grid-cols-2.md\\:grid-cols-3.gap-4 > div {
        padding: 0.4rem 0.3rem !important;
        border-radius: 0.4rem !important;
        text-align: center !important;
    }

    /* Statistics Numbers - Positioned as in Image 2 */
    .grid.grid-cols-2.md\\:grid-cols-3.gap-4 .text-2xl {
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        line-height: 1 !important;
        margin-bottom: 0.2rem !important;
    }

    .grid.grid-cols-2.md\\:grid-cols-3.gap-4 .text-xs {
        font-size: 0.6rem !important;
        line-height: 1.2 !important;
        font-weight: 500 !important;
    }

    /* Clickable Stats Card */
    #upcoming-events-card.cursor-pointer:active {
        transform: scale(0.95);
    }

    /* Next Event Card - BOTTOM LEFT (Image 2) */
    #next-event-card {
        padding: 0.65rem !important;
        margin-top: 0.6rem !important;
        position: relative;
    }

    #next-event-card h4 {
        font-size: 0.85rem !important;
        margin-bottom: 0.4rem !important;
        font-weight: 600 !important;
        text-align: center;
    }

    #next-event-card .grid.md\\:grid-cols-2 {
        grid-template-columns: 1fr !important;
        gap: 0.3rem !important;
    }

    #next-event-card .flex.justify-between {
        gap: 0.3rem !important;
        padding: 0.2rem 0 !important;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    #next-event-card .text-sm {
        font-size: 0.65rem !important;
    }

    #next-event-card .text-lg {
        font-size: 0.8rem !important;
    }

    /* Account Information Card - COMPACT */
    #section-settings .bg-white.rounded-lg.shadow-md.p-6.mb-6:last-of-type {
        padding: 0.65rem !important;
        margin-bottom: 0.6rem !important;
    }

    #section-settings .bg-white.rounded-lg.shadow-md.p-6.mb-6 h4 {
        font-size: 0.85rem !important;
        margin-bottom: 0.4rem !important;
        font-weight: 600 !important;
        text-align: center;
    }

    #section-settings .bg-white.rounded-lg.shadow-md.p-6.mb-6 .space-y-3 {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.3rem !important;
    }

    #section-settings .bg-white.rounded-lg.shadow-md.p-6.mb-6 .space-y-3 > * + * {
        margin-top: 0 !important;
    }

    #section-settings .flex.justify-between {
        font-size: 0.7rem !important;
        padding: 0.2rem 0 !important;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.2rem;
    }

    #section-settings .flex.justify-between .text-gray-600 {
        color: #6b7280 !important;
        min-width: auto !important;
    }

    #section-settings .flex.justify-between .font-semibold {
        font-weight: 600 !important;
        text-align: center !important;
    }

    /* ========== CALENDAR ========== */
    
    /* Calendar Navigation */
    .calendar-nav {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 0.5rem;
        padding: 0.75rem 0.5rem;
        margin-bottom: 0.75rem;
        align-items: center;
    }

    .calendar-nav button {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.75rem !important;
        white-space: nowrap;
        min-width: 70px;
    }

    #calendar-title {
        font-size: 1rem !important;
        text-align: center;
        font-weight: 700;
        order: 0;
        grid-column: 1 / -1;
        margin-bottom: 0.5rem;
    }

    #prev-month {
        order: 1;
    }

    #next-month {
        order: 2;
        grid-column: 3;
    }

    /* Calendar Legend - Compact */
    .mb-4.flex.flex-wrap.gap-4.text-sm {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.4rem !important;
        font-size: 0.7rem !important;
        margin-bottom: 0.5rem !important;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: 0.5rem;
    }

    .mb-4.flex.flex-wrap.gap-4 > div {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem;
    }

    .mb-4.flex.flex-wrap.gap-4 .w-4.h-4 {
        width: 12px !important;
        height: 12px !important;
        flex-shrink: 0;
    }

    .mb-4.flex.flex-wrap.gap-4 span {
        font-size: 0.65rem !important;
        line-height: 1.2;
    }

    /* Calendar Header Days */
    .calendar-header {
        gap: 1px;
        margin-bottom: 1px;
    }

    .calendar-header-day {
        font-size: 0.65rem !important;
        padding: 0.5rem 0.2rem !important;
        font-weight: 700;
        letter-spacing: -0.3px;
    }

    /* Calendar Grid */
    .calendar {
        gap: 1px;
        background-color: #cbd5e1;
    }

    .calendar-day {
        min-height: 85px !important;
        padding: 0.3rem !important;
        display: flex;
        flex-direction: column;
        background-color: white;
    }

    .calendar-day.other-month {
        background-color: #f1f5f9;
    }

    .date-number {
        font-size: 0.9rem !important;
        font-weight: 700;
        margin-bottom: 0.2rem;
        color: #1f2937;
    }

    .calendar-day.other-month .date-number {
        color: #9ca3af;
    }

    .booking-slot {
        font-size: 0.58rem !important;
        padding: 2px 3px !important;
        margin: 1px 0 !important;
        line-height: 1.3;
        border-radius: 2px;
        font-weight: 500;
    }

    .booking-count {
        width: 16px !important;
        height: 16px !important;
        font-size: 0.65rem !important;
        top: 3px;
        right: 3px;
        font-weight: 700;
    }

    /* Calendar Status Colors - More Visible */
    .calendar-day.no-bookings,
    .calendar-day.one-booking {
        background-color: #dcfce7 !important;
        border: 1.5px solid #22c55e;
    }

    .calendar-day.two-bookings {
        background-color: #fef3c7 !important;
        border: 1.5px solid #f59e0b;
    }

    .calendar-day.three-bookings,
.calendar-day.unavailable {
        background-color: #fee2e2 !important;
        border: 1.5px solid #ef4444;
    }

    .calendar-day.today {
        box-shadow: inset 0 0 0 2px #3b82f6;
    }

    /* Booking Details Modal */
    #booking-details-modal .modal-content {
        max-width: 95% !important;
    }

    #booking-details-modal h3 {
        font-size: 1rem !important;
    }

    #booking-details-modal .mb-4 {
        margin-bottom: 0.5rem !important;
        padding: 0.5rem !important;
    }

    /* ========== ABOUT US ========== */
    
    .bg-gradient-to-r.from-\\[\\#DC2626\\] {
        padding: 1rem !important;
    }

    .bg-gradient-to-r.from-\\[\\#DC2626\\] .text-3xl {
        font-size: 1.3rem !important;
    }

    .bg-gradient-to-r.from-\\[\\#DC2626\\] .text-lg {
        font-size: 0.9rem !important;
    }

    /* Vision/Mission Cards */
    .bg-white.rounded-lg.shadow-lg.border-2.p-6 {
        padding: 0.75rem !important;
    }

    .bg-\\[\\#DC2626\\].rounded-full.p-3 {
        padding: 0.6rem !important;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bg-\\[\\#DC2626\\].rounded-full.p-3 i {
        font-size: 1.1rem !important;
    }

    /* Core Values Grid */
    .bg-gradient-to-br {
        padding: 0.75rem !important;
    }

    .bg-gradient-to-br i {
        font-size: 2rem !important;
    }

    .bg-gradient-to-br h5 {
        font-size: 0.85rem !important;
    }

    /* Terms & Conditions */
    .flex.items-start .text-\\[\\#DC2626\\] {
        min-width: 1.5rem;
        font-size: 0.85rem !important;
    }

    /* ========== MENU PACKAGES ========== */
    
    .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3 {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .package-card {
        transform: none !important;
        border-width: 1.5px !important;
    }

    .package-card:hover {
        transform: none !important;
    }

    .package-card img {
        height: 160px !important;
    }

    .package-card .p-6 {
        padding: 0.75rem !important;
    }

    .package-card .text-xl {
        font-size: 1rem !important;
    }

    .package-card .text-sm {
        font-size: 0.75rem !important;
    }

    .view-menu-btn {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.75rem !important;
    }

    /* ========== MODALS - ACCURATE SIZING ========== */
    
    /* General Modal Container */
    .fixed.inset-0.bg-black.bg-opacity-50 {
        padding: 0.5rem;
    }

    /* Modal Content Boxes */
    .modal-content,
    #menu-modal > div:not(.hidden),
    #preview-modal > div:not(.hidden),
    #delete-modal > div,
    #password-modal > div,
    #avatar-modal > div,
    #password-popup-modal > div {
        width: calc(100% - 1rem) !important;
        max-width: calc(100% - 1rem) !important;
        margin: 0.5rem auto;
        max-height: 88vh;
        overflow-y: auto;
    }

    /* Modal Headers */
    .modal-content h3,
    #modal-package-name,
    .text-2xl.font-bold {
        font-size: 1.1rem !important;
    }

    /* Modal Content Padding */
    .modal-content .p-6,
    .modal-content .p-8,
    #preview-content {
        padding: 0.75rem !important;
    }

    /* Modal Text */
    .modal-content p,
    .modal-content li,
    .modal-content div {
        font-size: 0.8rem !important;
    }

    /* Menu Modal Specific */
    #modal-menu-items {
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    .menu-item-card {
        overflow: hidden;
    }

    .menu-item-card img {
        height: 90px !important;
    }

    .menu-item-card .content {
        padding: 0.6rem !important;
    }

    .menu-item-card h5 {
        font-size: 0.8rem !important;
    }

    .menu-item-card p {
        font-size: 0.7rem !important;
    }

    /* Invoice Modal */
    #preview-content {
        font-size: 0.75rem !important;
        max-height: 70vh;
    }

    #preview-content .grid.md\\:grid-cols-2 {
        grid-template-columns: 1fr !important;
        gap: 0.5rem !important;
    }

    #preview-content .space-y-2 > * + * {
        margin-top: 0.3rem !important;
    }

    #preview-content .space-y-3 > * + * {
        margin-top: 0.5rem !important;
    }

    #preview-content .text-2xl {
        font-size: 1.2rem !important;
    }

    #preview-content .text-lg {
        font-size: 0.9rem !important;
    }

    #preview-content .text-sm {
        font-size: 0.75rem !important;
    }

    #preview-content .text-xs {
        font-size: 0.65rem !important;
    }

    /* Delete Modal */
    #delete-modal .max-w-md {
        max-width: calc(100% - 1rem) !important;
    }

    #delete-modal h3 {
        font-size: 1rem !important;
    }

    #delete-modal p {
        font-size: 0.8rem !important;
    }

    #delete-modal button {
        font-size: 0.8rem !important;
        padding: 0.6rem 1rem !important;
    }

    /* Avatar Modal */
    #avatar-grid {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 0.5rem !important;
    }

    #avatar-grid > div {
        aspect-ratio: 1;
    }

    /* Password Modal */
    #password-modal input {
        font-size: 0.8rem !important;
        padding: 0.5rem 0.6rem !important;
    }

    #password-modal label {
        font-size: 0.8rem !important;
    }

    #password-modal button {
        font-size: 0.8rem !important;
    }

    /* ========== SPACING ADJUSTMENTS ========== */
    .space-y-6 > * + * {
        margin-top: 0.75rem !important;
    }

    .space-y-4 > * + * {
        margin-top: 0.6rem !important;
    }

    .space-y-3 > * + * {
        margin-top: 0.5rem !important;
    }

    .space-y-2 > * + * {
        margin-top: 0.4rem !important;
    }

    .gap-8 {
        gap: 1rem !important;
    }

    .gap-6 {
        gap: 0.75rem !important;
    }

    .gap-4 {
        gap: 0.6rem !important;
    }

    .gap-3 {
        gap: 0.5rem !important;
    }

    .gap-2 {
        gap: 0.4rem !important;
    }

    .mb-8 {
        margin-bottom: 1rem !important;
    }

    .mb-6 {
        margin-bottom: 0.75rem !important;
    }

    .mb-4 {
        margin-bottom: 0.6rem !important;
    }

    .mb-3 {
        margin-bottom: 0.5rem !important;
    }

    .mb-2 {
        margin-bottom: 0.4rem !important;
    }

    .mt-8 {
        margin-top: 1rem !important;
    }

    .mt-6 {
        margin-top: 0.75rem !important;
    }

    .mt-4 {
        margin-top: 0.6rem !important;
    }

    .mt-3 {
        margin-top: 0.5rem !important;
    }

    .mt-2 {
        margin-top: 0.4rem !important;
    }

    /* ========== UTILITY CLASSES ========== */
    
    /* Icons */
    .fas,
    .far,
    .fab {
        font-size: 0.9rem;
    }

    /* Loading Spinner */
    .loading-spinner {
        width: 14px !important;
        height: 14px !important;
        margin-right: 0.4rem;
    }

    /* Divider Lines */
    .w-full.h-0\\.5 {
        height: 1px !important;
        margin-bottom: 0.75rem !important;
    }

    /* Border Radius */
    .rounded-lg {
        border-radius: 0.5rem !important;
    }

    .rounded-xl {
        border-radius: 0.75rem !important;
    }

    /* Shadows */
    .shadow-lg {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    }

    .shadow-xl {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12) !important;
    }

    /* ========== LANDSCAPE MODE (Mobile) ========== */
    @media (max-height: 500px) and (orientation: landscape) {
        main {
            padding-top: 55px;
        }

        .modal-content,
        #menu-modal > div,
        #preview-modal > div {
            max-height: 92vh;
        }

        .step-progress {
            margin-bottom: 0.5rem;
        }

        .step-circle {
            width: 24px !important;
            height: 24px !important;
            font-size: 0.7rem !important;
        }

        .step-text {
            font-size: 0.6rem !important;
        }

        .calendar-day {
            min-height: 65px !important;
        }
    }
}

/* ========== EXTRA SMALL DEVICES (< 375px) ========== */
@media (max-width: 374px) {
    body {
        font-size: 13px;
    }

    main {
        padding: 0.5rem;
        padding-top: 60px;
    }

    h2.text-2xl {
        font-size: 1.15rem !important;
    }

    .form-input,
    input,
    select,
    textarea {
        font-size: 0.75rem !important;
        padding: 0.45rem 0.5rem !important;
        min-height: 36px;
    }

    button {
        font-size: 0.75rem !important;
        padding: 0.5rem 0.85rem !important;
        min-height: 36px;
    }

    .step-circle {
        width: 26px !important;
        height: 26px !important;
        font-size: 0.7rem !important;
    }

    .step-text {
        font-size: 0.6rem !important;
    }

    .step-line {
        width: 30px !important;
    }

    .booking-card-enhanced {
        padding: 0.6rem !important;
    }

    .calendar-day {
        min-height: 70px !important;
        padding: 0.15rem !important;
    }

    .booking-slot {
        font-size: 0.5rem !important;
    }

    #avatar-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }

    .package-card img {
        height: 140px !important;
    }

    .theme-btn {
        padding: 0.5rem !important;
    }

    .theme-btn i {
        font-size: 1.5rem !important;
    }

    .booking-price-tag {
        font-size: 0.7rem !important;
        padding: 0.25rem 0.5rem !important;
        top: 1.3rem !important;
    }

    #profile-avatar {
        width: 75px !important;
        height: 75px !important;
    }

    .grid.grid-cols-2.md\\:grid-cols-3.gap-4 .text-2xl {
        font-size: 1rem !important;
    }

    .status-badge {
        font-size: 0.55rem !important;
        padding: 2px 5px !important;
    }
}

/* ========== TABLET LANDSCAPE (768px - 1024px) ========== */
@media (min-width: 768px) and (max-width: 1024px) {
    main {
        padding: 1.5rem;
    }

    #mobile-menu-btn {
        display: none;
    }

    .grid.md\\:grid-cols-2 {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid.md\\:grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid.lg\\:grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }

    .calendar-day {
        min-height: 95px;
    }

    .package-card img {
        height: 180px;
    }

    .form-step {
        padding: 1.5rem !important;
    }

    .modal-content,
    #menu-modal > div,
    #preview-modal > div {
        max-width: 90%;
    }
}

/* ========== PRINT STYLES ========== */
@media print {
    #mobile-menu-btn,
    #sidebar,
    nav,
    button:not(#print-preview),
    .no-print,
    #backdrop {
        display: none !important;
    }

    main {
        margin-left: 0 !important;
        padding: 0.5rem !important;
    }

    body {
        font-size: 11pt;
    }

    .booking-card-enhanced,
    #preview-content {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    @page {
        margin: 1cm;
        size: A4;
    }
}

/* ========== TOUCH OPTIMIZATION ========== */
@media (hover: none) and (pointer: coarse) {
    button,
    a,
    input[type="checkbox"],
    input[type="radio"],
    select {
        min-height: 44px;
        min-width: 44px;
    }

    button:active,
    a:active,
    .theme-btn:active {
        opacity: 0.7;
        transform: scale(0.98);
    }

    .calendar-day,
    .booking-card-enhanced,
    .package-card {
        -webkit-transform: translateZ(0);
        transform: translateZ(0);
    }
}

/* ========== HIGH DPI DISPLAYS ========== */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    img {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }

    .package-card img,
    #profile-avatar {
        image-rendering: auto;
    }
}

/* ========== REDUCED MOTION ========== */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }

    .form-step {
        animation: none !important;
        transition: none !important;
    }
}

/* ========== FOCUS VISIBLE ========== */
@media (max-width: 768px) {
    *:focus-visible {
        outline: 2px solid #DC2626;
        outline-offset: 2px;
    }

    button:focus-visible,
    a:focus-visible,
    input:focus-visible,
    select:focus-visible,
    textarea:focus-visible {
        outline: 2px solid #DC2626;
        outline-offset: 2px;
        box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
    }
}

/* ========== SAFE AREA INSETS ========== */
@supports (padding: max(0px)) {
    @media (max-width: 768px) {
        main {
            padding-left: max(0.75rem, env(safe-area-inset-left));
            padding-right: max(0.75rem, env(safe-area-inset-right));
            padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
        }

        #sidebar {
            padding-left: max(1rem, env(safe-area-inset-left));
        }
    }
}

/* ========== NOTCH SUPPORT ========== */
@media (max-width: 768px) {
    @supports (padding-top: env(safe-area-inset-top)) {
        main {
            padding-top: calc(65px + env(safe-area-inset-top));
        }

        #mobile-menu-btn {
            top: calc(0.75rem + env(safe-area-inset-top));
        }
    }
}

/* ========== SCROLLBAR STYLING ========== */
@media (max-width: 768px) {
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: #DC2626;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:active {
        background: #B91C1C;
    }
}

/* ========== SMOOTH SCROLLING ========== */
@media (max-width: 768px) {
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    main section {
        scroll-margin-top: 70px;
    }
}

/* ========== PREVENT TEXT SELECTION ========== */
@media (max-width: 768px) {
    button,
    .theme-btn,
    .package-card,
    .calendar-day {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    input,
    textarea,
    select {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
    }
}

/* ========== PERFORMANCE OPTIMIZATION ========== */
@media (max-width: 768px) {
    .booking-card-enhanced,
    .package-card,
    .calendar-day,
    .modal-content {
        will-change: transform;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
    }

    img {
        content-visibility: auto;
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
                    <img src="Catering_Photos/red_diamond_package.jpg" alt="Diamond Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/gold_corporate_package.jpg" alt="Gold Corporate Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/platinum_corporate_package.jpg" alt="Platinum Corporate Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/basic-wedding-package.jpg" alt="Basic Wedding Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/premium_wedding_package.jpg" alt="Premium Wedding Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/silver_debut_package.jpg" alt="Silver Debut Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/gold_debut_package.jpg" alt="Gold Debut Package" class="w-full h-48 object-cover">
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
                    <img src="Catering_Photos/platinum_debut_package.jpg" alt="Platinum Debut Package" class="w-full h-48 object-cover">
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

<!-- Menu Package Details Modal - ENHANCED -->
<div id="menu-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <!-- Header -->
        <div class="p-6 border-b bg-gradient-to-r from-[#DC2626] to-[#B91C1C]">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <h3 id="modal-package-name" class="text-2xl font-bold text-white mb-2"></h3>
                    <p id="modal-package-price" class="text-lg text-white font-semibold opacity-90"></p>
                </div>
                <button id="close-menu-modal" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            <!-- Package Image -->
            <div class="mb-6">
                <img id="modal-package-image" src="" alt="" class="w-full h-64 object-cover rounded-lg shadow-lg">
            </div>
            
            <!-- Description -->
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fas fa-info-circle text-[#DC2626] text-xl"></i>
                    <h4 class="text-lg font-semibold text-gray-800">Description</h4>
                </div>
                <div id="modal-description" class="text-gray-700 leading-relaxed pl-7"></div>
            </div>
            
            <!-- Inclusions -->
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fas fa-check-circle text-[#DC2626] text-xl"></i>
                    <h4 class="text-lg font-semibold text-gray-800">Package Inclusions</h4>
                </div>
                <div id="modal-inclusions" class="space-y-2 pl-7"></div>
            </div>

            <!-- Guest Selection -->
            <div class="mb-6 bg-gray-50 p-4 rounded-lg border-2 border-[#DC2626]">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fas fa-users text-[#DC2626] text-xl"></i>
                    <h4 class="text-lg font-semibold text-gray-800">Select Number of Guests</h4>
                </div>
                <div id="modal-guest-selection" class="grid grid-cols-2 sm:grid-cols-4 gap-3 pl-7"></div>
                <div id="selected-price-display" class="hidden mt-4 p-3 bg-white rounded-lg border border-[#DC2626]">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Selected Package:</span>
                        <span id="selected-pax-text" class="text-[#DC2626] font-bold"></span>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-gray-700 font-medium">Total Price:</span>
                        <span id="selected-price-text" class="text-2xl text-[#DC2626] font-bold"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer with Book Button -->
        <div class="sticky bottom-0 p-4 border-t bg-white shadow-lg">
            <button id="book-package-btn" disabled class="w-full bg-gray-300 text-gray-500 py-3 px-6 rounded-lg font-semibold text-lg transition-all cursor-not-allowed">
                <i class="fas fa-calendar-plus mr-2"></i>
                Select Guest Count to Continue
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
    
    // ✅ BLOCK 30 & 40 PAX FOR PREMIUM WEDDING
    const guestSelect = document.getElementById('guest-count');
    if (guestSelect) {
        const options = guestSelect.querySelectorAll('option');
        options.forEach(option => {
            const value = parseInt(option.value);
            
            // Disable 30 & 40 for Premium Wedding
            if (packageType === 'premium_wedding' && (value === 30 || value === 40)) {
                option.disabled = true;
                option.textContent = option.textContent.replace(' (Not available)', '') + ' (Not available)';
            } else {
                option.disabled = false;
                option.textContent = option.textContent.replace(' (Not available)', '');
            }
        });
        
        // Reset guest count if currently blocked
        if (packageType === 'premium_wedding' && (guestCount === 30 || guestCount === 40)) {
            guestSelect.value = '';
            resetPriceDisplay();
            showMessage('warning', 'Guest Count Restricted', 
                'Premium Wedding package requires minimum 50 guests. Please select 50 or more.');
            return;
        }
    }
            
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

// Show preview modal - PROFESSIONAL VERSION
function showpreviewModal(booking) {
    const modal = document.getElementById('preview-modal');
    const content = document.getElementById('preview-content');
    
    if (!modal || !content) return;
    
    // Format date and time
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
    
    // Status badges
    const statusConfig = {
        'approved': { class: 'bg-green-100 text-green-800 border-green-200', text: 'CONFIRMED' },
        'pending': { class: 'bg-yellow-100 text-yellow-800 border-yellow-200', text: 'PENDING APPROVAL' },
        'cancelled': { class: 'bg-red-100 text-red-800 border-red-200', text: 'CANCELLED' }
    };
    
    const paymentConfig = {
        'paid': { class: 'bg-green-100 text-green-800 border-green-200', text: 'PAID' },
        'unpaid': { class: 'bg-gray-100 text-gray-800 border-gray-200', text: 'PENDING PAYMENT' }
    };
    
    const status = statusConfig[booking.booking_status] || statusConfig.pending;
    const paymentStatus = paymentConfig[booking.payment_status || 'unpaid'];
    
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Header -->
            <div class="text-center border-b border-gray-200 pb-6">
                <div class="flex justify-center items-center mb-4">
                    <img src="logo/logo-border.png" alt="Zaf's Kitchen" class="w-20 h-20 rounded-full border-4 border-[#DC2626]">
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">ZAF'S KITCHEN CATERING</h1>
                <p class="text-gray-600 text-sm">Professional Event Catering Services</p>
                <p class="text-gray-500 text-xs mt-1">Quezon City, Metro Manila • +63 912 345 6789</p>
            </div>

            <!-- preview Title & Status -->
            <div class="flex justify-between items-start border-b border-gray-200 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">BOOKING CONFIRMATION</h2>
                    <p class="text-sm text-gray-600">preview #${booking.id.toString().padStart(6, '0')}</p>
                    <p class="text-xs text-gray-500">Issued: ${bookedDate}</p>
                </div>
                <div class="text-right space-y-2">
                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full border ${status.class}">
                        ${status.text}
                    </span>
                    <br>
                    <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full border ${paymentStatus.class}">
                        ${paymentStatus.text}
                    </span>
                </div>
            </div>

            <!-- Client Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Client Details</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Full Name:</span>
                            <span class="text-sm font-medium">${booking.full_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Contact Number:</span>
                            <span class="text-sm font-medium">${booking.contact_number || 'N/A'}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Celebrant:</span>
                            <span class="text-sm font-medium">${booking.celebrant_name}</span>
                        </div>
                        ${booking.event_type === 'birthday' && booking.celebrant_age ? `
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Age:</span>
                            <span class="text-sm font-medium">${booking.celebrant_age} years old</span>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Event Details</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Event Type:</span>
                            <span class="text-sm font-medium capitalize">${booking.event_type}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Date:</span>
                            <span class="text-sm font-medium">${formattedDate}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Time:</span>
                            <span class="text-sm font-medium">${startTime12} - ${endTime12}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Guests:</span>
                            <span class="text-sm font-medium">${booking.guest_count} persons</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Details -->
            <div>
                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Service Package</h3>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Package:</span>
                                <span class="text-sm font-medium text-[#DC2626] capitalize">${booking.food_package}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-700">Theme:</span>
                                <span class="text-sm font-medium capitalize">${booking.event_theme === 'custom' ? (booking.custom_theme || 'Custom Theme') : booking.event_theme}</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-700">Location:</span>
                                <span class="text-sm font-medium text-right">${booking.location || 'To be confirmed'}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${booking.theme_suggestions ? `
                    <div class="mt-3 pt-3 border-t border-gray-200">
                        <p class="text-xs font-semibold text-gray-600 uppercase mb-1">Special Requests:</p>
                        <p class="text-sm text-gray-700">${booking.theme_suggestions}</p>
                    </div>
                    ` : ''}
                </div>
            </div>

            <!-- Pricing Section -->
            <div class="border-t border-gray-200 pt-4">
                <div class="bg-gradient-to-r from-[#DC2626] to-[#B91C1C] text-white rounded-lg p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold">Estimated Total</h3>
                            <p class="text-sm opacity-90">${priceNote}</p>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-bold">${priceDisplay}</div>
                            <div class="text-sm opacity-90 mt-1">For ${booking.guest_count} guests</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Important Information -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-sm font-semibold text-blue-800">Important Information</h4>
                        <div class="mt-1 text-sm text-blue-700 space-y-1">
                            <p>• This is a booking confirmation Book Preview</p>
                            <p>• Final pricing may be adjusted based on location and specific requirements</p>
                            <p>• Payment instructions will be provided upon booking approval</p>
                            <p>• Please present this Book Preview for verification</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t border-gray-200 pt-4 text-center">
                <p class="text-xs text-gray-500">
                    Thank you for choosing Zaf's Kitchen Catering Services.<br>
                    For inquiries, please contact us at info@zafskitchen.com or call +63 912 345 6789
                </p>
                <p class="text-xs text-gray-400 mt-2">
                    This is an electronically generated Book Preview. No signature required.
                </p>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
                
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
                            eventEl.innerHTML = '<span class="text-gray-600 font-bold">🎉 EVENT ENDED</span>';
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
    
    // ✅ Initialize countdown timers after DOM update
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
                
                catering: ["Rice tngaaaaaaaaaaaaaaaaa", "3 Main Courses", "1 Vegetable", "1 Pasta", "Juice/Water", "Dessert"],
                
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
                image: "Catering_Photos/basic-wedding-package.jpg",
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

    // Store current package type
    modal.setAttribute('data-current-package', packageType);
    modal.setAttribute('data-selected-pax', ''); // Reset selection

    // Update modal header
    document.getElementById('modal-package-name').textContent = packageData.name;
    document.getElementById('modal-package-price').textContent = packageData.priceRange;
    
    // Update package image
    const packageImage = document.getElementById('modal-package-image');
    if (packageImage) {
        packageImage.src = packageData.image;
        packageImage.alt = packageData.name;
    }

    // Update description with icon
    document.getElementById('modal-description').innerHTML = `
        <p class="text-gray-700 leading-relaxed">${packageData.description}</p>
    `;

    // Update inclusions with icons
    const inclusionsContainer = document.getElementById('modal-inclusions');
    inclusionsContainer.innerHTML = '';
    
    const allInclusions = [
        ...(packageData.catering || []),
        ...(packageData.inclusions || []),
        ...(packageData.otherInclusions || []),
        ...(packageData.styling || [])
    ];

    allInclusions.forEach(inclusion => {
        const div = document.createElement('div');
        div.className = 'flex items-start gap-2 text-gray-700';
        div.innerHTML = `
            <i class="fas fa-check-circle text-[#DC2626] mt-1 flex-shrink-0"></i>
            <span>${inclusion}</span>
        `;
        inclusionsContainer.appendChild(div);
    });

    // Generate guest selection checkboxes
    generateGuestSelection(packageType, packageData.rates);

    // Reset book button
    resetBookButton();

    // Show modal with animation
    modal.classList.remove('hidden');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

// Generate guest selection checkboxes
function generateGuestSelection(packageType, rates) {
    const container = document.getElementById('modal-guest-selection');
    if (!container) return;
    
    container.innerHTML = '';
    
    rates.forEach(rate => {
        const checkbox = document.createElement('label');
        checkbox.className = 'flex items-center gap-2 p-3 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-[#DC2626] hover:bg-red-50 transition-all';
        checkbox.innerHTML = `
            <input type="checkbox" name="modal_guest_count" value="${rate.pax}" data-price="${rate.price}" 
                   class="w-5 h-5 text-[#DC2626] focus:ring-[#DC2626] rounded cursor-pointer">
            <div class="flex-1">
                <div class="font-semibold text-gray-800">${rate.pax} Guests</div>
                <div class="text-sm text-[#DC2626] font-bold">₱${rate.price.toLocaleString()}</div>
            </div>
        `;
        
        // Add change listener
        const input = checkbox.querySelector('input');
        input.addEventListener('change', function() {
            handleGuestSelection(this);
        });
        
        container.appendChild(checkbox);
    });
}

// Handle guest selection (only one can be selected)
function handleGuestSelection(selectedCheckbox) {
    const modal = document.getElementById('menu-modal');
    const allCheckboxes = document.querySelectorAll('input[name="modal_guest_count"]');
    
    // Uncheck all others
    allCheckboxes.forEach(cb => {
        if (cb !== selectedCheckbox) {
            cb.checked = false;
            cb.parentElement.classList.remove('border-[#DC2626]', 'bg-red-50');
            cb.parentElement.classList.add('border-gray-300');
        }
    });
    
    if (selectedCheckbox.checked) {
        // Highlight selected
        selectedCheckbox.parentElement.classList.add('border-[#DC2626]', 'bg-red-50');
        selectedCheckbox.parentElement.classList.remove('border-gray-300');
        
        // Store selection
        const pax = selectedCheckbox.value;
        const price = selectedCheckbox.dataset.price;
        modal.setAttribute('data-selected-pax', pax);
        modal.setAttribute('data-selected-price', price);
        
        // Update price display
        const priceDisplay = document.getElementById('selected-price-display');
        const paxText = document.getElementById('selected-pax-text');
        const priceText = document.getElementById('selected-price-text');
        
        if (priceDisplay && paxText && priceText) {
            priceDisplay.classList.remove('hidden');
            paxText.textContent = `${pax} Guests`;
            priceText.textContent = `₱${parseInt(price).toLocaleString()}`;
        }
        
        // Enable book button
        enableBookButton();
    } else {
        // Reset if unchecked
        modal.setAttribute('data-selected-pax', '');
        modal.setAttribute('data-selected-price', '');
        
        const priceDisplay = document.getElementById('selected-price-display');
        if (priceDisplay) {
            priceDisplay.classList.add('hidden');
        }
        
        resetBookButton();
    }
}

// Enable book button
function enableBookButton() {
    const bookBtn = document.getElementById('book-package-btn');
    if (bookBtn) {
        bookBtn.disabled = false;
        bookBtn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
        bookBtn.classList.add('bg-[#DC2626]', 'hover:bg-[#B91C1C]', 'text-white', 'cursor-pointer');
        bookBtn.innerHTML = '<i class="fas fa-calendar-plus mr-2"></i>Book This Package';
    }
}

// Reset book button
function resetBookButton() {
    const bookBtn = document.getElementById('book-package-btn');
    if (bookBtn) {
        bookBtn.disabled = true;
        bookBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
        bookBtn.classList.remove('bg-[#DC2626]', 'hover:bg-[#B91C1C]', 'text-white', 'cursor-pointer');
        bookBtn.innerHTML = '<i class="fas fa-calendar-plus mr-2"></i>Select Guest Count to Continue';
    }
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
        
        // Find and activate the Book Now nav item (assuming it's the first nav link)
        const bookNavLink = document.querySelector('nav a[href="#"]');
        if (bookNavLink) {
            bookNavLink.classList.add("active-nav");
        }
        
        const bookSection = document.getElementById("section-book");
        if (bookSection) {
            bookSection.classList.remove("hidden");
            
            // Auto-select the package in the booking form
            setTimeout(() => {
                selectPackageInForm(selectedPackage);
            }, 100);
            
            // Scroll to top smoothly
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    }

    // Helper function to show specific package in booking form
    function selectPackageInForm(packageType) {
        const packageSelect = document.getElementById('package');
        if (packageSelect) {
            packageSelect.value = packageType;
            
            // Trigger change event to update price calculator
            const changeEvent = new Event('change', { bubbles: true });
            packageSelect.dispatchEvent(changeEvent);
            
            // Also trigger input event for compatibility
            const inputEvent = new Event('input', { bubbles: true });
            packageSelect.dispatchEvent(inputEvent);
            
            // Force update the price calculator if function exists
            if (typeof updatePriceCalculator === 'function') {
                updatePriceCalculator();
            }
            
            // Visual feedback - highlight the selected package
            packageSelect.classList.add('ring-2', 'ring-[#DC2626]');
            setTimeout(() => {
                packageSelect.classList.remove('ring-2', 'ring-[#DC2626]');
            }, 2000);
            
            // Scroll to the package field
            packageSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

// Book package button click handler
document.addEventListener('DOMContentLoaded', function() {
    const bookPackageBtn = document.getElementById('book-package-btn');
    if (bookPackageBtn) {
        bookPackageBtn.addEventListener('click', function() {
            const modal = document.getElementById('menu-modal');
            const selectedPackage = modal?.getAttribute('data-current-package');
            const selectedPax = modal?.getAttribute('data-selected-pax');
            
            if (!selectedPackage || !selectedPax) {
                showMessage('error', 'Selection Required', 'Please select number of guests first.');
                return;
            }
            
            // Close modal with animation
            closeMenuModal();
            
            // Navigate to Book Now section
            setTimeout(() => {
                navigateToBookingWithPackage(selectedPackage, selectedPax);
            }, 300);
        });
    }
});

// Navigate to booking with pre-filled package and guest count
function navigateToBookingWithPackage(packageType, guestCount) {
    hideAllSections();
    document.querySelectorAll("nav a").forEach(l => l.classList.remove("active-nav"));
    
    // Activate Book Now nav
    const bookNavLink = document.querySelector('nav a');
    if (bookNavLink) bookNavLink.classList.add("active-nav");
    
    const bookSection = document.getElementById("section-book");
    if (bookSection) {
        bookSection.classList.remove("hidden");
        
        // Pre-fill form with animation
        setTimeout(() => {
            // Set guest count
            const guestSelect = document.getElementById('guest-count');
            if (guestSelect) {
                guestSelect.value = guestCount;
                guestSelect.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Highlight with animation
                guestSelect.classList.add('ring-4', 'ring-green-300');
                setTimeout(() => {
                    guestSelect.classList.remove('ring-4', 'ring-green-300');
                }, 2000);
            }
            
            // Set package
            const packageSelect = document.getElementById('package');
            if (packageSelect) {
                packageSelect.value = packageType;
                packageSelect.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Highlight with animation
                packageSelect.classList.add('ring-4', 'ring-green-300');
                setTimeout(() => {
                    packageSelect.classList.remove('ring-4', 'ring-green-300');
                }, 2000);
            }
            
            // Update price calculator
            if (typeof updatePriceCalculator === 'function') {
                updatePriceCalculator();
            }
            
            // Show success message
            showMessage('success', 'Package Selected!', `${guestCount} guests package has been pre-filled. Please complete the remaining details.`);
            
            // Scroll to form
            bookSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
}

// Add keyboard support for modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('menu-modal');
        if (modal && !modal.classList.contains('hidden')) {
            closeMenuModal();
        }
    }
});

    // Export functions for use in other parts of the application
    window.openMenuModal = openMenuModal;
    window.closeMenuModal = closeMenuModal;
    window.navigateToBooking = navigateToBooking;
    window.selectPackageInForm = selectPackageInForm;
        



            // Helper function to format time
            function formatTimeTo12Hour(time24) {
                if (!time24) return '';
                const [hours, minutes] = time24.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour % 12 || 12;
                return `${hour12}:${minutes} ${ampm}`;
            }

            // Global variables
            let currentStep = 1;
            let conflictCheckTimeout = null;
            let currentMonth = new Date().getMonth() + 1;
            let currentYear = 2025;
            let calendarData = {};

            // Mobile menu functionality
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');

            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                backdrop.classList.toggle('hidden');
            }

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
            }
            if (backdrop) {
                backdrop.addEventListener('click', toggleSidebar);
            }

            // Navigation functionality
            const navMap = {
                "Book Now": "section-book",
                "My Bookings": "section-mybookings",
                "Menu Packages": "section-menu",
                "Gallery": "section-gallery",
                "Available Schedule": "section-schedule",
                "Profile Settings": "section-settings",
                "About Us": "section-about"
            };

            function hideAllSections() {
                document.querySelectorAll("main section").forEach(sec => sec.classList.add("hidden"));
            }

            // Save current section to localStorage
            function saveCurrentSection(sectionId) {
                localStorage.setItem('currentSection', sectionId);
            }

        const navLinks = document.querySelectorAll("nav a");
        navLinks.forEach(link => {
            link.addEventListener("click", e => {
                e.preventDefault();
                hideAllSections();
                navLinks.forEach(l => l.classList.remove("active-nav"));
                link.classList.add("active-nav");
                const text = link.innerText.trim();
                const sectionId = navMap[text];
                if (sectionId) {
                    // Save to localStorage
                    saveCurrentSection(sectionId);
                    
                    document.getElementById(sectionId).classList.remove("hidden");
                    
                    if (sectionId === 'section-book') {
                        resetBookingForm();
                    } else if (sectionId === 'section-schedule') {
                        loadCalendar();
                    } else if (sectionId === 'section-mybookings') {
                        loadMyBookings();
                    } else if (sectionId === 'section-settings') {
                        // FIXED: Load stats when Profile Settings is clicked
                        loadBookingStats();
                        loadProfileSettings();
                    }
                }
                document.getElementById("section-dashboard").classList.add("hidden");
                if (window.innerWidth < 1024) toggleSidebar();
            });
        });


            // Initialize
            hideAllSections();
            const dashboardSection = document.getElementById("section-dashboard");
            if (dashboardSection) {
                dashboardSection.classList.remove("hidden");
            }

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
                    
                    // ✅ Auto-refresh every 30 seconds to keep timers updated
                    setTimeout(() => {
                        const section = document.getElementById('section-mybookings');
                        if (section && !section.classList.contains('hidden')) {
                            loadMyBookings();
                        }
                    }, 330000);
                })
                
                fetch(window.location.pathname + '?action=get_my_bookings')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(bookings => {
                        displayBookings(bookings);
                    })
                    .catch(error => {
                        console.error('Error loading bookings:', error);
                        container.innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i>
                                <p class="text-gray-600">Failed to load bookings. Please try again.</p>
                                <button onclick="loadMyBookings()" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                    Retry
                                </button>
                            </div>
                        `;
                    });
            }

            // Display bookings function
            function displayBookings(bookings) {
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
                
                // Separate upcoming and past bookings
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
                
                // Upcoming Bookings Section
                if (upcomingBookings.length > 0) {
                    bookingsHtml += `
                        <div class="mb-8">
                            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                                <i class="fas fa-calendar-week text-[#DC2626]"></i>
                                Upcoming Events (${upcomingBookings.length})
                            </h3>
                            <div class="space-y-4">
                    `;
                    
                    upcomingBookings.forEach(booking => {
                        bookingsHtml += generateBookingCard(booking);
                    });
                    
                    bookingsHtml += `
                            </div>
                        </div>
                    `;
                }
                
                // Past Bookings Section
                if (pastBookings.length > 0) {
                    bookingsHtml += `
                        <div class="mb-8">
                            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                                <i class="fas fa-history text-gray-600"></i>
                                Past Events (${pastBookings.length})
                            </h3>
                            <div class="space-y-4">
                    `;
                    
                    pastBookings.forEach(booking => {
                        bookingsHtml += generateBookingCard(booking, true);
                    });
                    
                    bookingsHtml += `
                            </div>
                        </div>
                    `;
                }
                
                container.innerHTML = bookingsHtml;
            }

            function generateBookingCard(booking, isPast = false) {
                const eventDate = new Date(booking.event_date);
                const formattedDate = eventDate.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                // Convert times to 12-hour format
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
                
                // Create proper border style based on status
                let borderStyle = '';
                let cardOpacity = '';
                
                switch(booking.booking_status) {
                    case 'approved':
                        borderStyle = 'border-l-4 border-l-green-500 border-t-2 border-r-2 border-b-2 border-gray-300';
                        break;
                    case 'pending':
                        borderStyle = 'border-l-4 border-l-yellow-500 border-t-2 border-r-2 border-b-2 border-gray-300';
                        break;
                    case 'cancelled':
                        borderStyle = 'border-l-4 border-l-red-500 border-t-2 border-r-2 border-b-2 border-gray-300';
                        cardOpacity = 'opacity-70';
                        break;
                    default:
                        borderStyle = 'border-2 border-gray-300';
                }
                
                if (isPast) {
                    cardOpacity = cardOpacity ? 'opacity-50' : 'opacity-75';
                }
                
                const statusClass = `status-${booking.booking_status}`;
                const cardClass = `booking-card ${statusClass} bg-white p-6 rounded-lg shadow-lg ${borderStyle} ${cardOpacity}`;
                
                // Determine status icon and message
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
                
                // Age display for birthday events
                const ageDisplay = booking.event_type === 'birthday' && booking.celebrant_age ? 
                    ` (${booking.celebrant_age} years old)` : '';
                
                return `
                    <div class="${cardClass}">
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
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Booking ID</div>
                                <div class="font-mono text-sm">#${booking.id.toString().padStart(4, '0')}</div>
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
                        <div class="mt-3 p-3 bg-gray-50 border-l-4 border-[#DC2626] rounded">
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
                        <div class="mt-4 p-3">
                            <div class="flex items-center gap-2 text-green-800">
                                <i class="fas fa-check-circle"></i>
                                <span class="font-semibold">Event Confirmed!</span>
                            </div>
                            <p class="text-sm text-green-700 mt-1">Your booking has been approved by our admin. We look forward to catering your event!</p>
                        </div>
                        ` : booking.booking_status === 'pending' ? `
                        <div class="mt-4 p-3">
                            <div class="flex items-center gap-2 text-yellow-800">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="font-semibold">Pending Approval</span>
                            </div>
                            <p class="text-sm text-yellow-700 mt-1">We're reviewing your booking. You'll be notified once it's approved!</p>
                        </div>
                        ` : `
                        <div class="mt-4 p-3">
                            <div class="flex items-center gap-2 text-red-800">
                                <i class="fas fa-ban"></i>
                                <span class="font-semibold">Booking Cancelled</span>
                            </div>
                            <p class="text-sm text-red-700 mt-1">This booking was cancelled. Contact us if you have questions.</p>
                        </div>  
                        `) : ''}
                    </div>
                `;
            }

            // Helper function to show Book Now section
            function showBookNowSection() {
                hideAllSections();
                document.querySelectorAll("nav a").forEach(l => l.classList.remove("active-nav"));
                document.querySelector('nav a').classList.add("active-nav");
                document.getElementById("section-book").classList.remove("hidden");
                resetBookingForm();
            }

            // 3-step navigation
            function showStep(step) {
                const steps = ['booking-step1', 'booking-step2', 'booking-step3'];
                const stepIndicators = ['step-1', 'step-2', 'step-3'];
                
                // Hide all steps
                steps.forEach((stepId, index) => {
                    const stepElement = document.getElementById(stepId);
                    if (stepElement) {
                        stepElement.classList.remove('active');
                        stepElement.classList.add('hidden');
                    }
                });
                
                // Show current step
                const currentStepElement = document.getElementById(`booking-step${step}`);
                if (currentStepElement) {
                    currentStepElement.classList.remove('hidden');
                    setTimeout(() => {
                        currentStepElement.classList.add('active');
                    }, 50);
                }
                
                // Update step indicators
                stepIndicators.forEach((stepId, index) => {
                    const stepElement = document.getElementById(stepId);
                    const stepNumber = index + 1;
                    
                    if (stepElement) {
                        stepElement.classList.remove('active', 'completed', 'inactive');
                        
                        if (stepNumber < step) {
                            stepElement.classList.add('completed');
                        } else if (stepNumber === step) {
                            stepElement.classList.add('active');
                        } else {
                            stepElement.classList.add('inactive');
                        }
                    }
                });
                
                // Update step lines
                const stepLines = document.querySelectorAll('.step-line');
                stepLines.forEach((line, index) => {
                    line.classList.remove('active', 'completed', 'inactive');
                    const lineNumber = index + 1;
                    
                    if (lineNumber < step) {
                        line.classList.add('completed');
                    } else if (lineNumber === step) {
                        line.classList.add('active');
                    } else {
                        line.classList.add('inactive');
                    }
                });
                
                currentStep = step;
            }

            // Event type change handler to show/hide age field
            function setupEventTypeHandler() {
                const eventTypeSelect = document.getElementById('eventtype');
                const ageField = document.getElementById('age-field');
                const celebrantAge = document.getElementById('celebrant-age');
                
                if (eventTypeSelect && ageField) {
                    eventTypeSelect.addEventListener('change', function() {
                        if (this.value === 'birthday') {
                            ageField.classList.remove('hidden');
                            celebrantAge.setAttribute('required', 'required');
                        } else {
                            ageField.classList.add('hidden');
                            celebrantAge.removeAttribute('required');
                            celebrantAge.value = '';
                        }
                        updateEventPreview();
                    });
                }
            }

            // Update event preview
            function updateEventPreview() {
                const previewDiv = document.getElementById('event-preview');
                if (!previewDiv) return;
                
                const celebrantName = document.getElementById('celebrant-name')?.value || '';
                const eventType = document.getElementById('eventtype')?.value || '';
                const guestCount = document.getElementById('guest-count')?.value || '';
                const eventDate = document.getElementById('event-date')?.value || '';
                const startTime = document.getElementById('start-time')?.value || '';
                const endTime = document.getElementById('end-time')?.value || '';
                const celebrantAge = document.getElementById('celebrant-age')?.value || '';
                const foodPackage = document.getElementById('package')?.value || '';
                
                if (!celebrantName || !eventType || !guestCount) {
                    previewDiv.innerHTML = '<p>Fill in the details above to see your event preview</p>';
                    return;
                }
                
                let preview = `<div class="space-y-2">`;
                
                if (eventType === 'birthday' && celebrantAge) {
                    preview += `<p><strong>${celebrantName}'s ${celebrantAge}th Birthday Party</strong></p>`;
                } else {
                    preview += `<p><strong>${celebrantName}'s ${eventType.charAt(0).toUpperCase() + eventType.slice(1)}</strong></p>`;
                }
                
                preview += `<p><i class="fas fa-users mr-2"></i>${guestCount} guests expected</p>`;
                
                if (foodPackage) {
                    preview += `<p><i class="fas fa-utensils mr-2"></i>${foodPackage.charAt(0).toUpperCase() + foodPackage.slice(1)} package</p>`;
                }
                
                if (eventDate && startTime && endTime) {
                    const date = new Date(eventDate);
                    const formattedDate = date.toLocaleDateString('en-US', {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    const startTime12 = formatTimeTo12Hour(startTime);
                    const endTime12 = formatTimeTo12Hour(endTime);
                    
                    preview += `<p><i class="fas fa-calendar mr-2"></i>${formattedDate}</p>`;
                    preview += `<p><i class="fas fa-clock mr-2"></i>${startTime12} - ${endTime12}</p>`;
                }
                
                preview += `</div>`;
                previewDiv.innerHTML = preview;
            }

            // Enhanced date input setup
            function setupDateInput() {
                const eventDateInput = document.getElementById('event-date');
                if (eventDateInput) {
                    // Set minimum date (3 days from now, but in 2025)
                    const minDate = new Date();
                    minDate.setDate(minDate.getDate() + 3);
                    const minDateStr = `2025-${String(minDate.getMonth() + 1).padStart(2, '0')}-${String(minDate.getDate()).padStart(2, '0')}`;
                    eventDateInput.min = minDateStr;
                    
                    // Set maximum date to end of 2025
                    eventDateInput.max = '2025-12-31';
                    
                    // Force year to 2025 and validate date
                    eventDateInput.addEventListener('change', function() {
                        if (this.value && this.value.length === 10) {
                            const parts = this.value.split('-');
                            if (parts.length === 3 && parts[0] !== '2025') {
                                this.value = `2025-${parts[1]}-${parts[2]}`;
                            }
                            updateEventPreview();
                        }
                    });
                    
                    eventDateInput.addEventListener('blur', function() {
                        if (this.value && this.value.length === 10) {
                            const parts = this.value.split('-');
                            if (parts.length === 3 && parts[0] !== '2025') {
                                this.value = `2025-${parts[1]}-${parts[2]}`;
                            }
                            
                            // Check if selected date is in the past
                            const selectedDate = new Date(this.value);
                            const minAllowedDate = new Date();
                            minAllowedDate.setDate(minAllowedDate.getDate() + 3);
                            
                            if (selectedDate < minAllowedDate) {
                                showMessage('error', 'Invalid Date', 'Please select a date that is at least 3 days from today.');
                                this.value = '';
                                this.classList.add('border-red-500');
                                return false;
                            } else {
                                this.classList.remove('border-red-500');
                            }
                            updateEventPreview();
                        }
                    });
                }
            }

            // FIXED VALIDATION FUNCTIONS - These prevent step progression when validation fails

            // Enhanced validation for Step 3 - NEW
            function validateStep3() {
                console.log('Validating Step 3...');
                
                // Clear any previous error styling
                document.querySelectorAll('.theme-btn').forEach(btn => {
                    btn.classList.remove('border-red-500');
                });
                
                // Check if a theme is selected
                const selectedTheme = document.querySelector('.theme-btn.selected');
                if (!selectedTheme) {
                    console.log('Step 3 validation failed - no theme selected');
                    showMessage('error', 'Theme Required', 'Please select an event theme before proceeding.');
                    
                    // Add red border to all theme buttons to indicate selection is required
                    document.querySelectorAll('.theme-btn').forEach(btn => {
                        btn.classList.add('border-red-500');
                    });
                    
                    // Scroll to theme selection area
                    const themeSection = document.querySelector('.theme-btn').parentElement;
                    if (themeSection) {
                        themeSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    return false;
                }
                
                // If custom theme is selected, check if custom theme text is provided
                if (selectedTheme.dataset.theme === 'custom') {
                    const customThemeInput = document.getElementById('custom-theme');
                    if (!customThemeInput.value || !customThemeInput.value.trim()) {
                        console.log('Step 3 validation failed - custom theme text missing');
                        customThemeInput.classList.add('border-red-500');
                        showMessage('error', 'Custom Theme Required', 'Please describe your custom theme.');
                        customThemeInput.focus();
                        return false;
                    } else {
                        customThemeInput.classList.remove('border-red-500');
                    }
                }
                
                console.log('Step 3 validation passed');
                return true;
            }

            // Enhanced validation for Step 1 - FIXED
            function validateStep1() {
                console.log('Validating Step 1...');
                
                // Clear any previous error styling
                document.querySelectorAll('input, select, textarea').forEach(field => {
                    field.classList.remove('border-red-500');
                });
                
                const requiredFields = [
                    { name: 'full_name', element: document.querySelector('[name="full_name"]'), label: 'Full Name' },
                    { name: 'contact_number', element: document.querySelector('[name="contact_number"]'), label: 'Contact Number' },
                    { name: 'celebrant_name', element: document.querySelector('[name="celebrant_name"]'), label: 'Celebrant Name' },
                    { name: 'guest_count', element: document.querySelector('[name="guest_count"]'), label: 'Guest Count' },
                    { name: 'food_package', element: document.querySelector('[name="food_package"]'), label: 'Food Package' },
                    { name: 'event_type', element: document.querySelector('[name="event_type"]'), label: 'Event Type' }
                ];
                
                let isValid = true;
                let firstInvalidField = null;
                let errorMessages = [];
                
                // Check required fields
                requiredFields.forEach(field => {
                    if (field.element && (!field.element.value || !field.element.value.trim())) {
                        isValid = false;
                        if (!firstInvalidField) firstInvalidField = field.element;
                        field.element.classList.add('border-red-500');
                        errorMessages.push(field.label + ' is required');
                    }
                });
                
                // Validate guest count - now a dropdown
                const guestCountField = document.querySelector('[name="guest_count"]');
                const guestCount = guestCountField?.value;
                if (!guestCount || guestCount === '') {
                    isValid = false;
                    guestCountField.classList.add('border-red-500');
                    errorMessages.push('Please select number of guests');
                    if (!firstInvalidField) firstInvalidField = guestCountField;
                }
                
                // Validate age for birthday events
                const eventType = document.querySelector('[name="event_type"]')?.value;
                const celebrantAgeField = document.querySelector('[name="celebrant_age"]');
                
                if (eventType === 'birthday') {
                    const celebrantAge = celebrantAgeField?.value;
                    if (!celebrantAge || !celebrantAge.trim()) {
                        isValid = false;
                        celebrantAgeField.classList.add('border-red-500');
                        errorMessages.push('Age is required for birthday events');
                        if (!firstInvalidField) firstInvalidField = celebrantAgeField;
                    } else {
                        const age = parseInt(celebrantAge);
                        if (isNaN(age) || age < 1 || age > 150) {
                            isValid = false;
                            celebrantAgeField.classList.add('border-red-500');
                            errorMessages.push('Please enter a valid age (1-150)');
                            if (!firstInvalidField) firstInvalidField = celebrantAgeField;
                        }
                    }
                }
                
                // Show error message and focus first invalid field
                if (!isValid) {
                    console.log('Step 1 validation failed:', errorMessages);
                    showMessage('error', 'Required Fields Missing', 'Please fill in all required fields:<br>• ' + errorMessages.join('<br>• '));
                    if (firstInvalidField) {
                        firstInvalidField.focus();
                        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                
                console.log('Step 1 validation passed');
                return true;
            }

// Enhanced validation for Step 2 - CORRECTLY FIXED
function validateStep2() {
    console.log('Validating Step 2...');
    
    // Clear any previous error styling
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.classList.remove('border-red-500');
    });
    
    const requiredFields = [
        { name: 'event_date', element: document.querySelector('[name="event_date"]'), label: 'Event Date' },
        { name: 'start_time', element: document.querySelector('[name="start_time"]'), label: 'Start Time' },
        { name: 'end_time', element: document.querySelector('[name="end_time"]'), label: 'End Time' },
        { name: 'location', element: document.querySelector('[name="location"]'), label: 'Event Location' }
    ];
    
    let isValid = true;
    let firstInvalidField = null;
    let errorMessages = [];
    
    // Check required fields
    requiredFields.forEach(field => {
        if (field.element && (!field.element.value || !field.element.value.trim())) {
            isValid = false;
            if (!firstInvalidField) firstInvalidField = field.element;
            field.element.classList.add('border-red-500');
            errorMessages.push(field.label + ' is required');
        }
    });
    
    if (!isValid) {
        console.log('Step 2 validation failed - missing fields:', errorMessages);
        showMessage('error', 'Required Fields Missing', 'Please fill in all required fields:<br>• ' + errorMessages.join('<br>• '));
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false; // Return false directly, not Promise
    }
    
    // Validate date is in future (at least 3 days from today)
    const eventDateField = document.querySelector('[name="event_date"]');
    const eventDate = eventDateField?.value;
    if (eventDate) {
        const selectedDate = new Date(eventDate);
        const minDate = new Date();
        minDate.setDate(minDate.getDate() + 3);
        minDate.setHours(0, 0, 0, 0);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < minDate) {
            console.log('Step 2 validation failed - date too soon');
            eventDateField.classList.add('border-red-500');
            showMessage('error', 'Invalid Date', 'Event date must be at least 3 days from today.');
            eventDateField.focus();
            return false;
        }
    }
    
    // Time validation
    const startTimeField = document.querySelector('[name="start_time"]');
    const endTimeField = document.querySelector('[name="end_time"]');
    const startTime = startTimeField?.value;
    const endTime = endTimeField?.value;
    
    if (startTime && endTime) {
        if (startTime >= endTime) {
            console.log('Step 2 validation failed - invalid time range');
            startTimeField.classList.add('border-red-500');
            endTimeField.classList.add('border-red-500');
            showMessage('error', 'Invalid Time Range', 'End time must be after start time.');
            startTimeField.focus();
            return false;
        }
        
        // Calculate duration
        const start = new Date('2000-01-01 ' + startTime);
        const end = new Date('2000-01-01 ' + endTime);
        const durationHours = (end - start) / (1000 * 60 * 60);
        
        if (durationHours < 4) {
            console.log('Step 2 validation failed - duration too short');
            startTimeField.classList.add('border-red-500');
            endTimeField.classList.add('border-red-500');
            showMessage('error', 'Invalid Duration', 'Event duration must be at least 4 hours.');
            startTimeField.focus();
            return false;
        }
        
        if (durationHours > 8) {
            console.log('Step 2 validation failed - duration too long');
            startTimeField.classList.add('border-red-500');
            endTimeField.classList.add('border-red-500');
            showMessage('error', 'Invalid Duration', 'Event duration cannot exceed 8 hours.');
            startTimeField.focus();
            return false;
        }
    }
    
    // All basic validations passed - now check conflicts
    console.log('Basic validations passed, checking conflicts...');
    return true; // Return true to proceed to conflict check
}











let activeConflict = null; // store the last known conflict

// ------------------------------------------------------------
// CHECK TIME CONFLICT (Callback Version)
// ------------------------------------------------------------
function checkTimeConflictForValidation(callback) {
    const eventDate = document.querySelector('[name="event_date"]').value;
    const startTime = document.querySelector('[name="start_time"]').value;
    const endTime = document.querySelector('[name="end_time"]').value;

    if (!eventDate || !startTime || !endTime) {
        // No values yet → don't hide previous warning
        callback(true);
        return;
    }

    const url = window.location.pathname +
        `?action=check_conflict&event_date=${encodeURIComponent(eventDate)}&start_time=${encodeURIComponent(startTime)}&end_time=${encodeURIComponent(endTime)}`;

    fetch(url)
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(data => {
            if (data.conflict) {
                console.log('⛔ Conflict detected:', data.reason);
                activeConflict = data; // remember the active conflict

                // highlight invalid fields
                document.querySelector('[name="start_time"]').classList.add('border-red-500');
                document.querySelector('[name="end_time"]').classList.add('border-red-500');

                // show the right warning
                if (data.reason === 'gap') {
                    showConflictWarning(data.existing_slots, 'gap', data.gap_hours || 0);
                } else if (data.reason === 'overlap') {
                    showConflictWarning(data.existing_slots, 'overlap');
                } else {
                    showConflictWarning(data.existing_slots, 'conflict');
                }

                callback(false);
            } else {
                console.log('✅ No conflicts detected');
                activeConflict = null; // clear only when resolved
                hideConflictWarning(); // hide now since no more conflicts
                document.querySelector('[name="start_time"]').classList.remove('border-red-500');
                document.querySelector('[name="end_time"]').classList.remove('border-red-500');
                callback(true);
            }
        })
        .catch(err => {
            console.error('⚠️ Conflict check error:', err);
            showMessage(
                'warning',
                '⚠️ Unable to Verify Schedule',
                'We were unable to verify the selected time due to a network or server issue. Please double-check your schedule before proceeding.'
            );
            callback(true);
        });
}

// ------------------------------------------------------------
// SHOW CONFLICT WARNING
// ------------------------------------------------------------
function showConflictWarning(existingSlots, reason = '', gapHours = 0) {
    const warningDiv = document.getElementById('time-conflict-warning');
    const conflictDetails = document.getElementById('conflict-details');
    if (!warningDiv || !conflictDetails) return;

    let message = '';

    if (reason === 'gap') {
        const gapNeeded = Math.max(0, 4 - gapHours).toFixed(1);
        message = `
            <div class="space-y-2">
                <div class="font-bold text-red-700 text-lg">⚠️ 4-Hour Gap Required</div>
                <div class="bg-yellow-50 p-3 rounded border border-yellow-300">
                    <div class="font-semibold text-yellow-800 mb-2">Existing Events:</div>
                    <div class="text-yellow-700">${existingSlots}</div>
                </div>
                <div class="bg-red-50 p-3 rounded border border-red-300">
                    <div class="font-semibold text-red-800 mb-1">Current Gap:</div>
                    <div class="text-red-700 text-2xl font-bold">${gapHours} hour(s)</div>
                    <div class="text-red-600 text-sm mt-1">Requires ${gapNeeded} more hour(s)</div>
                </div>
                <div class="text-sm text-gray-700 italic mt-2">
                    💡 Please allow at least <strong>4 hours</strong> between events for preparation and cleanup.
                </div>
            </div>
        `;
    } else if (reason === 'overlap') {
        message = `
            <div class="space-y-2">
                <div class="font-bold text-red-700 text-lg">⚠️ Overlapping Schedule</div>
                <div class="bg-red-50 p-3 rounded border border-red-300">
                    <div class="font-semibold text-red-800 mb-2">Maximum Capacity Reached:</div>
                    <div class="text-red-700">Only 2 events can overlap at the same time.</div>
                </div>
                <div class="bg-yellow-50 p-3 rounded border border-yellow-300">
                    <div class="font-semibold text-yellow-800 mb-2">Conflicting Bookings:</div>
                    <div class="text-yellow-700">${existingSlots}</div>
                </div>
                <div class="text-sm text-gray-700 italic mt-2">
                    💡 Please select a different time that does not overlap with existing events.
                </div>
            </div>
        `;
    } else {
        message = `
            <div class="space-y-2">
                <div class="font-bold text-red-700 text-lg">⚠️ Scheduling Conflict</div>
                <div>Conflicting bookings detected:</div>
                <div class="text-yellow-700 font-semibold">${existingSlots}</div>
                <div class="text-sm text-gray-700 italic mt-2">
                    Please choose a different time slot and make sure there is a minimum 4-hour gap before or after other bookings.
                </div>
            </div>
        `;
    }

    conflictDetails.innerHTML = message;
    warningDiv.classList.remove('hidden');
}

// ------------------------------------------------------------
// HIDE CONFLICT WARNING (only if there’s no active conflict)
// ------------------------------------------------------------
function hideConflictWarning() {
    if (activeConflict) return; // ⛔ keep showing if still conflicting
    const warningDiv = document.getElementById('time-conflict-warning');
    const conflictDetails = document.getElementById('conflict-details');
    if (warningDiv) {
        warningDiv.classList.add('hidden');
        if (conflictDetails) conflictDetails.innerHTML = '';
    }
}

// ------------------------------------------------------------
// AUTO-RECHECK ON DATE/TIME CHANGE
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    const fields = [
        document.querySelector('[name="event_date"]'),
        document.querySelector('[name="start_time"]'),
        document.querySelector('[name="end_time"]')
    ];

    fields.forEach(field => {
        if (!field) return;

        field.addEventListener('change', () => {
            checkTimeConflictForValidation(() => {});
        });
    });
});


            function hideConflictWarning() {
                const warningDiv = document.getElementById('time-conflict-warning');
                if (warningDiv) {
                    warningDiv.classList.add('hidden');
                }
            }

            function resetBookingForm() {
                // Find the actual form element
                const form = document.querySelector('form[method="POST"]');
                if (form) {
                    form.reset();
                }
                
                showStep(1);
                
                // Reset theme selection
                document.querySelectorAll('.theme-btn').forEach(btn => {
                    btn.classList.remove('selected', 'border-red-500');
                });
                
                // Hide custom theme input
                const customThemeInput = document.getElementById('custom-theme');
                if (customThemeInput) {
                    customThemeInput.classList.add('hidden');
                    customThemeInput.classList.remove('border-red-500');
                }
                
                // Show theme suggestions section (reset to default state)
                const labels = document.querySelectorAll('label');
                let themeSuggestionsContainer = null;
                
                labels.forEach(label => {
                    if (label.textContent.includes('Additional Theme Suggestions')) {
                        themeSuggestionsContainer = label.parentElement;
                    }
                });
                
                if (themeSuggestionsContainer) {
                    themeSuggestionsContainer.style.display = 'block';
                }
                
                const ageField = document.getElementById('age-field');
                if (ageField) {
                    ageField.classList.add('hidden');
                }
                
                hideConflictWarning();
                
                // Remove error styling
                document.querySelectorAll('input, select, textarea').forEach(field => {
                    field.classList.remove('border-red-500');
                });
                
                // Reset event preview
                const previewDiv = document.getElementById('event-preview');
                if (previewDiv) {
                    previewDiv.innerHTML = '<p>Fill in the details above to see your event preview</p>';
                }
                
                // Reset price calculator
                resetPriceDisplay();
                
                setupDateInput();
            }

            // Calendar functionality
            function loadCalendar() {
                updateCalendarTitle();
                
                const url = window.location.pathname + `?action=get_calendar_data&month=${currentMonth}&year=${currentYear}`;
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        calendarData = data;
                        generateCalendar();
                    })
                    .catch(error => {
                        console.error('Error loading calendar:', error);
                    });
            }

            function updateCalendarTitle() {
                const months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                
                const titleElement = document.getElementById('calendar-title');
                if (titleElement) {
                    titleElement.textContent = `${months[currentMonth - 1]} ${currentYear}`;
                }
                
                const prevBtn = document.getElementById('prev-month');
                const nextBtn = document.getElementById('next-month');
                
                if (prevBtn) prevBtn.disabled = (currentYear === 2025 && currentMonth === 1);
                if (nextBtn) nextBtn.disabled = (currentYear === 2025 && currentMonth === 12);
            }

            function generateCalendar() {
                const calendarGrid = document.getElementById('calendar-grid');
                if (!calendarGrid) return;
                
                calendarGrid.innerHTML = '';
                
                const firstDay = new Date(currentYear, currentMonth - 1, 1);
                const lastDay = new Date(currentYear, currentMonth, 0);
                const daysInMonth = lastDay.getDate();
                const startingDayOfWeek = firstDay.getDay();
                
                const today = new Date();
                const todayStr = today.toISOString().split('T')[0];
                
                // Get minimum date (3 days from today)
                const minDate = new Date();
                minDate.setDate(minDate.getDate() + 3);
                const minDateStr = minDate.toISOString().split('T')[0];
                
                for (let i = 0; i < startingDayOfWeek; i++) {
                    const emptyDay = document.createElement('div');
                    emptyDay.classList.add('calendar-day', 'other-month');
                    calendarGrid.appendChild(emptyDay);
                }
                
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayElement = document.createElement('div');
                    dayElement.classList.add('calendar-day');
                    
                    const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const isToday = dateStr === todayStr;
                    const isPastDate = dateStr < minDateStr;
                    
                    if (isToday) {
                        dayElement.classList.add('today');
                    }
                    
                    // Mark past dates as unavailable
                    if (isPastDate) {
                        dayElement.classList.add('unavailable');
                        dayElement.style.backgroundColor = '#e5e7eb';
                        dayElement.style.borderColor = '#9ca3af';
                        dayElement.style.color = '#6b7280';
                        dayElement.style.cursor = 'not-allowed';
                        dayElement.style.position = 'relative';
                        dayElement.style.opacity = '0.6';
                    }
                    
                    const bookingInfo = calendarData[dateStr];
                    if (bookingInfo && !isPastDate) {
                        const count = bookingInfo.count;
                        if (count === 1) {
                            dayElement.classList.add('one-booking');
                        } else if (count === 2) {
                            dayElement.classList.add('two-bookings');
                        } else if (count >= 3) {
                            dayElement.classList.add('three-bookings', 'unavailable');
                            dayElement.style.position = 'relative';
                            dayElement.style.opacity = '0.6';
                        }
                        
                        const countIndicator = document.createElement('div');
                        countIndicator.classList.add('booking-count');
                        countIndicator.textContent = count;
                        dayElement.appendChild(countIndicator);
                        
                        bookingInfo.bookings.forEach(booking => {
                            const slotElement = document.createElement('div');
                            slotElement.classList.add('booking-slot');
                            if (booking.is_own_booking) {
                                slotElement.classList.add('own-booking');
                            }
                            
                            if (count >= 3) {
                                slotElement.style.opacity = '0.4';
                            }
                            
                            const startTime12 = formatTimeTo12Hour(booking.start_time.substring(0, 5));
                            const endTime12 = formatTimeTo12Hour(booking.end_time.substring(0, 5));
                            const timeStr = `${startTime12}-${endTime12}`;
                            slotElement.textContent = timeStr;
                            dayElement.appendChild(slotElement);
                        });
                        
                        if (count >= 3) {
                            const unavailableOverlay = document.createElement('div');
                            unavailableOverlay.style.cssText = `
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                background-color: rgba(239, 68, 68, 0.9);
                                color: white;
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 10px;
                                font-weight: bold;
                                z-index: 10;
                                text-align: center;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                            `;
                            unavailableOverlay.textContent = 'UNAVAILABLE';
                            dayElement.appendChild(unavailableOverlay);
                        }
                        
                        if (!isPastDate && count < 3) {
                            dayElement.addEventListener('click', () => showBookingDetails(dateStr, bookingInfo));
                        }
                    } else if (!isPastDate) {
                        dayElement.classList.add('no-bookings');
                    } else if (isPastDate && bookingInfo) {
                        const count = bookingInfo.count;
                        const countIndicator = document.createElement('div');
                        countIndicator.classList.add('booking-count');
                        countIndicator.textContent = count;
                        countIndicator.style.opacity = '0.5';
                        dayElement.appendChild(countIndicator);
                        
                        bookingInfo.bookings.forEach(booking => {
                            const slotElement = document.createElement('div');
                            slotElement.classList.add('booking-slot');
                            if (booking.is_own_booking) {
                                slotElement.classList.add('own-booking');
                            }
                            slotElement.style.opacity = '0.3';
                            
                            const startTime12 = formatTimeTo12Hour(booking.start_time.substring(0, 5));
                            const endTime12 = formatTimeTo12Hour(booking.end_time.substring(0, 5));
                            const timeStr = `${startTime12}-${endTime12}`;
                            slotElement.textContent = timeStr;
                            dayElement.appendChild(slotElement);
                        });
                    }
                    
                    if (isPastDate) {
                        const pastOverlay = document.createElement('div');
                        pastOverlay.style.cssText = `
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            background-color: rgba(107, 114, 128, 0.9);
                            color: white;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 10px;
                            font-weight: bold;
                            z-index: 10;
                            text-align: center;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                        `;
                        pastOverlay.textContent = 'PAST';
                        dayElement.appendChild(pastOverlay);
                    }
                    
                    const dateNumber = document.createElement('div');
                    dateNumber.classList.add('date-number');
                    dateNumber.textContent = day;
                    dayElement.insertBefore(dateNumber, dayElement.firstChild);
                    
                    calendarGrid.appendChild(dayElement);
                }
            }

            function showBookingDetails(dateStr, bookingInfo) {
                const modal = document.getElementById('booking-details-modal');
                const selectedDate = document.getElementById('selected-date');
                const content = document.getElementById('booking-details-content');
                
                if (selectedDate) {
                    selectedDate.textContent = new Date(dateStr).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }
                
                if (content) {
                    content.innerHTML = '';
                    
                    bookingInfo.bookings.forEach((booking) => {
                        const bookingElement = document.createElement('div');
                        bookingElement.classList.add('mb-4', 'p-3', 'border', 'rounded-lg');
                        
                        if (booking.is_own_booking) {
                            bookingElement.classList.add('border-blue-300', 'bg-blue-50');
                        } else {
                            bookingElement.classList.add('border-gray-300', 'bg-gray-50');
                        }
                        
                        const startTime12 = formatTimeTo12Hour(booking.start_time.substring(0, 5));
                        const endTime12 = formatTimeTo12Hour(booking.end_time.substring(0, 5));
                        const timeStr = `${startTime12} - ${endTime12}`;
                        
                        bookingElement.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-semibold text-gray-800">${timeStr}</div>
                                    <div class="text-sm text-gray-600">${booking.event_type}</div>
                                    ${booking.is_own_booking ? `<div class="text-sm text-blue-600 font-medium">Your booking: ${booking.full_name}</div>` : '<div class="text-sm text-gray-500">Other user\'s booking</div>'}
                                    <div class="text-xs text-gray-500 mt-1">Status: ${booking.booking_status}</div>
                                </div>
                                ${booking.is_own_booking ? '<div class="text-blue-500"><i class="fas fa-user"></i></div>' : '<div class="text-gray-400"><i class="fas fa-clock"></i></div>'}
                            </div>
                        `;
                        
                        content.appendChild(bookingElement);
                    });
                }
                
                if (modal) {
                    modal.classList.remove('hidden');
                }
            }

            // Enhanced message modal
            function showMessage(type, title, message) {
                const modal = document.getElementById('message-modal');
                const icon = document.getElementById('message-icon');
                const titleEl = document.getElementById('message-title');
                const textEl = document.getElementById('message-text');
                
                if (!modal || !icon || !titleEl || !textEl) return;
                
                if (type === 'success') {
                    icon.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
                } else if (type === 'warning') {
                    icon.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500"></i>';
                } else {
                    icon.innerHTML = '<i class="fas fa-exclamation-triangle text-red-500"></i>';
                }
                
                titleEl.textContent = title;
                textEl.innerHTML = message;
                modal.classList.remove('hidden');
            }

            // Time conflict checking with debounce (for live preview)
            function checkTimeConflict() {
                const eventDate = document.querySelector('[name="event_date"]')?.value;
                const startTime = document.querySelector('[name="start_time"]')?.value;
                const endTime = document.querySelector('[name="end_time"]')?.value;
                
                if (!eventDate || !startTime || !endTime) {
                    hideConflictWarning();
                    return;
                }
                
                if (conflictCheckTimeout) {
                    clearTimeout(conflictCheckTimeout);
                }
                
                conflictCheckTimeout = setTimeout(() => {
                    const url = window.location.pathname + `?action=check_conflict&event_date=${encodeURIComponent(eventDate)}&start_time=${encodeURIComponent(startTime)}&end_time=${encodeURIComponent(endTime)}`;
                    
                    fetch(url)
                        .then(r => {
                            if (!r.ok) throw new Error('Network response was not ok');
                            return r.json();
                        })
                        .then(data => {
                            if (data.conflict) {
                                showConflictWarning(data.existing_slots || '');
                            } else {
                                hideConflictWarning();
                            }
                        })
                        .catch(err => console.error('Conflict check error:', err));
                }, 500);
            }

            function setupTimeConflictChecking() {
                const eventDateInput = document.querySelector('[name="event_date"]');
                const startTimeInput = document.querySelector('[name="start_time"]');
                const endTimeInput = document.querySelector('[name="end_time"]');
                
                if (eventDateInput && startTimeInput && endTimeInput) {
                    [eventDateInput, startTimeInput, endTimeInput].forEach(input => {
                        input.addEventListener('change', () => {
                            checkTimeConflict();
                            updateEventPreview();
                        });
                        input.addEventListener('input', () => {
                            checkTimeConflict();
                            updateEventPreview();
                        });
                    });
                }
            }

            // Setup input change handlers for preview updates
            function setupPreviewUpdaters() {
                const previewFields = [
                    'celebrant_name', 'event_type', 'guest_count', 'food_package', 
                    'celebrant_age', 'event_date', 'start_time', 'end_time'
                ];
                
                previewFields.forEach(fieldName => {
                    const field = document.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        field.addEventListener('input', updateEventPreview);
                        field.addEventListener('change', updateEventPreview);
                    }
                });
            }

            // Profile settings placeholder
            function loadProfileSettings() {
                console.log('Loading profile settings...');
                // Add profile settings functionality here
            }

            // Event listeners setup - MAIN INITIALIZATION
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM Content Loaded - Initializing enhanced booking form');
                
                // Initialize basic components
                setupDateInput();
                setupTimeConflictChecking();
                setupEventTypeHandler();
                setupPreviewUpdaters();
                showStep(1);
                
                // Price calculator listeners
                const priceFields = ['guest-count', 'package'];
                priceFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.addEventListener('change', updatePriceCalculator);
                        field.addEventListener('input', updatePriceCalculator);
                    }
                });

                // ✅ TRIGGER GUEST BLOCKING WHEN PACKAGE CHANGES
                const packageField = document.getElementById('package');
                if (packageField) {
                    packageField.addEventListener('change', function() {
                        updatePriceCalculator(); // This will trigger guest blocking
                    });
                }
                
                
                // Menu checkboxes for additional pricing
                document.addEventListener('change', function(e) {
                    if (e.target.matches('input[name^="menu_"]')) {
                        updatePriceCalculator();
                    }
                });
                
                // FIXED STEP NAVIGATION LISTENERS - These now properly validate before proceeding
                const nextStep1 = document.getElementById('next-step1');
                if (nextStep1) {
                    nextStep1.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Next Step 1 clicked');
                        
                        if (validateStep1()) {
                            console.log('Step 1 valid, proceeding to step 2');
                            goToStep(2);
                        } else {
                            console.log('Step 1 validation failed, staying on step 1');
                            // Stay on current step - validation function already shows errors
                        }
                    });
                }
                
const nextStep2 = document.getElementById('next-step2');
if (nextStep2) {
    nextStep2.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Next Step 2 clicked');
        
        const btn = this;
        const originalText = btn.innerHTML;
        
        // Step 1: Basic validation (synchronous)
        const basicValidation = validateStep2();
        
        if (!basicValidation) {
            // Basic validation failed - stay on step 2
            console.log('❌ Basic validation failed');
            return;
        }
        
        // Step 2: Check conflicts (asynchronous)
        console.log('✅ Basic validation passed, checking conflicts...');
        
        // Disable button
        btn.innerHTML = '<span class="loading-spinner"></span>Checking conflicts...';
        btn.disabled = true;
        
        // Check conflicts with callback
        checkTimeConflictForValidation(function(hasNoConflict) {
            if (hasNoConflict) {
                console.log('✅ No conflicts - proceeding to step 3');
                goToStep(3);
            } else {
                console.log('❌ Conflict detected - staying on step 2');
            }
            
            // Always restore button
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
}
                
                const backStep2 = document.getElementById('back-step2');
                if (backStep2) {
                    backStep2.addEventListener('click', function(e) {
                        e.preventDefault();
                        goToStep(1);
                    });
                }
                
                const backStep3 = document.getElementById('back-step3');
                if (backStep3) {
                    backStep3.addEventListener('click', function(e) {
                        e.preventDefault();
                        goToStep(2);
                    });
                }
                
                // Calendar navigation
                const prevMonthBtn = document.getElementById('prev-month');
                const nextMonthBtn = document.getElementById('next-month');
                
                if (prevMonthBtn) {
                    prevMonthBtn.addEventListener('click', () => {
                        if (currentMonth === 1) {
                            currentMonth = 12;
                            currentYear--;
                        } else {
                            currentMonth--;
                        }
                        
                        if (currentYear < 2025) {
                            currentYear = 2025;
                            currentMonth = 1;
                        }
                        
                        loadCalendar();
                    });
                }

                if (nextMonthBtn) {
                    nextMonthBtn.addEventListener('click', () => {
                        if (currentMonth === 12) {
                            currentMonth = 1;
                            currentYear++;
                        } else {
                            currentMonth++;
                        }
                        
                        if (currentYear > 2025) {
                            currentYear = 2025;
                            currentMonth = 12;
                        }
                        
                        loadCalendar();
                    });
                }

                // Refresh bookings button
                const refreshBookingsBtn = document.getElementById('refresh-bookings');
                if (refreshBookingsBtn) {
                    refreshBookingsBtn.addEventListener('click', function() {
                        loadMyBookings();
                        
                        const originalText = this.innerHTML;
                        this.innerHTML = '<span class="loading-spinner"></span>Refreshing...';
                        this.disabled = true;
                        
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            this.disabled = false;
                        }, 2000);
                    });
                }

                // Theme selection with enhanced logic
                document.querySelectorAll('.theme-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Remove selection and error styling from all buttons
                        document.querySelectorAll('.theme-btn').forEach(b => {
                            b.classList.remove('selected', 'border-red-500');
                        });
                        
                        // Select this button
                        this.classList.add('selected');
                        
                        const theme = this.dataset.theme;
                        const radioBtn = this.querySelector('input[type="radio"]');
                        
                        if (radioBtn) {
                            radioBtn.checked = true;
                        }
                        
                        const customInput = document.getElementById('custom-theme');
                        const themeSuggestionsSection = document.querySelector('label').parentElement; // Find theme suggestions container
                        
                        // Find the theme suggestions section properly
                        const labels = document.querySelectorAll('label');
                        let themeSuggestionsContainer = null;
                        
                        labels.forEach(label => {
                            if (label.textContent.includes('Additional Theme Suggestions')) {
                                themeSuggestionsContainer = label.parentElement;
                            }
                        });
                        
                        if (theme === 'custom') {
                            customInput.classList.remove('hidden');
                            customInput.focus();
                            
                            // Hide theme suggestions for custom theme
                            if (themeSuggestionsContainer) {
                                themeSuggestionsContainer.style.display = 'none';
                            }
                        } else {
                            customInput.classList.add('hidden');
                            customInput.value = '';
                            customInput.classList.remove('border-red-500');
                            
                            // Show theme suggestions for non-custom themes
                            if (themeSuggestionsContainer) {
                                themeSuggestionsContainer.style.display = 'block';
                            }
                        }
                    });
                });

                // preview modal event listeners
                const closepreviewModal = document.getElementById('close-preview-modal');
                if (closepreviewModal) {
                    closepreviewModal.addEventListener('click', function() {
                        closepreviewModal();
                    });
                }

                const closepreviewBtn = document.getElementById('close-preview-btn');
                if (closepreviewBtn) {
                    closepreviewBtn.addEventListener('click', function() {
                        closepreviewModal();
                    });
                }

                // Close preview modal on backdrop click
                const previewModal = document.getElementById('preview-modal');
                if (previewModal) {
                    previewModal.addEventListener('click', function(e) {
                        if (e.target === previewModal) {
                            closepreviewModal();
                        }
                    });
                }

                // Delete modal event listeners
                const deleteModalCancel = document.getElementById('delete-modal-cancel');
                if (deleteModalCancel) {
                    deleteModalCancel.addEventListener('click', function() {
                        hideDeleteModal();
                    });
                }
                
                const deleteModalConfirm = document.getElementById('delete-modal-confirm');
                if (deleteModalConfirm) {
                    deleteModalConfirm.addEventListener('click', function() {
                        if (currentDeleteBookingId) {
                            deleteBooking(currentDeleteBookingId);
                            hideDeleteModal();
                        }
                    });
                }
                
                // Close modal when clicking outside
                const deleteModal = document.getElementById('delete-modal');
                if (deleteModal) {
                    deleteModal.addEventListener('click', function(e) {
                        if (e.target === deleteModal) {
                            hideDeleteModal();
                        }
                    });
                }

                // Sign out functionality
                const signoutBtn = document.getElementById('signout-btn');
                if (signoutBtn) {
                    signoutBtn.addEventListener('click', () => {
                        const signoutModal = document.getElementById('signout-modal');
                        if (signoutModal) {
                            signoutModal.classList.remove('hidden');
                        }
                    });
                }

                const cancelSignout = document.getElementById('cancel-signout');
                if (cancelSignout) {
                    cancelSignout.addEventListener('click', () => {
                        const signoutModal = document.getElementById('signout-modal');
                        if (signoutModal) {
                            signoutModal.classList.add('hidden');
                        }
                    });
                }

                const confirmSignout = document.getElementById('confirm-signout');
                if (confirmSignout) {
                    confirmSignout.addEventListener('click', function() {
                        // Clear localStorage before redirecting
                        localStorage.removeItem('currentSection');
                        localStorage.removeItem('bookingFormData');
                        
                        // Redirect to logout.php instead of auth.php directly
                        window.location.href = 'logout.php';
                    });
                }

                // Modal event listeners
                const messageModal = document.getElementById('message-modal');
                if (messageModal) {
                    messageModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.classList.add('hidden');
                        }
                    });
                }

                const bookingDetailsModal = document.getElementById('booking-details-modal');
                if (bookingDetailsModal) {
                    bookingDetailsModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.classList.add('hidden');
                        }
                    });
                }

                const signoutModal = document.getElementById('signout-modal');
                if (signoutModal) {
                    signoutModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.classList.add('hidden');
                        }
                    });
                }

                const closeBookingDetails = document.getElementById('close-booking-details');
                if (closeBookingDetails) {
                    closeBookingDetails.addEventListener('click', () => {
                        const bookingDetailsModal = document.getElementById('booking-details-modal');
                        if (bookingDetailsModal) {
                            bookingDetailsModal.classList.add('hidden');
                        }
                    });
                }

                // Message modal OK button
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.id === 'message-ok') {
                        const messageModal = document.getElementById('message-modal');
                        if (messageModal) {
                            messageModal.classList.add('hidden');
                        }
                    }
                });

                // ENHANCED BOOKING FORM SUBMISSION - FIXED
                const bookingForm = document.querySelector('form[method="POST"]');
                
                // Only attach event listener if form exists and doesn't already have one
                if (bookingForm && !bookingForm.hasAttribute('data-form-initialized')) {
                    // Mark form as initialized to prevent duplicate listeners
                    bookingForm.setAttribute('data-form-initialized', 'true');
                    
                    bookingForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        console.log('Form submission started');
                        
                        // VALIDATE STEP 3 BEFORE SUBMISSION
                        if (!validateStep3()) {
                            console.log('Form submission blocked - Step 3 validation failed');
                            return;
                        }
                        
                        // Prevent multiple rapid submissions
                        const submitBtn = document.getElementById('submit-booking');
                        if (submitBtn.disabled) {
                            console.log('Form already being submitted, ignoring duplicate');
                            return;
                        }
                        
                        // Create FormData object
                        const formData = new FormData(this);
                        formData.set('action', 'book_event');
                        
                        // Get selected theme
                        const selectedTheme = document.querySelector('.theme-btn.selected');
                        if (selectedTheme) {
                            formData.set('event_theme', selectedTheme.dataset.theme);
                        }
                        
                        // Collect selected menus
                        const selectedMenus = [];
                        document.querySelectorAll('input[name^="menu_"]:checked').forEach(checkbox => {
                            selectedMenus.push(checkbox.value);
                        });
                        formData.set('selected_menus', selectedMenus.join(','));
                        
                        const originalText = submitBtn.innerHTML;
                        
                        // Show loading state and disable button
                        submitBtn.innerHTML = '<span class="loading-spinner"></span>Submitting...';
                        submitBtn.disabled = true;
                        
                        // Submit form
                        fetch(window.location.pathname, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            console.log('Response status:', response.status);
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.text();
                        })
                        .then(text => {
                            console.log('Response received');
                            try {
                                const data = JSON.parse(text);
                                console.log('Parsed data:', data);
                                
                        if (data.success) {
                        // Prepare booking data for preview
                        const bookingData = {
                            id: data.booking_id,
                            full_name: formData.get('full_name'),
                            contact_number: formData.get('contact_number'),
                            celebrant_name: formData.get('celebrant_name'),
                            guest_count: formData.get('guest_count'),
                            event_type: formData.get('event_type'),
                            event_date: formData.get('event_date'),
                            start_time: formData.get('start_time'),
                            end_time: formData.get('end_time'),
                            location: formData.get('location'),
                            food_package: formData.get('food_package'),
                            event_theme: formData.get('event_theme'),
                            custom_theme: formData.get('custom_theme'),
                            theme_suggestions: formData.get('theme_suggestions'),
                            total_price: data.total_price,
                            booking_status: 'pending',
                            payment_status: 'unpaid',
                            created_at: new Date().toISOString()
                        };
                        
                        // Show success modal with booking data
                        showSuccessModal(bookingData);
                        resetBookingForm();
                                    
                                    // Refresh calendar if visible
                                    const scheduleSection = document.getElementById('section-schedule');
                                    if (scheduleSection && !scheduleSection.classList.contains('hidden')) {
                                        loadCalendar();
                                    }
                                } else {
                                    if (data.clear_time) {
                                        showMessage('error', 'Time Conflict Detected', data.message + '<br><br>Please select a different time slot and try again.');
                                        const startTimeField = document.querySelector('[name="start_time"]');
                                        const endTimeField = document.querySelector('[name="end_time"]');
                                        if (startTimeField) startTimeField.value = '';
                                        if (endTimeField) endTimeField.value = '';
                                        goToStep(2); // Go back to step 2 to fix times
                                    } else {
                                        showMessage('error', 'Booking Failed', data.message);
                                    }
                                }
                            } catch (parseError) {
                                console.error('JSON parse error:', parseError);
                                showMessage('error', 'Server Error', 'Invalid response from server. Please try again.');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            showMessage('error', 'Network Error', 'Please check your connection and try again.');
                        })
                        .finally(() => {
                            // Always restore button state
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        });
                    });
                    
                    console.log('Form submission handler attached successfully');
                }
                
                // Override the original displayBookings function
                window.displayBookings = displayBookingsWithPrice;
                
                // Make functions globally available
                window.goToStep = goToStep;
                window.updatePriceCalculator = updatePriceCalculator;
                window.showDeleteModal = showDeleteModal;
                window.hideDeleteModal = hideDeleteModal;
                window.deleteBooking = deleteBooking;
                window.showpreviewModal = showpreviewModal;
                window.closepreviewModal = closepreviewModal;
                window.printpreview = printpreview;
                window.showBookNowSection = showBookNowSection;
                window.loadMyBookings = loadMyBookings;
                window.loadCalendar = loadCalendar;
                window.showMessage = showMessage;
                
                // Initialize calendar date
                const now = new Date();
                currentMonth = now.getMonth() + 1;
                currentYear = 2025;
                
                console.log('Enhanced 3-step booking form loaded and initialized with fixed step validation');
            });

            // Additional utility functions

            // Function to convert 24-hour to 12-hour format (duplicate removed)
            // formatTimeTo12Hour is already defined above

            // FIXED Navigation functions
            function showStep(step) {
                console.log(`Showing step ${step}`);
                
                const steps = ['booking-step1', 'booking-step2', 'booking-step3'];
                const stepIndicators = ['step-1', 'step-2', 'step-3'];
                
                // Hide all steps
                steps.forEach((stepId, index) => {
                    const stepElement = document.getElementById(stepId);
                    if (stepElement) {
                        stepElement.classList.remove('active');
                        stepElement.classList.add('hidden');
                    }
                });
                
                // Show current step
                const currentStepElement = document.getElementById(`booking-step${step}`);
                if (currentStepElement) {
                    currentStepElement.classList.remove('hidden');
                    setTimeout(() => {
                        currentStepElement.classList.add('active');
                    }, 50);
                }
                
                // Update step indicators
                stepIndicators.forEach((stepId, index) => {
                    const stepElement = document.getElementById(stepId);
                    const stepNumber = index + 1;
                    
                    if (stepElement) {
                        stepElement.classList.remove('active', 'completed', 'inactive');
                        
                        if (stepNumber < step) {
                            stepElement.classList.add('completed');
                        } else if (stepNumber === step) {
                            stepElement.classList.add('active');
                        } else {
                            stepElement.classList.add('inactive');
                        }
                    }
                });
                
                // Update step lines
                const stepLines = document.querySelectorAll('.step-line');
                stepLines.forEach((line, index) => {
                    line.classList.remove('active', 'completed', 'inactive');
                    const lineNumber = index + 1;
                    
                    if (lineNumber < step) {
                        line.classList.add('completed');
                    } else if (lineNumber === step) {
                        line.classList.add('active');
                    } else {
                        line.classList.add('inactive');
                    }
                });
                
                currentStep = step;
                
                // Sync price displays when navigating
                updatePriceCalculator();
                
                // Scroll to top of form section
                const sectionBook = document.getElementById('section-book');
                if (sectionBook) {
                    sectionBook.scrollIntoView({ behavior: 'smooth' });
                }
            }

            // Make sure all global functions are available
            window.validateStep1 = validateStep1;
            window.validateStep2 = validateStep2;
            window.validateStep3 = validateStep3;
            window.showStep = showStep;
            window.resetBookingForm = resetBookingForm;
            window.formatTimeTo12Hour = formatTimeTo12Hour;


        // Load next upcoming event
        function loadNextEvent() {
            fetch(window.location.pathname + '?action=get_my_bookings')
                .then(response => response.json())
                .then(bookings => {
                    if (bookings.error) return;
                    
                    // Filter upcoming approved/pending events
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    const upcomingEvents = bookings.filter(booking => {
                        const eventDate = new Date(booking.event_date);
                        eventDate.setHours(0, 0, 0, 0);
                        return eventDate >= today && booking.booking_status !== 'cancelled';
                    }).sort((a, b) => new Date(a.event_date) - new Date(b.event_date));
                    
                    if (upcomingEvents.length > 0) {
                        const nextEvent = upcomingEvents[0];
                        displayNextEvent(nextEvent);
                    } else {
                        const card = document.getElementById('next-event-card');
                        if (card) card.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error loading next event:', error);
                });
        }

        // Display next event details
        function displayNextEvent(event) {
            const card = document.getElementById('next-event-card');
            if (!card) return;
            
            // Format date
            const eventDate = new Date(event.event_date);
            const formattedDate = eventDate.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
            
            // Format time
            const startTime = formatTimeTo12Hour(event.start_time.substring(0, 5));
            const endTime = formatTimeTo12Hour(event.end_time.substring(0, 5));
            const timeRange = `${startTime} - ${endTime}`;
            
            // Calculate days until event
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            eventDate.setHours(0, 0, 0, 0);
            const daysUntil = Math.ceil((eventDate - today) / (1000 * 60 * 60 * 24));
            
            // Update card elements
            const nextEventDate = document.getElementById('next-event-date');
            const nextEventTime = document.getElementById('next-event-time');
            const nextEventCelebrant = document.getElementById('next-event-celebrant');
            const nextEventType = document.getElementById('next-event-type');
            const nextEventLocation = document.getElementById('next-event-location');
            const nextEventCountdown = document.getElementById('next-event-countdown');
            
            if (nextEventDate) nextEventDate.textContent = formattedDate;
            if (nextEventTime) nextEventTime.textContent = timeRange;
            if (nextEventCelebrant) nextEventCelebrant.textContent = event.celebrant_name;
            if (nextEventType) nextEventType.textContent = event.event_type;
            if (nextEventLocation) nextEventLocation.textContent = event.location || 'Not specified';
            if (nextEventCountdown) {
                nextEventCountdown.textContent = daysUntil === 0 ? 'Today!' : 
                    daysUntil === 1 ? 'Tomorrow!' : `${daysUntil} days`;
            }
            
            card.classList.remove('hidden');
        }

        // Load booking statistics
        function loadBookingStats() {
            fetch(window.location.pathname + '?action=get_booking_stats')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.error('Error loading stats:', data.error);
                        return;
                    }
                    
                    // Update statistics
                    const totalBookings = parseInt(data.total_bookings) || 0;
                    const upcomingEvents = parseInt(data.upcoming_events) || 0;
                    const totalSpent = parseFloat(data.total_spent) || 0;
                    
                    // Update DOM elements
                    const totalBookingsEl = document.getElementById('total-bookings');
                    const upcomingEventsEl = document.getElementById('upcoming-events');
                    const totalSpentEl = document.getElementById('total-spent');
                    
                    if (totalBookingsEl) totalBookingsEl.textContent = totalBookings;
                    if (upcomingEventsEl) upcomingEventsEl.textContent = upcomingEvents;
                    if (totalSpentEl) {
                        totalSpentEl.textContent = '₱' + totalSpent.toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    
                    // Load next upcoming event details
                    loadNextEvent();
                })
                .catch(error => {
                    console.error('Error fetching stats:', error);
                    
                    // Set default values on error
                    const totalBookingsEl = document.getElementById('total-bookings');
                    const upcomingEventsEl = document.getElementById('upcoming-events');
                    const totalSpentEl = document.getElementById('total-spent');
                    
                    if (totalBookingsEl) totalBookingsEl.textContent = '0';
                    if (upcomingEventsEl) upcomingEventsEl.textContent = '0';
                    if (totalSpentEl) totalSpentEl.textContent = '₱0.00';
                });
        }

            // Display next event details
            function displayNextEvent(event) {
                const card = document.getElementById('next-event-card');
                
                // Format date
                const eventDate = new Date(event.event_date);
                const formattedDate = eventDate.toLocaleDateString('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });
                
                // Format time
                const startTime = formatTimeTo12Hour(event.start_time.substring(0, 5));
                const endTime = formatTimeTo12Hour(event.end_time.substring(0, 5));
                const timeRange = `${startTime} - ${endTime}`;
                
                // Calculate days until event
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                eventDate.setHours(0, 0, 0, 0);
                const daysUntil = Math.ceil((eventDate - today) / (1000 * 60 * 60 * 24));
                
                // Update card
                document.getElementById('next-event-date').textContent = formattedDate;
                document.getElementById('next-event-time').textContent = timeRange;
                document.getElementById('next-event-celebrant').textContent = event.celebrant_name;
                document.getElementById('next-event-type').textContent = event.event_type;
                document.getElementById('next-event-location').textContent = event.location || 'Not specified';
                document.getElementById('next-event-countdown').textContent = daysUntil === 0 ? 'Today!' : 
                    daysUntil === 1 ? 'Tomorrow!' : `${daysUntil} days`;
                
                card.classList.remove('hidden');
            }

            // Event listeners setup - MAIN INITIALIZATION
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM Content Loaded - Initializing enhanced booking form');
                
                // ============= PROFILE SETTINGS EVENT LISTENERS =============
                // Avatar change button
                const changeAvatarBtn = document.getElementById('change-avatar-btn');
                if (changeAvatarBtn) {
                    changeAvatarBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        openAvatarModal();
                    });
                }
                
                // Cancel avatar button
                const cancelAvatarBtn = document.getElementById('cancel-avatar-btn');
                if (cancelAvatarBtn) {
                    cancelAvatarBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        closeAvatarModal();
                    });
                }
                
                // Close avatar modal on backdrop click
                const avatarModal = document.getElementById('avatar-modal');
                if (avatarModal) {
                    avatarModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            closeAvatarModal();
                        }
                    });
                }
                
                // Profile dropdown toggle
                const profileMenuBtn = document.getElementById('profile-menu-btn');
                if (profileMenuBtn) {
                    profileMenuBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const dropdown = document.getElementById('profile-dropdown');
                        if (dropdown) {
                            dropdown.classList.toggle('hidden');
                        }
                    });
                }
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    const dropdown = document.getElementById('profile-dropdown');
                    const menuBtn = document.getElementById('profile-menu-btn');
                    
                    if (dropdown && !dropdown.contains(e.target) && e.target !== menuBtn && !menuBtn?.contains(e.target)) {
                        dropdown.classList.add('hidden');
                    }
                });
                
                // Change password button
                const changePasswordBtn = document.getElementById('change-password-btn');
                if (changePasswordBtn) {
                    changePasswordBtn.addEventListener('click', function() {
                        const profileDropdown = document.getElementById('profile-dropdown');
                        const passwordModal = document.getElementById('password-modal');
                        if (profileDropdown) profileDropdown.classList.add('hidden');
                        if (passwordModal) {
                            passwordModal.classList.remove('hidden');
                            document.body.style.overflow = 'hidden';
                        }
                    });
                }
                
                // Close password modal
                const closePasswordModal = document.getElementById('close-password-modal');
                if (closePasswordModal) {
                    closePasswordModal.addEventListener('click', function() {
                        const passwordModal = document.getElementById('password-modal');
                        if (passwordModal) {
                            passwordModal.classList.add('hidden');
                            document.body.style.overflow = '';
                        }
                    });
                }
                
                const cancelPasswordBtn = document.getElementById('cancel-password-btn');
                if (cancelPasswordBtn) {
                    cancelPasswordBtn.addEventListener('click', function() {
                        const passwordModal = document.getElementById('password-modal');
                        if (passwordModal) {
                            passwordModal.classList.add('hidden');
                            document.body.style.overflow = '';
                        }
                    });
                }
                
                // Password change form submission
                const changePasswordForm = document.getElementById('change-password-form');
                if (changePasswordForm) {
                    changePasswordForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const currentPassword = document.getElementById('current-password').value;
                        const newPassword = document.getElementById('new-password').value;
                        const confirmPassword = document.getElementById('confirm-password').value;
                        
                        if (newPassword !== confirmPassword) {
                            showPasswordPopup('error', 'New passwords do not match!');
                            return;
                        }
                        
                        if (newPassword.length < 11) {
                            showPasswordPopup('error', 'Password must be at least 11 characters!');
                            return;
                        }
                        
                        fetch('change_password.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `current_password=${encodeURIComponent(currentPassword)}&new_password=${encodeURIComponent(newPassword)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const passwordModal = document.getElementById('password-modal');
                                if (passwordModal) {
                                    passwordModal.classList.add('hidden');
                                    document.body.style.overflow = '';
                                }
                                const form = document.getElementById('change-password-form');
                                if (form) form.reset();
                                showPasswordPopup('success', 'Password changed successfully!');
                            } else {
                                showPasswordPopup('error', data.message || 'Failed to change password.');
                            }
                        })
                        .catch(error => {
                            console.error('Error changing password:', error);
                            showPasswordPopup('error', 'An error occurred.');
                        });
                    });
                }
                
                // Close password popup
                const closePasswordPopup = document.getElementById('close-password-popup');
                if (closePasswordPopup) {
                    closePasswordPopup.addEventListener('click', function() {
                        const popup = document.getElementById('password-popup-modal');
                        if (popup) popup.classList.add('hidden');
                    });
                }
                
                // Upcoming events card click
                const upcomingEventsCard = document.getElementById('upcoming-events-card');
                if (upcomingEventsCard) {
                    upcomingEventsCard.addEventListener('click', function() {
                        hideAllSections();
                        document.querySelectorAll("nav a").forEach(l => l.classList.remove("active-nav"));
                        const myBookingsNav = document.querySelector('nav a[href="#"]:nth-child(2)');
                        if (myBookingsNav) myBookingsNav.classList.add("active-nav");
                        const myBookingsSection = document.getElementById("section-mybookings");
                        if (myBookingsSection) myBookingsSection.classList.remove("hidden");
                        loadMyBookings();
                    });
                }
                
                // Dropdown sign out
                const dropdownSignout = document.getElementById('dropdown-signout');
                if (dropdownSignout) {
                    dropdownSignout.addEventListener('click', function() {
                        const dropdown = document.getElementById('profile-dropdown');
                        const signoutModal = document.getElementById('signout-modal');
                        if (dropdown) dropdown.classList.add('hidden');
                        if (signoutModal) signoutModal.classList.remove('hidden');
                    });
                }
                
                // Sign Out modal handlers
                const cancelSignout = document.getElementById('cancel-signout');
                if (cancelSignout) {
                    cancelSignout.addEventListener('click', function() {
                        const signoutModal = document.getElementById('signout-modal');
                        if (signoutModal) signoutModal.classList.add('hidden');
                    });
                }

                const confirmSignout = document.getElementById('confirm-signout');
                if (confirmSignout) {
                    confirmSignout.addEventListener('click', function() {
                        localStorage.removeItem('currentSection');
                        window.location.href = 'auth.php';
                    });
                }

                const signoutModal = document.getElementById('signout-modal');
                if (signoutModal) {
                    signoutModal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.classList.add('hidden');
                        }
                    });
                }
                
                console.log('Profile settings functionality initialized');
                
        // Load saved section with loading screen
        const savedSection = localStorage.getItem('currentSection');

        // Function to hide loader
        function hideLoader() {
            const loader = document.getElementById('page-loader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
        }

        if (savedSection && document.getElementById(savedSection)) {
            console.log('Restoring previous section:', savedSection);
            
            hideAllSections();
            document.getElementById(savedSection).classList.remove('hidden');
            
            // Update nav
            const navLinks = document.querySelectorAll("nav a");
            navLinks.forEach(link => {
                link.classList.remove('active-nav');
                const navMap = {
                    "Book Now": "section-book",
                    "My Bookings": "section-mybookings",
                    "Menu Packages": "section-menu",
                    "Gallery": "section-gallery",
                    "Available Schedule": "section-schedule",
                    "Profile Settings": "section-settings",
                    "About Us": "section-about"
                };
                if (navMap[link.innerText.trim()] === savedSection) {
                    link.classList.add('active-nav');
                }
            });
            
            // Load section data with loading indicator
            if (savedSection === 'section-settings') {
                loadBookingStats();
                // Hide loader after stats load
                setTimeout(hideLoader, 800);
            } else if (savedSection === 'section-mybookings') {
                loadMyBookings();
                // Hide loader after bookings load
                setTimeout(hideLoader, 800);
            } else if (savedSection === 'section-schedule') {
                loadCalendar();
                // Hide loader after calendar loads
                setTimeout(hideLoader, 800);
            } else if (savedSection === 'section-book') {
                // Load saved form inputs
                setTimeout(() => {
                    loadFormInputs();
                    hideLoader();
                }, 500);
            } else {
                // For other sections, hide loader immediately
                setTimeout(hideLoader, 500);
            }
        } else {
            // No saved section, show dashboard
            console.log('No saved section, showing dashboard');
            setTimeout(hideLoader, 500);
        }

        console.log('Enhanced 3-step booking form loaded and initialized with fixed step validation');
            });

            // Save form inputs to localStorage
            function saveFormInputs() {
                const formFields = [
                    'full_name', 'contact_number', 'celebrant_name', 'guest_count', 
                    'celebrant_age', 'food_package', 'event_type', 'location', 
                    'event_date', 'start_time', 'end_time', 'theme_suggestions'
                ];
                
                formFields.forEach(fieldName => {
                    const field = document.querySelector(`[name="${fieldName}"]`);
                    if (field && field.value) {
                        localStorage.setItem(`form_${fieldName}`, field.value);
                    }
                });
                
                // Save theme selection
                const selectedTheme = document.querySelector('.theme-btn.selected');
                if (selectedTheme) {
                    localStorage.setItem('form_theme', selectedTheme.dataset.theme);
                }
                
                // Save menu selections
                const selectedMenus = [];
                document.querySelectorAll('input[name^="menu_"]:checked').forEach(cb => {
                    selectedMenus.push(cb.value);
                });
                localStorage.setItem('form_menus', JSON.stringify(selectedMenus));
            }

            // Load saved form inputs
            function loadFormInputs() {
                const formFields = [
                    'full_name', 'contact_number', 'celebrant_name', 'guest_count', 
                    'celebrant_age', 'food_package', 'event_type', 'location', 
                    'event_date', 'start_time', 'end_time', 'theme_suggestions'
                ];
                
                formFields.forEach(fieldName => {
                    const savedValue = localStorage.getItem(`form_${fieldName}`);
                    if (savedValue) {
                        const field = document.querySelector(`[name="${fieldName}"]`);
                        if (field) {
                            field.value = savedValue;
                            
                            // Trigger change event for calculations
                            if (fieldName === 'guest_count' || fieldName === 'food_package') {
                                updatePriceCalculator();
                            }
                        }
                    }
                });
                
                // Load theme selection
                const savedTheme = localStorage.getItem('form_theme');
                if (savedTheme) {
                    const themeBtn = document.querySelector(`.theme-btn[data-theme="${savedTheme}"]`);
                    if (themeBtn) {
                        themeBtn.click();
                    }
                }
                
                // Load menu selections
                const savedMenus = localStorage.getItem('form_menus');
                if (savedMenus) {
                    try {
                        const menus = JSON.parse(savedMenus);
                        menus.forEach(menuValue => {
                            const checkbox = document.querySelector(`input[value="${menuValue}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                        updatePriceCalculator();
                    } catch (e) {
                        console.error('Error loading menus:', e);
                    }
                }
                
                updateEventPreview();
            }

            // Clear form data from localStorage
            function clearFormInputs() {
                const keys = Object.keys(localStorage);
                keys.forEach(key => {
                    if (key.startsWith('form_')) {
                        localStorage.removeItem(key);
                    }
                });
            }

        // ============= AVATAR FUNCTIONALITY (CATERING BUSINESS THEME) =============
            const avatarSeeds = [
                'Chef', 'Gourmet', 'Bistro', 'Cuisine', 'Deluxe',
                'Premium', 'Savory', 'Fusion', 'Epicure', 'Banquet',
                'Feast', 'Catering', 'Culinary', 'Flavor', 'Taste',
                'Dining', 'Plate', 'Garnish', 'Seasonal', 'Fresh'
            ];

            function generateAvatarGrid() {
                const grid = document.getElementById('avatar-grid');
                if (!grid) return;
                
                grid.innerHTML = '';
                
                avatarSeeds.forEach(seed => {
                    const avatarUrl = `https://api.dicebear.com/7.x/avataaars/svg?seed=${seed}`;
                    
                    const avatarDiv = document.createElement('div');
                    avatarDiv.className = 'cursor-pointer hover:scale-110 transition-transform border-2 border-transparent hover:border-blue-500 rounded-lg overflow-hidden';
                    avatarDiv.onclick = () => selectAvatar(avatarUrl);
                    
                    const img = document.createElement('img');
                    img.src = avatarUrl;
                    img.alt = `Avatar ${seed}`;
                    img.className = 'w-full h-full object-cover';
                    
                    avatarDiv.appendChild(img);
                    grid.appendChild(avatarDiv);
                });
            }

            function selectAvatar(avatarUrl) {
                fetch('save_avatar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `avatar_url=${encodeURIComponent(avatarUrl)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const profileAvatar = document.getElementById('profile-avatar');
                        if (profileAvatar) profileAvatar.src = avatarUrl;
                        closeAvatarModal();
                        showPasswordPopup('success', 'Avatar updated successfully!');
                    } else {
                        showPasswordPopup('error', data.message || 'Failed to save avatar.');
                    }
                })
                .catch(error => {
                    console.error('Error saving avatar:', error);
                    showPasswordPopup('error', 'An error occurred while saving avatar.');
                });
            }

            function openAvatarModal() {
                const modal = document.getElementById('avatar-modal');
                if (modal) {
                    generateAvatarGrid();
                    modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }
            }

            function closeAvatarModal() {
                const modal = document.getElementById('avatar-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }

            // ============= PASSWORD POPUP FUNCTIONALITY =============
            function showPasswordPopup(type, message) {
                const modal = document.getElementById('password-popup-modal');
                const icon = document.getElementById('password-popup-icon');
                const messageEl = document.getElementById('password-popup-message');
                
                if (!modal || !icon || !messageEl) return;
                
                if (type === 'success') {
                    icon.innerHTML = '<svg class="w-16 h-16 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                } else {
                    icon.innerHTML = '<svg class="w-16 h-16 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
                }
                
                messageEl.textContent = message;
                modal.classList.remove('hidden');
            }

        // ============= ENHANCED AUTO-SAVE WITH EXPIRATION =============

        // Save form inputs with timestamp
        function saveFormInputs() {
            const formData = {
                timestamp: new Date().getTime(), // Save current time
                fields: {}
            };
            
            const formFields = [
                'full_name', 'contact_number', 'celebrant_name', 'guest_count', 
                'celebrant_age', 'food_package', 'event_type', 'location', 
                'event_date', 'start_time', 'end_time', 'theme_suggestions'
            ];
            
            formFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field && field.value) {
                    formData.fields[fieldName] = field.value;
                }
            });
            
            // Save theme selection
            const selectedTheme = document.querySelector('.theme-btn.selected');
            if (selectedTheme) {
                formData.fields.event_theme = selectedTheme.dataset.theme;
                
                // Save custom theme if selected
                if (selectedTheme.dataset.theme === 'custom') {
                    const customTheme = document.getElementById('custom-theme');
                    if (customTheme && customTheme.value) {
                        formData.fields.custom_theme = customTheme.value;
                    }
                }
            }
            
            // Save menu selections
            const selectedMenus = [];
            document.querySelectorAll('input[name^="menu_"]:checked').forEach(cb => {
                selectedMenus.push(cb.value);
            });
            if (selectedMenus.length > 0) {
                formData.fields.selected_menus = selectedMenus;
            }
            
            // Save to localStorage
            localStorage.setItem('bookingFormData', JSON.stringify(formData));
            console.log('Form data saved with timestamp:', new Date(formData.timestamp).toLocaleString());
        }

        // Load saved form inputs with expiration check
        function loadFormInputs() {
            const savedData = localStorage.getItem('bookingFormData');
            
            if (!savedData) {
                console.log('No saved form data found');
                return;
            }
            
            try {
                const formData = JSON.parse(savedData);
                
                // Check if data is older than 2 days (48 hours)
                const currentTime = new Date().getTime();
                const savedTime = formData.timestamp;
                const twoDaysInMs = 2 * 24 * 60 * 60 * 1000; // 2 days in milliseconds
                
                if (currentTime - savedTime > twoDaysInMs) {
                    console.log('Form data expired (older than 2 days), clearing...');
                    clearFormInputs();
                    return;
                }
                
                console.log('Loading saved form data from:', new Date(savedTime).toLocaleString());
                
                // Load field values
                Object.keys(formData.fields).forEach(fieldName => {
                    const value = formData.fields[fieldName];
                    
                    if (fieldName === 'selected_menus') {
                        // Restore menu selections
                        value.forEach(menuValue => {
                            const checkbox = document.querySelector(`input[value="${menuValue}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    } else if (fieldName === 'event_theme') {
                        // Restore theme selection
                        const themeBtn = document.querySelector(`.theme-btn[data-theme="${value}"]`);
                        if (themeBtn) {
                            themeBtn.click();
                        }
                    } else if (fieldName === 'custom_theme') {
                        // Restore custom theme text
                        const customInput = document.getElementById('custom-theme');
                        if (customInput) {
                            customInput.value = value;
                        }
                    } else {
                        // Restore regular field
                        const field = document.querySelector(`[name="${fieldName}"]`);
                        if (field) {
                            field.value = value;
                        }
                    }
                });
                
                // Update calculations and preview
                updatePriceCalculator();
                updateEventPreview();
                
                // Show age field if birthday
                if (formData.fields.event_type === 'birthday') {
                    const ageField = document.getElementById('age-field');
                    if (ageField) {
                        ageField.classList.remove('hidden');
                    }
                }
                
                console.log('Form data loaded successfully');
                
            } catch (e) {
                console.error('Error loading form data:', e);
                clearFormInputs();
            }
        }

        // Clear form data from localStorage
        function clearFormInputs() {
            localStorage.removeItem('bookingFormData');
            console.log('Form data cleared from localStorage');
        }

        // Check expiration on page load
        function checkFormDataExpiration() {
            const savedData = localStorage.getItem('bookingFormData');
            
            if (savedData) {
                try {
                    const formData = JSON.parse(savedData);
                    const currentTime = new Date().getTime();
                    const savedTime = formData.timestamp;
                    const twoDaysInMs = 2 * 24 * 60 * 60 * 1000;
                    
                    if (currentTime - savedTime > twoDaysInMs) {
                        console.log('Expired form data detected, clearing...');
                        clearFormInputs();
                    }
                } catch (e) {
                    console.error('Error checking expiration:', e);
                    clearFormInputs();
                }
            }
        }

        // ============= AUTO-SAVE LISTENERS =============

        // Auto-save on input change
        document.addEventListener('input', function(e) {
            if (e.target.closest('#booking-form')) {
                saveFormInputs();
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.closest('#booking-form')) {
                saveFormInputs();
            }
        });

        // ============= LOAD ON NAVIGATION =============

        // Load saved inputs when Book Now section is shown
        document.querySelectorAll('nav a').forEach(navLink => {
            navLink.addEventListener('click', function() {
                const text = this.innerText.trim();
                if (text === 'Book Now') {
                    setTimeout(() => {
                        loadFormInputs();
                    }, 100);
                }
            });
        });

        // ============= CLEAR ON SUCCESSFUL SUBMISSION =============

        // Enhanced form submission with localStorage clearing
        const bookingForm = document.querySelector('form[method="POST"]');
        if (bookingForm && !bookingForm.hasAttribute('data-form-initialized')) {
            bookingForm.setAttribute('data-form-initialized', 'true');
            
            bookingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateStep3()) {
                    return;
                }
                
                const submitBtn = document.getElementById('submit-booking');
                if (submitBtn.disabled) {
                    return;
                }
                
                const formData = new FormData(this);
                formData.set('action', 'book_event');
                
                const selectedTheme = document.querySelector('.theme-btn.selected');
                if (selectedTheme) {
                    formData.set('event_theme', selectedTheme.dataset.theme);
                }
                
                const selectedMenus = [];
                document.querySelectorAll('input[name^="menu_"]:checked').forEach(checkbox => {
                    selectedMenus.push(checkbox.value);
                });
                formData.set('selected_menus', selectedMenus.join(','));
                
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading-spinner"></span>Submitting...';
                submitBtn.disabled = true;
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // ✅ CLEAR SAVED DATA ON SUCCESS
                        clearFormInputs();
                        
                        // Show success modal
                        showSuccessModal();
                        
                        // Reset form
                        resetBookingForm();
                        
                        // Refresh calendar if visible
                        const scheduleSection = document.getElementById('section-schedule');
                        if (scheduleSection && !scheduleSection.classList.contains('hidden')) {
                            loadCalendar();
                        }
                    } else {
                        if (data.clear_time) {
                            showMessage('error', 'Time Conflict Detected', data.message);
                            const startTimeField = document.querySelector('[name="start_time"]');
                            const endTimeField = document.querySelector('[name="end_time"]');
                            if (startTimeField) startTimeField.value = '';
                            if (endTimeField) endTimeField.value = '';
                            
                            // Save updated form (without conflicting times)
                            saveFormInputs();
                            
                            goToStep(2);
                        } else {
                            showMessage('error', 'Booking Failed', data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showMessage('error', 'Network Error', 'Please check your connection and try again.');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }

        // ============= INITIALIZATION =============

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing auto-save with 2-day expiration...');
            
            // Check expiration on page load
            checkFormDataExpiration();
            
            // Load form data if on Book Now section
            const bookSection = document.getElementById('section-book');
            if (bookSection && !bookSection.classList.contains('hidden')) {
                loadFormInputs();
            }
            
            console.log('Auto-save initialized successfully');
        });

        // ============= PERIODIC EXPIRATION CHECK =============

        // Check expiration every hour while page is open
        setInterval(checkFormDataExpiration, 60 * 60 * 1000); // Check every hour
            
            
            </script>
            </body>
            </html>
