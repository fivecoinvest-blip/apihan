<?php
/**
 * Return Page - Player lands here after game ends
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Completed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        .message { font-size: 18px; margin: 20px 0; color: #555; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .btn:hover { background: #5568d3; }
        .icon { font-size: 60px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🎮</div>
        <h1>Thanks for Playing!</h1>
        <p class="message">Your game session has ended. Check your balance to see your results.</p>
        <div>
            <a href="/apihan/" class="btn">🎲 Play Again</a>
            <a href="/apihan/index.php" class="btn">🏠 Home</a>
        </div>
    </div>
</body>
</html>
