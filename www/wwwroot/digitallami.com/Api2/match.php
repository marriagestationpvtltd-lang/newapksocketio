<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;

    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $userid = isset($_REQUEST['userid']) ? intval($_REQUEST['userid']) : 0;
    if ($userid <= 0) {
        echo json_encode(["success"=>false,"message"=>"Invalid userid"]);
        exit;
    }
    
    
    /* ================= USER LIKES ================= */
$stmtLikes = $pdo->prepare("
    SELECT receiver_id 
    FROM likes 
    WHERE sender_id = :me
");
$stmtLikes->execute([":me" => $userid]);

$likedUserIds = array_column($stmtLikes->fetchAll(), 'receiver_id');


    /* ================= REQUESTER GENDER ================= */
    $stmt = $pdo->prepare("SELECT gender FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([":id"=>$userid]);
    $user = $stmt->fetch();
    if(!$user){
        echo json_encode(["success"=>false,"message"=>"User not found"]);
        exit;
    }
    $userGender = $user['gender'];

    /* ================= PARTNER PREF ================= */
    $stmt = $pdo->prepare("SELECT minage, maxage FROM user_partner WHERE userid = :uid LIMIT 1");
    $stmt->execute([":uid"=>$userid]);
    $pref = $stmt->fetch();
    // If no partner preference set, use defaults (no age restriction)
    $hasPrefs = (bool)$pref;

    /* ================= CANDIDATES ================= */
    $stmt = $pdo->prepare("
        SELECT 
            u.id AS userid,
            upd.memberid,
            u.firstName,
            u.lastName,
            u.isVerified,
            u.profile_picture,
            u.privacy,                    -- ✅ ADDED
            ROUND(DATEDIFF(CURDATE(), upd.birthDate)/365) AS age,
            upd.height_name,
            pa.country,
            pa.city,
            ec.designation
        FROM users u
        LEFT JOIN userpersonaldetail upd ON upd.userId = u.id
        LEFT JOIN (SELECT userid, country, city FROM permanent_address GROUP BY userid) pa ON pa.userid = u.id
        LEFT JOIN (SELECT userid, designation FROM educationcareer GROUP BY userid) ec ON ec.userid = u.id
        WHERE u.id != :userid AND TRIM(LOWER(u.gender)) != TRIM(LOWER(:gender))
          AND u.isActive = 1 AND u.isDelete = 0
        GROUP BY u.id
    ");
    $stmt->execute([
        ":userid"=>$userid,
        ":gender"=>$userGender
    ]);
    $candidates = $stmt->fetchAll();

    $results = [];

    foreach($candidates as $c){

        /* ================= MATCH PERCENT ================= */
        $matchPercent = 50; // Default when no prefs set
        $minAge = ($hasPrefs && !empty($pref['minage'])) ? intval($pref['minage']) : null;
        $maxAge = ($hasPrefs && !empty($pref['maxage'])) ? intval($pref['maxage']) : null;
        $age = intval($c['age']);
        /* ================= LIKE STATUS ================= */
         $isLiked = in_array($c['userid'], $likedUserIds);


        if($minAge !== null && $maxAge !== null){
            if($age >= $minAge && $age <= $maxAge){
                $matchPercent = 100;
            } elseif($age >= $minAge-5 && $age <= $maxAge+5){
                $matchPercent = 20;
            } else {
                $matchPercent = 0;
            }
        } elseif($minAge !== null){
            $matchPercent = ($age >= $minAge) ? 100 : 20;
        } elseif($maxAge !== null){
            $matchPercent = ($age <= $maxAge) ? 100 : 20;
        }

        if($matchPercent < 20) continue;

        /* ================= PHOTO REQUEST ================= */
        $photo_request = "not sent";

        $stmtPhoto = $pdo->prepare("
            SELECT status
            FROM proposals
            WHERE request_type = 'Photo'
            AND (
                (sender_id = :me AND receiver_id = :other)
                OR
                (sender_id = :other AND receiver_id = :me)
            )
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtPhoto->execute([
            ":me"=>$userid,
            ":other"=>$c['userid']
        ]);

        if($row = $stmtPhoto->fetch()){
            $photo_request = ($row['status'] === 'accepted')
                ? 'accepted'
                : 'pending';
        }

        /* ================= GALLERY ================= */
        $stmtImages = $pdo->prepare("
            SELECT id, imageUrl, createdDate, updatedDate
            FROM userimagegallery
            WHERE userId = :uid AND isActive = 1 AND isDelete = 0
            ORDER BY createdDate DESC
        ");
        $stmtImages->execute([":uid"=>$c['userid']]);
        $gallery = $stmtImages->fetchAll();

        /* ================= FINAL USER ================= */
        $results[] = [
            "userid"=>$c['userid'],
            "memberid"=>$c['memberid'],
            "firstName"=>$c['firstName'],
            "lastName"=>$c['lastName'],
            "isVerified"=>$c['isVerified'],
            "profile_picture"=>$c['profile_picture'],
            "privacy"=>$c['privacy'],              // ✅ INCLUDED
            "age"=>$age,
            "height_name"=>$c['height_name'],
            "country"=>$c['country'],
            "city"=>$c['city'],
            "designation"=>$c['designation'],
            "matchPercent"=>$matchPercent,
            "photo_request"=>$photo_request,
            "like"=>$isLiked,              // ✅ ADDED

            "gallery"=>$gallery
        ];
    }

    usort($results, fn($a,$b)=>$b['matchPercent']<=>$a['matchPercent']);

    echo json_encode([
        "success"=>true,
        "matched_users"=>$results
    ]);

}catch(Exception $e){
    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);
}
