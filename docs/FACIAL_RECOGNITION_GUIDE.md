# Facial Recognition Registration & Verification System
## Implementation Guide for Professor Hawkeinstein's Educational Foundation

### Overview
This system implements biometric facial recognition for student registration and login verification. It uses:
- **face-api.js** - Client-side facial detection and recognition (JavaScript library)
- **Euclidean distance** - Face descriptor comparison algorithm
- **15-second re-verification cycle** - For testing/demo purposes
- **MariaDB serialization** - Facial signatures stored as serialized PHP arrays

---

## System Architecture

### Frontend Components

#### 1. **register.html** - Student Registration with Facial Capture
**Location:** `/home/steve/Professor_Hawkeinstein/register.html`

**Features:**
- Multi-step registration form (3-step progress indicator)
- Real-time face detection using face-api.js
- Facial capture with single image
- 15-second auto-verification countdown
- Euclidean distance matching (threshold: 0.6)
- Comprehensive form validation

**User Flow:**
1. Student fills registration form (name, email, username, password)
2. Clicks "Enable Camera" to start facial capture
3. Face is detected and displayed in real-time
4. Student clicks "Capture Face" to save initial facial image
5. System automatically re-verifies every 15 seconds
6. When match confirmed, "Create Account" button is enabled
7. On submission, facial descriptor is sent to backend along with user data

**Technical Details:**
- Uses Canvas API to draw video frames
- Extracts 128-dimensional face descriptor using face-api.js
- Compares distances between capture and verification frames
- Success threshold: distance < 0.6

---

### Backend Components

#### 2. **api/auth/register.php** - Account Creation Endpoint
**Location:** `/home/steve/Professor_Hawkeinstein/api/auth/register.php`

**Purpose:** Creates new student account with facial biometric

**Request:**
```json
{
  "fullName": "John Doe",
  "email": "john@example.com",
  "username": "john_doe",
  "password": "SecurePass123",
  "facialDescriptor": [0.123, -0.456, 0.789, ...]  // 128 floats
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Account created successfully",
  "userId": 3
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Username already taken"
}
```

**Validation Rules:**
- Full Name: 2-100 characters
- Email: Valid email format, unique in database
- Username: 3-20 chars (letters, numbers, underscores), unique
- Password: Min 8 chars, uppercase, lowercase, number required
- Facial Descriptor: Array of 128 float values

**Database Storage:**
- Stores in `users.facial_signature` column
- Data is serialized as PHP serialized array
- Also stores password as bcrypt hash
- Default role: 'student'

---

#### 3. **api/auth/verify_face.php** - Facial Verification Endpoint
**Location:** `/home/steve/Professor_Hawkeinstein/api/auth/verify_face.php`

**Purpose:** Verifies facial match during login

**Request:**
```json
{
  "username": "john_doe",
  "facialDescriptor": [0.123, -0.456, 0.789, ...]  // 128 floats
}
```

**Response:**
```json
{
  "success": true,
  "match": true,
  "userId": 3,
  "distance": 0.4523,
  "message": "Facial match confirmed"
}
```

**Algorithm:**
1. Retrieves stored facial signature from database
2. Calculates Euclidean distance between stored and current descriptor
3. Returns match status and distance value
4. Threshold: 0.6 (typical for face matching)

---

### Database Schema

#### `users` Table
```sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    facial_signature BLOB NULL,        -- Serialized array of 128 floats
    voice_signature BLOB NULL,         -- Future: voice biometric
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_email (email)
)
```

**Key Columns:**
- `facial_signature`: Stores serialized PHP array (face descriptor)
  - Can be unserialized with `unserialize()` in PHP
  - Contains 128 float values representing facial features
  - Example: `a:128:{i:0;d:0.123;i:1;d:-0.456;...}`

---

## Facial Recognition Technology

### face-api.js Library
**Source:** `https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js`
**Models:** TensorFlow.js-based neural networks

**Capabilities:**
- `detectSingleFace()` - Locates face in image
- `withFaceLandmarks()` - Identifies facial features (eyes, nose, mouth, etc.)
- `withFaceDescriptor()` - Generates 128-dimensional feature vector

**Process:**
1. Captures video frame from webcam
2. Passes frame to face detection neural network
3. If face found, extracts 128 unique features
4. Stores as array of floats (128 values)
5. Uses Euclidean distance for comparison

