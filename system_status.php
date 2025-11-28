<!DOCTYPE html>
<html>
<head>
    <title>System Status Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Professor Hawkeinstein System Status</h1>
    
    <h2>1. Database Configuration</h2>
    <?php
    require_once 'config/database.php';
    try {
        $db = getDB();
        echo '<div class="status success">✓ Database connection successful</div>';
        
        // Check student_advisors table
        $result = $db->query("SHOW TABLES LIKE 'student_advisors'")->fetchAll();
        if (count($result) > 0) {
            echo '<div class="status success">✓ student_advisors table exists</div>';
        } else {
            echo '<div class="status error">✗ student_advisors table NOT found</div>';
        }
        
        // Check agents table
        $agents = $db->query("SELECT * FROM agents WHERE agent_id = 1")->fetchAll(PDO::FETCH_ASSOC);
        if (count($agents) > 0) {
            echo '<div class="status success">✓ Professor Hawkeinstein agent found</div>';
            echo '<pre>' . print_r($agents[0], true) . '</pre>';
        } else {
            echo '<div class="status error">✗ Professor Hawkeinstein agent NOT found</div>';
        }
        
        // Check student advisors
        $advisors = $db->query("SELECT COUNT(*) as count FROM student_advisors WHERE is_active=1")->fetch();
        echo '<div class="status info">Active student advisor assignments: ' . $advisors['count'] . '</div>';
        
        // Show sample student advisor
        $sample = $db->query("
            SELECT sa.advisor_instance_id, sa.student_id, u.username, a.agent_name 
            FROM student_advisors sa 
            LEFT JOIN users u ON sa.student_id = u.user_id 
            LEFT JOIN agents a ON sa.advisor_type_id = a.agent_id 
            WHERE sa.is_active=1 
            LIMIT 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        if (count($sample) > 0) {
            echo '<h3>Sample Student-Advisor Assignment:</h3>';
            echo '<pre>' . print_r($sample[0], true) . '</pre>';
        }
        
    } catch (Exception $e) {
        echo '<div class="status error">✗ Database error: ' . $e->getMessage() . '</div>';
    }
    ?>
    
    <h2>2. C++ Agent Service</h2>
    <div id="cppStatus">Checking...</div>
    
    <h2>3. Chat Test</h2>
    <div id="chatTest">
        <button onclick="testChat()">Test llama.cpp Chat</button>
        <div id="chatResult"></div>
    </div>
    
    <script>
        // Test C++ service
        fetch('http://localhost:8080/health')
            .then(r => r.json())
            .then(data => {
                document.getElementById('cppStatus').innerHTML = 
                    '<div class="status success">✓ C++ Agent Service is running on port 8080</div>' +
                    '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(err => {
                document.getElementById('cppStatus').innerHTML = 
                    '<div class="status error">✗ C++ Agent Service is NOT responding: ' + err.message + '</div>';
            });
        
        function testChat() {
            document.getElementById('chatResult').innerHTML = '<div class="status info">Testing chat...</div>';
            
            fetch('http://localhost:8080/api/chat', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    system_prompt: 'You are Professor Hawkeinstein, an expert math tutor.',
                    messages: [{role: 'user', content: 'What is 5+7?'}],
                    temperature: 0.7
                })
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById('chatResult').innerHTML = 
                    '<div class="status success">✓ Chat test successful!</div>' +
                    '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(err => {
                document.getElementById('chatResult').innerHTML = 
                    '<div class="status error">✗ Chat test failed: ' + err.message + '</div>';
            });
        }
    </script>
    
    <h2>Next Steps</h2>
    <ol>
        <li>If all checks are green, go to <a href="student_dashboard.html">Student Dashboard</a></li>
        <li>Login with your student credentials</li>
        <li>You should see "Professor Hawkeinstein is ready to help!" instead of "No advisor assigned"</li>
        <li>Try sending a chat message to test the full integration</li>
    </ol>
</body>
</html>
