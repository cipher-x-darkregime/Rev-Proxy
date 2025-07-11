<?php
$error_code = http_response_code();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $error_code; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="display-1"><?php echo $error_code; ?></h1>
        <p class="lead">
            <?php
            switch($error_code) {
                case 403:
                    echo "Access Forbidden";
                    break;
                case 404:
                    echo "Page Not Found";
                    break;
                case 500:
                    echo "Internal Server Error";
                    break;
                default:
                    echo "An Error Occurred";
            }
            ?>
        </p>
        <a href="login.php" class="btn btn-primary">Return to Login</a>
    </div>
</body>
</html> 