### Euclidean Distance Calculation
```javascript
distance = sqrt(sum((descriptor1[i] - descriptor2[i])^2))

Example:
- descriptor1 = [0.1, 0.2, 0.3, ...]
- descriptor2 = [0.11, 0.19, 0.31, ...]
- distance = sqrt((0.01)^2 + (0.01)^2 + (0.01)^2 + ...)
```

**Matching:**
- Distance < 0.6 = MATCH (face recognized)
- Distance >= 0.6 = NO MATCH (face not recognized)
- Typical match distance: 0.3-0.5
- Typical non-match distance: 0.7-1.5

---

## Testing Guide

### 1. Test Registration
```bash
# Navigate to registration page
http://localhost/Professor_Hawkeinstein/register.html

# Fill in form:
# - Full Name: John Doe
# - Email: john@example.com
# - Username: john_doe
# - Password: SecurePass123

# Click "Enable Camera" and allow browser permission

# Wait for face detection (green status when face visible)

# Click "Capture Face" 

# System will automatically verify every 15 seconds

# When match confirmed, click "Create Account"
```

### 2. Test Facial Verification (cURL)
```bash
# First, get face descriptor during registration (check console logs)
# Then test verification endpoint:

curl -X POST http://localhost/Professor_Hawkeinstein/api/auth/verify_face.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john_doe",
    "facialDescriptor": [0.123, -0.456, 0.789, ...]
  }'
```

### 3. View Stored Facial Data
```bash
# SSH into server and check database
mysql -u eduai_user -p professorhawkeinstein_platform

# Query:
SELECT user_id, username, LENGTH(facial_signature) as data_size 
FROM users 
WHERE username = "john_doe";

# Output shows size of stored facial signature (typically 2000-3000 bytes)
```

### 4. Monitor Registration Logs
```bash
# Check registration errors
tail -f /var/www/html/Professor_Hawkeinstein/logs/register_errors.log

# Check verification logs
tail -f /var/www/html/Professor_Hawkeinstein/logs/verify_errors.log
```

---

## Configuration

### Verification Timing (Testing)
**Current Setting:** 15-second countdown before auto-verification

**File:** `register.html`, line 350
```javascript
function startVerificationCountdown() {
    verificationTimer.style.display = 'block';
    let countdown = 15;  // ← Change this value (in seconds)
    
    verificationCountdown = setInterval(() => {
        countdown--;
        timerValue.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(verificationCountdown);
            verifyCameraBtn.click(); // Auto-trigger verification
        }
    }, 1000);
}
```

**To change timing:**
- For faster testing: Change `15` to `5` (5 seconds)
- For slower testing: Change `15` to `30` (30 seconds)
- For manual-only: Remove auto-trigger `verifyCameraBtn.click()`

### Face Matching Threshold
**Current Setting:** 0.6 (standard threshold)

**File:** `register.html`, line 433
```javascript
const threshold = 0.6; // ← Adjust for sensitivity
```

**Threshold Guidance:**
- `0.3` = Very strict (difficult to match, few false positives)
- `0.6` = Standard (recommended, balanced)
- `0.8` = Lenient (easy to match, more false positives)

**For Production:** Use 0.5-0.6
**For Testing:** Can use 0.6-0.7 for easier testing

---

## File Structure

```
/home/steve/Professor_Hawkeinstein/
├── register.html                 # Registration page with facial capture
├── login.html                    # Updated login page
└── api/auth/
    ├── register.php              # Account creation endpoint
    ├── verify_face.php           # Facial verification endpoint
    └── [existing files]

/var/www/html/Professor_Hawkeinstein/
├── register.html                 # (Copy of above)
├── login.html                    # (Copy of above)
├── api/auth/
│   ├── register.php              # (Copy of above)
│   ├── verify_face.php           # (Copy of above)
│   └── [existing files]
└── logs/
    ├── register_errors.log       # Registration error logging
    └── verify_errors.log         # Verification error logging
```

---

## API Integration Points

### 1. Register New User
**Called From:** `register.html` (on form submit)
**Endpoint:** `POST /Professor_Hawkeinstein/api/auth/register.php`
**Parameters:**
- fullName, email, username, password, facialDescriptor
**Returns:** userId, success status

### 2. Verify Facial Match
**Called From:** Login form (future enhancement)
**Endpoint:** `POST /Professor_Hawkeinstein/api/auth/verify_face.php`
**Parameters:**
- username, facialDescriptor
**Returns:** match status, userId, distance

