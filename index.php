<?php
// Firebase credentials
define('FIREBASE_API_KEY', 'AIzaSyBft_9j0yQ0jYY8znpEYXAf7yVZaM_KaXo');
define('FIREBASE_AUTH_URL', 'https://identitytoolkit.googleapis.com/v1/accounts:');
define('FIREBASE_DATABASE_URL', 'https://bigbrain-jar-default-rtdb.firebaseio.com');

// Function to make HTTP requests
function firebaseRequest($url, $method, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => $error];
    }

    return json_decode($response, true);
}

// User registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $username = $_POST['username'];

    $response = firebaseRequest(FIREBASE_AUTH_URL . "signUp?key=" . FIREBASE_API_KEY, 'POST', [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true
    ]);

    if (isset($response['error'])) {
        echo json_encode(['error' => $response['error']['message']]);
    } else {
        $userId = $response['localId'];
        $databaseUrl = FIREBASE_DATABASE_URL . "/users/$userId.json";

        // Save user details in the database
        firebaseRequest($databaseUrl, 'PUT', [
            'username' => $username,
            'email' => $email
        ]);

        echo json_encode(['success' => 'User registered successfully']);
    }
}

// User login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $response = firebaseRequest(FIREBASE_AUTH_URL . "signInWithPassword?key=" . FIREBASE_API_KEY, 'POST', [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true
    ]);

    if (isset($response['error'])) {
        echo json_encode(['error' => $response['error']['message']]);
    } else {
        echo json_encode(['success' => 'Login successful', 'idToken' => $response['idToken']]);
    }
}

// Add a post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'addPost') {
    $idToken = $_POST['idToken'];
    $content = $_POST['content'];

    // Verify user token
    $userVerification = firebaseRequest(FIREBASE_AUTH_URL . "lookup?key=" . FIREBASE_API_KEY, 'POST', [
        'idToken' => $idToken
    ]);

    if (isset($userVerification['error'])) {
        echo json_encode(['error' => 'Invalid user token']);
    } else {
        $userId = $userVerification['users'][0]['localId'];
        $username = $userVerification['users'][0]['displayName'] ?? 'Anonymous';
        $timestamp = time() * 1000; // Firebase stores timestamps in milliseconds

        $databaseUrl = FIREBASE_DATABASE_URL . "/posts.json";

        // Save post
        $post = [
            'content' => $content,
            'timestamp' => $timestamp,
            'username' => $username,
            'likes' => 0,
            'dislikes' => 0
        ];
        $response = firebaseRequest($databaseUrl, 'POST', $post);

        echo json_encode(['success' => 'Post added successfully', 'postId' => $response['name']]);
    }
}

// Fetch posts
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetchPosts') {
    $databaseUrl = FIREBASE_DATABASE_URL . "/posts.json";
    $posts = firebaseRequest($databaseUrl, 'GET');
    echo json_encode($posts);
}
?>
