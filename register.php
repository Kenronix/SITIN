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
    <title>Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        #regscontainer {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 40px;
        }

        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        .regscontainer {
            margin-bottom: 20px;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input:focus, select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background: white;
        }

        input::placeholder {
            color: #a0a0a0;
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
            padding-right: 40px;
        }

        button {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            background: #5a6fd6;
            transform: translateY(-1px);
        }

        .error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .error p {
            margin: 5px 0;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0a0a0;
        }

        .form-group input {
            padding-left: 45px;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            #regscontainer {
                padding: 20px;
            }

            h1 {
                font-size: 24px;
            }

            input, select, button {
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body>
    <div id="regscontainer">
        <form method="POST" action="register.php">
            <h1>Create Account</h1>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <i class="fas fa-id-card"></i>
                <input type="text" name="idno" id="idno" placeholder="ID Number" value="<?php echo isset($_POST['idno']) ? htmlspecialchars($_POST['idno']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="lname" id="lname" placeholder="Last Name" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="fname" id="fname" placeholder="First Name" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" name="mname" id="mname" placeholder="Middle Name" value="<?php echo isset($_POST['mname']) ? htmlspecialchars($_POST['mname']) : ''; ?>">
            </div>

            <div class="form-group">
                <i class="fas fa-graduation-cap"></i>
                <select id="course" name="course" required>
                    <option value="">Select Course</option>
                    <option value="BSCS" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSCS') ? 'selected' : ''; ?>>Bachelor of Science in Computer Science</option>
                    <option value="BSIT" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSIT') ? 'selected' : ''; ?>>Bachelor of Science in Information Technology</option>
                    <option value="BSIS" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSIS') ? 'selected' : ''; ?>>Bachelor of Science in Information Systems</option>
                    <option value="BSECE" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSECE') ? 'selected' : ''; ?>>Bachelor of Science in Electronics Engineering</option>
                </select>
            </div>

            <div class="form-group">
                <i class="fas fa-calendar"></i>
                <select id="year" name="year" required>
                    <option value="">Select Year Level</option>
                    <?php for($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo (isset($_POST['year']) && $_POST['year'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?>st Year</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="address" id="address" placeholder="Email Address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <i class="fas fa-user-circle"></i>
                <input type="text" name="uname" id="uname" placeholder="Username" value="<?php echo isset($_POST['uname']) ? htmlspecialchars($_POST['uname']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="pass" id="pass" placeholder="Password" required>
            </div>

            <button type="submit">
                <i class="fas fa-user-plus"></i> Sign Up
            </button>

            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>
</body>
</html>
