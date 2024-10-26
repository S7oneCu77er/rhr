<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tool Access</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0 auto;
            padding: 10px;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            min-width: 750px;
            margin: 10px auto;
            padding: 55px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
            font-size: 2rem;
            margin-bottom: 50px;
            margin-top: 10px;
        }

        .tool-box {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping to the next line */
            justify-content: space-between;
            margin-top: 30px;
        }

        .tool {
            background-color: #5c9ae1;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            width: calc(25% - 10px); /* Ensure 3 tools per row with spacing */
            margin: 10px;
            transition: background-color 0.3s ease;
        }

        .tool:hover {
            background-color: #428bca;
        }

        .tool-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        footer {
            text-align: center;
            margin-top: 50px;
            color: #888;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Tool Access Panel</h1>
    <div class="tool-box">
        <a href="convert_csv.php" class="tool">
            <div class="tool-icon">🗃️</div>
            Convert DB
        </a>
        <a href="lines.php" class="tool">
            <div class="tool-icon">📄</div>
            Count Code Lines
        </a>
        <a href="translate.php" class="tool">
            <div class="tool-icon">🌐</div>
            Check Translation
        </a>
        <a href="view_tree.php" class="tool">
            <div class="tool-icon">📁</div>
            View File Tree
        </a>
        <a href="create_admin.php" class="tool">
            <div class="tool-icon">👤</div>
            Create Admin User
        </a>
        <a href="view_db.php" class="tool">
            <div class="tool-icon">📊</div>
            View Database
        </a>
        <a href="attendance.php" class="tool">
            <div class="tool-icon">⏰</div>
            Attendance Tracking
        </a>
        <a href="attendance_summary.php" class="tool">
            <div class="tool-icon">📅</div>
            Attendance Summary
        </a>
        <a href="db_backup.php" class="tool">
            <div class="tool-icon">💾</div>
            Database Backup
        </a>
    </div>
</div>

<footer>
    <p dir="auto">&copy; 2008-<?php echo date('Y'); ?> StoneGaming - All rights reserved</p>
</footer>
</body>
</html>
