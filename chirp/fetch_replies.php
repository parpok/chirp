<?php
session_start();

try {
    // Set up PDO with error mode to exception
    $db = new PDO('sqlite:' . __DIR__ . '/../chirp.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Validate and sanitize inputs
    $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
    $limit = 12;
    $postId = isset($_GET['for']) ? (int)$_GET['for'] : null;
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($postId === null) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Post ID is required.']);
        exit;
    }

    // Single query to fetch replies along with user info and interaction counts
    $query = "
        SELECT chirps.*, users.username, users.name, users.profilePic, users.isVerified,
               (SELECT COUNT(*) FROM likes WHERE chirp_id = chirps.id) AS like_count,
               (SELECT COUNT(*) FROM rechirps WHERE chirp_id = chirps.id) AS rechirp_count,
               (SELECT COUNT(*) FROM chirps AS replies WHERE replies.parent = chirps.id AND replies.type = 'reply') AS reply_count,
               (SELECT COUNT(*) FROM likes WHERE chirp_id = chirps.id AND user_id = :user_id) AS liked_by_current_user,
               (SELECT COUNT(*) FROM rechirps WHERE chirp_id = chirps.id AND user_id = :user_id) AS rechirped_by_current_user
        FROM chirps
        INNER JOIN users ON chirps.user = users.id
        WHERE chirps.parent = :parentId
        ORDER BY chirps.timestamp DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':parentId', $postId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();

    $chirps = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Sanitize output
        $row['chirp'] = nl2br(htmlspecialchars($row['chirp'], ENT_QUOTES, 'UTF-8'));
        $row['username'] = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
        $row['name'] = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        $row['profilePic'] = htmlspecialchars($row['profilePic'], ENT_QUOTES, 'UTF-8');
        $row['isVerified'] = (bool)$row['isVerified'];

        // Convert the interaction counts to booleans
        $row['liked_by_current_user'] = (bool)$row['liked_by_current_user'];
        $row['rechirped_by_current_user'] = (bool)$row['rechirped_by_current_user'];

        $chirps[] = $row;
    }

    // Set JSON header and output
    header('Content-Type: application/json');
    echo json_encode($chirps);

} catch (PDOException $e) {
    // Log error details for debugging purposes
    error_log($e->getMessage());
    http_response_code(500); // Set HTTP response code to 500 for server error
    echo json_encode(['error' => 'An error occurred while fetching replies. Please try again later.']);
} catch (Exception $e) {
    http_response_code(500); // Set HTTP response code to 500 for server error
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
?>
