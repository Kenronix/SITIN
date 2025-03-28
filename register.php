<?php
require_once 'conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idno = trim($_POST['idno']);
    $lastname = trim($_POST['lname']);
    $firstname = trim($_POST['fname']);
    $middlename = trim($_POST['mname']);
    $course = $_POST['course'];
    $year = $_POST['year'];
    $email = trim($_POST['address']);
    $username = trim($_POST['uname']);
    $password = $_POST['pass'];
    
    $errors = [];
    
    if (empty($idno) || empty($lastname) || empty($firstname) || empty($email) || empty($username) || empty($password)) {
        $errors[] = "All fields are required";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username already exists";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id_number = ?");
    $stmt->execute([$idno]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "ID number already registered";
    }
    
    if (empty($errors)) {
        $sql = "INSERT INTO users (id_number, lastname, firstname, middlename, course, year_level, email, username, password) 
                VALUES (:idno, :lastname, :firstname, :middlename, :course, :year, :email, :username, :password)";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'idno' => $idno,
                'lastname' => $lastname,
                'firstname' => $firstname,
                'middlename' => $middlename,
                'course' => $course,
                'year' => $year,
                'email' => $email,
                'username' => $username,
                'password' => $password
            ]);
            
            echo "<script>alert('Registration successful!'); window.location.href='login.php';</script>";
            exit();
        } catch(PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Register</title>
</head>
<body>
    <div id="regscontainer">
        <form method="POST" action="register.php">
            <h1>REGISTRATION</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="regscontainer">
                <input type="text" name="idno" id="idno" placeholder="IDNO" value="<?php echo isset($_POST['idno']) ? htmlspecialchars($_POST['idno']) : ''; ?>"><br>
            </div>
            <div class="regscontainer">
                <input type="text" name="lname" id="lname" placeholder="LASTNAME" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>"><br>
            </div>
            <div class="regscontainer">
                <input type="text" name="fname" id="fname" placeholder="FIRSTNAME" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>"><br>
            </div>
            <div class="regscontainer">
                <input type="text" name="mname" id="mname" placeholder="MIDDLENAME" value="<?php echo isset($_POST['mname']) ? htmlspecialchars($_POST['mname']) : ''; ?>"><br>
            </div>
            <div class="regscontainer">
                <select id="course" name="course" required>
                    <option value="">Select Course</option>
                    <option value="BSCS" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSCS') ? 'selected' : ''; ?>>Bachelor of Science in Computer Science</option>
                    <option value="BSIT" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSIT') ? 'selected' : ''; ?>>Bachelor of Science in Information Technology</option>
                    <option value="BSIS" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSIS') ? 'selected' : ''; ?>>Bachelor of Science in Information Systems</option>
                    <option value="BSECE" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSECE') ? 'selected' : ''; ?>>Bachelor of Science in Electronics Engineering</option>
                </select>
            </div>
            <div class="regscontainer">
                <select id="year" name="year" required>
                    <option value="">Select Year Level</option>
                    <?php for($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo (isset($_POST['year']) && $_POST['year'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?>st Year</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="regscontainer">
                <input type="email" name="address" id="address" placeholder="EMAIL ADDRESS" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>"><br>
            </div>
            <div class="regscontainer">
                <input type="text" name="uname" id="uname" placeholder="USERNAME" value="<?php echo isset($_POST['uname']) ? htmlspecialchars($_POST['uname']) : ''; ?>"><br>
            </div>
            <div class="regscontainer">
                <input type="password" name="pass" id="pass" placeholder="PASSWORD"><br>
            </div>
            <div>
                <button type="submit" id="submit">Sign Up</button>    
            </div>
        </form>
    </div>
</body>
</html>
