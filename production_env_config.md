# Production Environment Configuration for Laravel Cloud + Vercel

## 🚨 Current Issue: 419 Error & Cookie Problems

Your current environment variables have issues that prevent proper CORS and session handling.

## ✅ CORRECTED Environment Variables for Laravel Cloud

Replace your current environment variables with these corrected ones:

```env
# App Configuration
APP_NAME=CommEase
APP_ENV=production
APP_DEBUG=false
APP_URL="https://commease-be-master-lpv6rd.laravel.cloud"

# Frontend Configuration
FRONTEND_URL="https://commease-frontend.vercel.app"

# Session Configuration (CRITICAL FIXES)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE_COOKIE=none

# Sanctum Configuration (CRITICAL FIXES)
SANCTUM_STATEFUL_DOMAINS="commease-frontend.vercel.app,localhost:3000,localhost:5173,127.0.0.1:3000"

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## 🔧 Key Changes Made

### 1. Session Domain
**BEFORE:** `SESSION_DOMAIN=commease-frontend.vercel.app`
**AFTER:** `SESSION_DOMAIN=null`

**Why:** Cross-domain setups require `null` domain to allow cookies across different domains.

### 2. Session Security
**ADDED:** 
- `SESSION_SECURE_COOKIE=true` (required for HTTPS)
- `SESSION_SAME_SITE_COOKIE=none` (required for cross-domain)

### 3. Sanctum Stateful Domains
**BEFORE:** `SANCTUM_STATEFUL_DOMAIN=commease-frontend.vercel.app`
**AFTER:** `SANCTUM_STATEFUL_DOMAINS="commease-frontend.vercel.app,localhost:3000,localhost:5173"`

**Why:** 
- Use `SANCTUM_STATEFUL_DOMAINS` (plural)
- Include development domains for testing

### 4. CORS Configuration
Updated `config/cors.php` to specify exact allowed origins instead of `*`.

## 🚀 Frontend Configuration (Vercel App)

Make sure your frontend is configured correctly:

### 1. Axios Configuration
```javascript
// In your axios setup
import axios from 'axios';

const api = axios.create({
  baseURL: 'https://commease-be-master-lpv6rd.laravel.cloud',
  withCredentials: true, // CRITICAL: Enable credentials
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }
});

// CSRF Token setup
export const getCsrfToken = async () => {
  await api.get('/sanctum/csrf-cookie');
};

export default api;
```

### 2. Authentication Flow
```javascript
// Before making authenticated requests
await getCsrfToken();

// Then make your login request
const response = await api.post('/api/auth/login', {
  email: 'user@example.com',
  password: 'password'
});
```

## 🔍 Testing the Fix

### 1. Test CSRF Cookie Endpoint
```bash
curl -X GET "https://commease-be-master-lpv6rd.laravel.cloud/sanctum/csrf-cookie" \
  -H "Origin: https://commease-frontend.vercel.app" \
  -H "Referer: https://commease-frontend.vercel.app" \
  -v
```

### 2. Check Response Headers
Look for these headers in the response:
- `Set-Cookie: XSRF-TOKEN=...`
- `Set-Cookie: commease_session=...`
- `Access-Control-Allow-Origin: https://commease-frontend.vercel.app`
- `Access-Control-Allow-Credentials: true`

## 🛠️ Deployment Steps

### Step 1: Update Laravel Cloud Environment Variables
In your Laravel Cloud dashboard, update these environment variables:

```env
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE_COOKIE=none
SANCTUM_STATEFUL_DOMAINS="commease-frontend.vercel.app,localhost:3000,localhost:5173,127.0.0.1:3000"
FRONTEND_URL="https://commease-frontend.vercel.app"
```

**Important:** Make sure `FRONTEND_URL` is set correctly as it's used by Sanctum to automatically include your frontend domain.

### Step 2: Deploy Updated Configuration
1. Commit and push the updated `config/cors.php` and `config/session.php` files
2. Deploy to Laravel Cloud
3. Run the test command: `php artisan test:cors-config`

### Step 3: Test CSRF Endpoint
```bash
curl -X GET "https://commease-be-master-lpv6rd.laravel.cloud/sanctum/csrf-cookie" \
  -H "Origin: https://commease-frontend.vercel.app" \
  -H "Referer: https://commease-frontend.vercel.app" \
  -v
```

### Step 4: Verify in Browser
1. Open browser dev tools → Network tab
2. Visit your Vercel app
3. Check that CSRF request sets cookies with proper attributes

## 🐛 Troubleshooting

### If you still get 419 errors:
1. Check browser dev tools → Network → Response headers
2. Verify CSRF token is being sent in requests
3. Ensure `withCredentials: true` in frontend

### If cookies aren't being set:
1. Verify `SESSION_SAME_SITE_COOKIE=none`
2. Verify `SESSION_SECURE_COOKIE=true`
3. Check that both domains use HTTPS

### Common Issues:
- **Mixed HTTP/HTTPS**: Both domains must use HTTPS in production
- **Wrong domain format**: Don't include `https://` in `SANCTUM_STATEFUL_DOMAINS`
- **Missing withCredentials**: Frontend must send `withCredentials: true`
