<?php
session_start(); // Start the session to access session variables
    // Database configuration
    define("SQLSERVER", "localhost");
    define("SQLUSER", "user");
    define("SQLPASSWD", "password");
    define("SQLDB", "database");

    // Connect to the database
    function connectToDatabase() {
        $connect = new mysqli(SQLSERVER, SQLUSER, SQLPASSWD, SQLDB);
        if ($connect->connect_error) {
            die(json_encode(["status" => "error", "message" => "Database connection failed: " . $connect->connect_error]));
        }
        return $connect;
    }
// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // <-- Starting brace for POST check
    // Get the score from the form, fallback to session if it's not present
    $score = $_POST['score'] ?? ($_SESSION['score'] ?? 0);

    // Validate score
    if ($score <= 0) {
        echo json_encode(["status" => "error", "message" => "No valid score to save."]);
        exit;
    }

    // Save high score
    if (isset($_SESSION['score'])) { // <-- Only checking for session score here, remove redundant POST check
        $name = $_POST['name'] ?? 'Anonymous';
        $score = $_SESSION['score'];
        $date = date('Y-m-d H:i:s');

        if ($name === '' || $score <= 0) {
            echo json_encode(["status" => "error", "message" => "Invalid name or score."]);
            exit;
        }


        $connect = connectToDatabase();
        $stmt = $connect->prepare("INSERT INTO poker_highscores (name, score, date) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $name, $score, $date);

        if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Score saved successfully."]);
                } else {
                // Return a JSON response with an error message
                echo json_encode(["status" => "error", "message" => "Failed to save score: " . $connect->error]);
                }

        $stmt->close();
        $connect->close();
        exit;
    } // <-- Closing brace for the 'if' checking $_SESSION['score']
} // <-- Closing brace for the 'if' checking POST request

// Fetch top scores
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'display') {
    $connect = connectToDatabase();
    $query = "SELECT name, score, date FROM poker_highscores ORDER BY score DESC, date DESC LIMIT 10";
    $result = $connect->query($query);

    if ($result->num_rows > 0) {
      /*  echo "<table>";
        echo "<tr><th>Name</th><th>Score</th><th>Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['name']}</td>
                    <td>{$row['score']}</td>
                    <td>{$row['date']}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "No scores available.";
    } */
    $scores = [];
        while ($row = $result->fetch_assoc()) {
            $scores[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $scores]);
    } else {
        echo json_encode(["status" => "success", "data" => [], "message" => "No scores available."]);
    }

    $connect->close();
    exit;
}
http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid request."]);
?>