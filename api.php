<?php
session_start();
header('Content-Type: application/json');

// CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET,POST,DELETE,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {

    /* =====================================================
       ===============   USER SYSTEM   =====================
       ===================================================== */

    /* ---------------------- SIGNUP ---------------------- */
    if ($method === 'POST' && $action === 'signup') {
        $data = json_decode(file_get_contents("php://input"), true);

        $name  = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $pass  = trim($data['password'] ?? '');

        if ($name === '' || $email === '' || $phone === '' || $pass === '') {
            echo json_encode(['ok' => false, 'error' => 'All fields required']);
            exit;
        }

        // Check email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Email already registered']);
            exit;
        }

        // Insert
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,password_hash) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $phone, $hash]);

        $user_id = $pdo->lastInsertId();

        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;

        echo json_encode(['ok' => true, 'message' => 'Signup success', 'user_id' => $user_id]);
        exit;
    }

    /* ---------------------- LOGIN ---------------------- */
    if ($method === 'POST' && $action === 'login') {
        $data = json_decode(file_get_contents("php://input"), true);

        $email = trim($data['email'] ?? '');
        $pass  = trim($data['password'] ?? '');

        if ($email === '' || $pass === '') {
            echo json_encode(['ok' => false, 'error' => 'Missing credentials']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE email=?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($pass, $row['password_hash'])) {
            echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
            exit;
        }

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['name'];

        echo json_encode(['ok' => true, 'message' => 'Login success']);
        exit;
    }

    /* ---------------------- CHECK SESSION ---------------------- */
    if ($method === 'GET' && $action === 'session') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                'ok' => true,
                'user_id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name']
            ]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    /* ---------------------- LOGOUT ---------------------- */
    if ($method === 'GET' && $action === 'logout') {
        session_destroy();
        echo json_encode(['ok' => true]);
        exit;
    }

    /* ---------------- SAVE FITNESS INFO ---------------- */
    if ($method === 'POST' && $action === 'save_fitness') {

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
            exit;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $height  = $data['height'];
        $weight  = $data['weight'];
        $age     = $data['age'];
        $goal    = $data['goal'];
        $medical = $data['medical'];

        $uid = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT id FROM fitness_info WHERE user_id=?");
        $stmt->execute([$uid]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE fitness_info
                SET height_cm=?, weight_kg=?, age=?, goal=?, medical=?
                WHERE user_id=?
            ");
            $stmt->execute([$height, $weight, $age, $goal, $medical, $uid]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO fitness_info (user_id,height_cm,weight_kg,age,goal,medical)
                VALUES (?,?,?,?,?,?)
            ");
            $stmt->execute([$uid, $height, $weight, $age, $goal, $medical]);
        }

        echo json_encode(['ok' => true, 'message' => 'Fitness info saved']);
        exit;
    }

    /* =====================================================
       =============== GET USER INFO ========================
       ===================================================== */

    if ($method === 'POST' && $action === 'get_user_info') {

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
            exit;
        }

        $uid = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            SELECT 
                u.name,
                u.phone,
                f.age,
                f.height_cm,
                f.weight_kg,
                f.goal,
                f.medical
            FROM users u
            LEFT JOIN fitness_info f ON f.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$uid]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'info' => $info]);
        exit;
    }

    /* =====================================================
       =============== WORKOUT SYSTEM =======================
       ===================================================== */

    // LIST WORKOUTS
    if ($action === "list") {

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(["ok" => false, "error" => "Not logged in"]);
            exit;
        }

        $uid = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT * FROM workouts WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["ok" => true, "workouts" => $rows]);
        exit;
    }

    // ADD WORKOUT
    if ($method === 'POST' && $action === 'add') {

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['ok' => false, 'error' => 'login_required']);
            exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $type = trim($body['type'] ?? '');
        $duration = intval($body['duration'] ?? 0);
        $intensity = $body['intensity'] ?? '';

        if ($type === '' || $duration <= 0 || !in_array($intensity, ['Low','Medium','High'])) {
            throw new Exception('Invalid input');
        }

        $id = (int) round(microtime(true) * 1000);
        $userId = $_SESSION['user_id'];

        $stmt = $pdo->prepare("
            INSERT INTO workouts (id, user_id, type, duration, intensity)
            VALUES (:id, :user_id, :type, :duration, :intensity)
        ");

        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId,
            ':type' => $type,
            ':duration' => $duration,
            ':intensity' => $intensity
        ]);

        echo json_encode([
            'ok' => true,
            'workout' => [
                'id' => $id,
                'type' => $type,
                'duration' => $duration,
                'intensity' => $intensity
            ]
        ]);
        exit;
    }

    // DELETE WORKOUT (Fixed)
    if ($method === 'DELETE' && $action === 'delete') {

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['ok'=>false,'error'=>'login_required']);
            exit;
        }

        $id = $_GET['id'] ?? null;
        if (!$id) throw new Exception('Missing id');

        $stmt = $pdo->prepare("DELETE FROM workouts WHERE id = :id AND user_id = :uid");
        $stmt->execute([
            ':id' => $id,
            ':uid' => $_SESSION['user_id']
        ]);

        echo json_encode(['ok' => true, 'deleted' => (bool)$stmt->rowCount()]);
        exit;
    }

    /* =====================================================
       =============== AI CHATBOT ===========================
       ===================================================== */