---

## Security Considerations

1. **HTTPS Required** (Production)
   - Facial data is sensitive biometric
   - All requests must use HTTPS
   - Configure SSL certificates before deployment

2. **Password Hashing**
   - Uses bcrypt (cost factor: 10)
   - Stored in `password_hash` column
   - Never stored in plain text

3. **Biometric Data Protection**
   - Serialized and stored as BLOB
   - Should be encrypted at rest (future: AES-256)
   - Access limited to authenticated requests

4. **CORS Security**
   - Restrict cross-origin requests
   - Add CORS headers to PHP endpoints
   - Validate origin in production

5. **Rate Limiting** (Future)
   - Prevent brute force registration
   - Limit verification attempts per user
   - Implement 429 Too Many Requests responses

---

## Troubleshooting

### Camera Access Denied
**Symptom:** "Camera access denied" error
**Solution:**
1. Check browser permissions for https://localhost
2. Grant camera permission in browser settings
3. Restart browser and try again
4. Ensure HTTPS or localhost domain

### Face Not Detected
**Symptom:** "No Face Detected" status
**Solution:**
1. Ensure adequate lighting (camera needs to see face clearly)
2. Position face directly in front of camera
3. Remove glasses or obstructions if possible
4. Check camera works in other applications

### Verification Always Fails
**Symptom:** Distance too high (> 0.6), verification failing repeatedly
**Solution:**
1. Ensure consistent lighting between capture and verification
2. Keep same facial expression
3. Maintain same distance from camera
4. Check for extreme head angles (face should be frontal)

### Database Connection Error
**Symptom:** "Database error" in response
**Solution:**
1. Verify MariaDB running: `sudo systemctl status mariadb`
2. Check credentials in `database.php`
3. Verify database exists: `mysql -u eduai_user -p`
4. Check logs: `tail -f /var/www/html/Professor_Hawkeinstein/logs/register_errors.log`

### Face-api.js Models Not Loading
**Symptom:** "Error loading facial recognition models"
**Solution:**
1. Check internet connection (models downloaded from CDN)
2. Check browser console for network errors (F12)
3. Try refreshing page
4. Check firewall blocks to jsdelivr.net

---

## Future Enhancements

1. **Voice Authentication**
   - Implement voice biometric capture
   - Store voice signature in database
   - Use during login verification

2. **Liveness Detection**
   - Ensure user is live (not photo/video replay)
   - Request specific facial expressions
   - Prevent spoofing attacks

3. **Multi-Factor Authentication**
   - Combine facial + voice + password
   - Provide fallback methods
   - Support security questions

4. **Encryption at Rest**
   - Encrypt facial_signature with AES-256
   - Store encryption keys securely
   - Implement key rotation

5. **Biometric Template Improvement**
   - Re-capture facial data periodically
   - Update template as user ages
   - Improve matching accuracy over time

6. **Anti-Spoofing**
   - Detect face liveness
   - Detect digital spoof (screen replay)
   - Require eye blink or head movement

---

## References

- **face-api.js Documentation:** https://github.com/vladmandic/face-api
- **Face Recognition Concepts:** https://medium.com/@ageitgey/machine-learning-is-fun-part-4-modern-face-recognition-with-deep-learning-c3cffc121d78
- **Euclidean Distance:** https://en.wikipedia.org/wiki/Euclidean_distance

---

## Support & Debugging

### Enable Verbose Logging
**register.html:**
```javascript
// Add before loadModels() call:
console.log('Initialization starting...');
// Add in detection loop:
console.log('Face detected:', detections);
```

### Check Facial Descriptor Format
**Browser Console:**
```javascript
console.log('Captured descriptor:', capturedFaceDescriptor);
console.log('Descriptor length:', capturedFaceDescriptor.length);
console.log('First 5 values:', Array.from(capturedFaceDescriptor).slice(0, 5));
```

### Test Registration Endpoint
```bash
curl -X POST http://localhost/Professor_Hawkeinstein/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "fullName": "Test User",
    "email": "test@example.com",
    "username": "testuser123",
    "password": "TestPass123",
    "facialDescriptor": ['$(python3 -c "import json; print(','.join(str(0.1 + i*0.01) for i in range(128)))")']
  }' | jq
```

---

**Last Updated:** November 18, 2025
**System:** Professor Hawkeinstein's Educational Foundation
**Status:** ✅ Ready for Testing