if ($method === 'POST' && $action === 'chat') {
    $data = json_decode(file_get_contents("php://input"), true);
    $message = strtolower(trim($data['message'] ?? ''));

    if ($message === '') {
        echo json_encode(['reply' => 'Please type something to chat.']);
        exit;
    }

    $reply = "I'm not sure how to answer that yet.";

    /* ------- BASIC GREETINGS -------- */
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false || strpos($message, 'hey') !== false) {
        $reply = "Hey there 👋! I'm your Fitness Assistant. How can I help you today?";
    }
    elseif (strpos($message, 'how are you') !== false) {
        $reply = "I’m feeling strong and ready to help 💪! What’s on your mind?";
    }

    /* ------- DIET / NUTRITION -------- */
    elseif (strpos($message, 'weight gain') !== false && strpos($message, 'diet') !== false) {
        $reply = "🍗 For healthy weight gain: eat protein-rich foods, add 500 kcal daily, and lift weights.";
    }
    elseif (strpos($message, 'weight loss') !== false || strpos($message, 'fat loss') !== false) {
        $reply = "🥗 For fat loss: eat high protein, reduce sugar, avoid oily foods, drink plenty of water, and take 7–8 hours of sleep.";
    }
    elseif (strpos($message, 'diet plan') !== false) {
        $reply = "🥦 Simple Clean Diet Plan:\nBreakfast: Oats + Eggs\nLunch: Rice + Chicken + Veg\nSnack: Banana Shake\nDinner: Paneer/Tofu + Salad.";
    }
    elseif (strpos($message, 'protein') !== false) {
        $reply = "💪 Best Protein Sources: Eggs, Milk, Paneer, Chicken, Tofu, Lentils, Chickpeas, Peanuts, Fish.";
    }
    elseif (strpos($message, 'calories') !== false) {
        $reply = "🔥 Daily calorie estimate: Weight(kg) × 33. Add 400–500 to gain weight. Reduce 300–400 for fat loss.";
    }
    elseif (strpos($message, 'creatine') !== false) {
        $reply = "⚡ Creatine helps improve strength, energy, and muscle recovery. Safe dosage: 3–5g per day.";
    }
    elseif (strpos($message, 'supplement') !== false) {
        $reply = "💊 Useful Supplements: Whey Protein, Creatine, Multivitamin, Omega-3, Vitamin D. Not mandatory — diet matters most.";
    }

    /* ------- WORKOUTS & GYM -------- */
    elseif (strpos($message, 'workout') !== false) {
        $reply = "🏋️ Try a simple workout split:\nDay 1: Chest/Triceps\nDay 2: Back/Biceps\nDay 3: Legs\nDay 4: Shoulders/Core.\nRest as needed.";
    }
    elseif (strpos($message, 'home workout') !== false) {
        $reply = "🏠 Home Workout Plan:\n• Pushups\n• Squats\n• Plank\n• Lunges\n• Mountain Climbers.\nDo 3 sets each.";
    }
    elseif (strpos($message, 'biceps') !== false) {
        $reply = "💪 Best Biceps Exercises:\n• Dumbbell Curls\n• Hammer Curls\n• Barbell Curls\n• Concentration Curls.";
    }
    elseif (strpos($message, 'abs') !== false || strpos($message, 'six pack') !== false) {
        $reply = "🔥 Abs Workout:\n• Crunches\n• Leg Raises\n• Plank (1–2 mins)\n• Russian Twists. Also maintain calorie deficit!";
    }
    elseif (strpos($message, 'chest') !== false) {
        $reply = "🏋️‍♂️ Chest Workout:\n• Bench Press\n• Pushups\n• Incline Bench\n• Cable Fly.";
    }
    elseif (strpos($message, 'height') !== false) {
        $reply = "📏 Height is mostly genetics, but stretching, good nutrition, and posture help during teenage years.";
    }

    /* ------- HEALTH / ROUTINE -------- */
    elseif (strpos($message, 'sleep') !== false) {
        $reply = "😴 Healthy sleep: 7–9 hours daily. Consistency is key.";
    }
    elseif (strpos($message, 'water') !== false) {
        $reply = "💧 Drink at least 3 liters of water per day — more if you train hard.";
    }
    elseif (strpos($message, 'motivate') !== false || strpos($message, 'motivation') !== false) {
        $reply = "🔥 Motivation Tip: Small progress is still progress. Stay consistent, not perfect!";
    }

    /* ------- FRIENDLY PHRASES -------- */
    elseif (strpos($message, 'thank') !== false) {
        $reply = "Anytime! I'm here to help you grow stronger 😄💪";
    }
    elseif (strpos($message, 'love') !== false) {
        $reply = "❤️ That's sweet! I'm here to support your fitness journey!";
    }

    /* ------- FUN EXTRA -------- */
    elseif (strpos($message, 'joke') !== false) {
        $reply = "😄 Here's one:\nWhy don’t bodybuilders ever get lost?\nBecause they always follow the *pump*! 💪🤣";
    }
    elseif (strpos($message, 'who are you') !== false) {
        $reply = "I'm your AI Fitness Coach 🤖💪 — here to guide your workouts, diet, and motivation!";
    }
    elseif (strpos($message, 'your name') !== false) {
        $reply = "You can call me FitBot 💚!";
    }

    /* ------- ADVANCED FITNESS QUESTIONS -------- */
    elseif (strpos($message, 'muscle building') !== false || strpos($message, 'bulk') !== false) {
        $reply = "💪 To build muscle: Increase protein, lift heavy with progressive overload, sleep 7–8 hrs, and stay consistent!";
    }
    elseif (strpos($message, 'strength') !== false) {
        $reply = "🏋️ For strength: Focus on low reps (3–6), heavy weight, and compound lifts like Squat, Deadlift, Bench.";
    }
    elseif (strpos($message, 'stamina') !== false || strpos($message, 'endurance') !== false) {
        $reply = "🏃 Boost stamina with running, cycling, HIIT, skipping, and consistent cardio 4–5 times a week.";
    }
    elseif (strpos($message, 'warm up') !== false) {
        $reply = "🔥 Good warm-up: 5 mins jogging, arm circles, hip mobility, light stretches — prevents injury!";
    }
    elseif (strpos($message, 'stretch') !== false) {
        $reply = "🤸 Do dynamic stretches before workout and static stretches after to improve flexibility.";
    }

    /* ------- DIET REQUESTS (CUSTOM PLANS) -------- */
    elseif (strpos($message, 'veg diet') !== false) {
        $reply = "🥦 Veg Diet Plan:\n• Breakfast: Oats + Milk\n• Lunch: Rice + Dal + Veggies\n• Snack: Fruit + Nuts\n• Dinner: Paneer/Tofu + Salad.";
    }
    elseif (strpos($message, 'non veg diet') !== false) {
        $reply = "🍗 Non-Veg Diet:\n• Breakfast: Eggs + Toast\n• Lunch: Rice + Chicken\n• Snack: Banana Shake\n• Dinner: Fish/Chicken + Veggies.";
    }
    elseif (strpos($message, 'high protein food') !== false) {
        $reply = "💪 High Protein Foods: Eggs, Chicken, Tuna, Soy, Paneer, Greek Yogurt, Peanuts, Lentils.";
    }
    elseif (strpos($message, 'carbs') !== false) {
        $reply = "🍚 Healthy Carbs: Rice, Oats, Potatoes, Fruits, Whole Wheat Bread, Quinoa.";
    }

    /* ------- MENTAL HEALTH / MOTIVATION -------- */
    elseif (strpos($message, 'stress') !== false) {
        $reply = "🧠 Reduce stress with meditation, walking, deep breathing, good sleep, and talking to loved ones.";
    }
    elseif (strpos($message, 'anxiety') !== false) {
        $reply = "💙 You're not alone. Try slow breathing, hydration, nature walks, and grounding techniques.";
    }
    elseif (strpos($message, 'sad') !== false || strpos($message, 'depress') !== false) {
        $reply = "💛 I’m here for you. Small steps, talk to someone you trust, take breaks, and remember you matter.";
    }

    /* ------- GYM KNOWLEDGE / TIPS -------- */
    elseif (strpos($message, 'form') !== false) {
        $reply = "📌 Always maintain correct form — reduce weight if needed. Good form > heavy weight.";
    }
    elseif (strpos($message, 'pre workout') !== false) {
        $reply = "⚡ Good Pre-Workout: Coffee + Banana or Peanut Butter Bread. Avoid on empty stomach.";
    }
    elseif (strpos($message, 'post workout') !== false) {
        $reply = "🥤 Post-Workout: 20–30g protein + carbs (banana, rice, oats). Helps recovery.";
    }

    /* ------- BODY SPECIFIC -------- */
    elseif (strpos($message, 'shoulder') !== false) {
        $reply = "🏋️ Shoulder Workout: Overhead Press, Lateral Raises, Front Raises, Rear Delt Fly.";
    }
    elseif (strpos($message, 'leg workout') !== false || strpos($message, 'legs') !== false) {
        $reply = "🦵 Leg Day:\n• Squats\n• Lunges\n• Leg Press\n• Deadlift\n• Calf Raises.";
    }
    elseif (strpos($message, 'back workout') !== false) {
        $reply = "💪 Back Workout:\n• Pull-ups\n• Deadlifts\n• Lat Pulldown\n• Rows\n• Face Pulls.";
    }

    /* ------- FUN PERSONALITY -------- */
    elseif (strpos($message, 'are you real') !== false) {
        $reply = "😄 I’m digital… but my fitness knowledge is absolutely real!";
    }
    elseif (strpos($message, 'you there') !== false) {
        $reply = "Always here for you 💚. Tell me what you want help with.";
    }
    elseif (strpos($message, 'bro') !== false) {
        $reply = "😎 Yes bro! Tell me what’s up!";
    }

    /* ------- QUICK CALCULATIONS & TOOLS -------- */
    elseif (preg_match('/bmi\s*([\d\.]+)\s*([\d\.]+)/', $message, $m)) {
        // format: bmi weight height (kg cm)
        $w = floatval($m[1]);
        $hcm = floatval($m[2]);
        if ($hcm > 0) {
            $h = $hcm / 100;
            $bmi = round($w / ($h * $h), 1);
            $reply = "Your BMI is {$bmi}. BMI categories: under 18.5 = underweight, 18.5–24.9 = normal, 25–29.9 = overweight, 30+ = obese.";
        } else {
            $reply = "Please provide height in cm. Example: 'bmi 70 175' (weight kg, height cm).";
        }
    }
    elseif (preg_match('/calorie\s*([\d\.]+)\s*([\d\.]+)/', $message, $m)) {
        // format: calorie weight height (kg cm) simple estimate
        $w = floatval($m[1]);
        $hcm = floatval($m[2]);
        $bmr = round(24 * $w); // simple approximation
        $reply = "Estimated maintenance calories ≈ {$bmr} kcal/day (very rough). For better estimate provide age & activity level.";
    }

    /* ------- DEFAULT -------- */
    else {
        $reply = "I’m here to help with workouts, diet, fitness tips, and motivation 💪.\nTry asking:\n• Best diet plan?\n• How to lose weight?\n• Best workout for abs?\n• How much protein do I need?\n• Make me a workout plan.";
    }

    echo json_encode(['reply' => $reply]);
    exit;
}


    /* =====================================================
       =============== ADMIN PANEL FEATURES =================
       ===================================================== */

    if ($action === "admin_list_users") {
        $q = $pdo->query("SELECT id, name, phone, email FROM users");
        echo json_encode(["users" => $q->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === "admin_list_workouts") {
        $q = $pdo->query("
            SELECT w.*, u.name AS user_name
            FROM workouts w
            LEFT JOIN users u ON u.id = w.user_id
            ORDER BY w.created_at DESC
        ");
        echo json_encode(["workouts" => $q->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === "admin_delete_user") {
        $id = intval($_GET['id']);
        $pdo->query("DELETE FROM users WHERE id=$id");
        echo json_encode(["ok" => true]);
        exit;
    }

    if ($action === "admin_delete_workout") {
        $id = intval($_GET['id']);
        $pdo->query("DELETE FROM workouts WHERE id=$id");
        echo json_encode(["ok" => true]);
        exit;
    }

    /* =====================================================
       =============== ADMIN LOGIN SYSTEM ===================
       ===================================================== */

    if ($action === "admin_login" && $method === "POST") {

        $data = json_decode(file_get_contents("php://input"), true);
        $user = $data['user'] ?? '';
        $pass = $data['pass'] ?? '';

        $ADMIN_USER = "admin";
        $ADMIN_PASS = "admin123";

        if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
            $_SESSION['admin'] = true;
            echo json_encode(["ok" => true]);
        } else {
            echo json_encode(["ok" => false, "error" => "Invalid admin credentials"]);
        }
        exit;
    }

    if ($action === "session_admin") {
        echo json_encode(["ok" => isset($_SESSION['admin'])]);
        exit;
    }

    if ($action === "admin_logout") {
        unset($_SESSION['admin']);
        echo json_encode(["ok" => true]);
        exit;
    }

    if (str_starts_with($action, "admin_")) {
        if (!isset($_SESSION['admin'])) {
            echo json_encode(["ok" => false, "error" => "admin_required"]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad request']);

} 
catch (Exception $e) 
{
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);

}

/* =====================================================
   =============== BOOK A SESSION =======================
   ===================================================== */

if ($method === 'POST' && $action === 'book_session') {

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'error' => 'login_required']);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $trainer_id = $data['trainer_id'] ?? '';
    $session_date = $data['session_date'] ?? '';
    $session_time = $data['session_time'] ?? '';

    if ($trainer_id === '' || $session_date === '' || $session_time === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        INSERT INTO bookings (user_id, trainer_id, session_date, session_time)
        VALUES (?, ?, ?, ?)
    ");

    if ($stmt->execute([$user_id, $trainer_id, $session_date, $session_time])) {
        echo json_encode(['ok' => true, 'message' => 'Booking confirmed!']);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Booking failed!']);
    }

    exit;
}

/* =====================================================
   =============== GET USER BOOKINGS ====================
   ===================================================== */

if ($method === 'GET' && $action === 'get_bookings') {

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'error' => 'login_required']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        SELECT 
            b.booking_id,
            b.session_date,
            b.session_time,
            t.name AS trainer_name,
            t.price
        FROM bookings b
        JOIN trainers t ON t.trainer_id = b.trainer_id
        WHERE b.user_id = ?
        ORDER BY b.session_date ASC
    ");

    $stmt->execute([$user_id]);

    echo json_encode([
        'ok' => true,
        'bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

    exit;
}


?>
