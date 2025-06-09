# 🚀 FINAL Laravel Cloud Environment Variables

## ✅ CONFIRMED: Vercel URL is now included in stateful domains!

Your Vercel URL `commease-frontend.vercel.app` is now properly included in both:
- ✅ **CORS allowed origins**
- ✅ **Sanctum stateful domains**

## 📋 Complete Environment Variables for Laravel Cloud

Copy these **EXACT** environment variables to your Laravel Cloud dashboard:

```env
# App Configuration
APP_NAME=CommEase
APP_ENV=production
APP_DEBUG=false
APP_URL="https://commease-be-master-lpv6rd.laravel.cloud"

# Frontend Configuration
FRONTEND_URL="https://commease-frontend.vercel.app"

# Session Configuration (CRITICAL for fixing 419 errors)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE_COOKIE=none

# Sanctum Configuration (CRITICAL for cookie authentication)
SANCTUM_STATEFUL_DOMAINS="commease-frontend.vercel.app,localhost:3000,localhost:5173,127.0.0.1:3000"

# Database Configuration (update with your actual values)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database

# Mail Configuration (if needed)
MAIL_MAILER=log
```

## 🔧 What's Fixed

### 1. Vercel URL Inclusion
- ✅ Added `commease-frontend.vercel.app` to CORS allowed origins
- ✅ Added `commease-frontend.vercel.app` to Sanctum stateful domains
- ✅ Both automatic (via FRONTEND_URL) and explicit inclusion

### 2. Session Configuration
- ✅ `SESSION_DOMAIN=null` (allows cross-domain cookies)
- ✅ `SESSION_SECURE_COOKIE=true` (required for HTTPS)
- ✅ `SESSION_SAME_SITE_COOKIE=none` (required for cross-domain)

### 3. CORS Configuration
- ✅ Specific origins instead of wildcard
- ✅ Credentials support enabled
- ✅ Your Vercel app explicitly allowed

## 🚀 Deployment Checklist

### Step 1: Update Environment Variables
1. Go to your Laravel Cloud dashboard
2. Navigate to Environment Variables
3. Update/add the variables listed above
4. **Important:** Remove any old conflicting variables

### Step 2: Deploy Updated Code
1. Commit and push the updated config files:
   - `config/cors.php`
   - `config/sanctum.php` 
   - `config/session.php`
2. Deploy to Laravel Cloud

### Step 3: Test Configuration
Run this command in Laravel Cloud terminal:
```bash
php artisan test:cors-config
```

You should see:
- ✅ `commease-frontend.vercel.app` in CORS allowed origins
- ✅ `commease-frontend.vercel.app` in Sanctum stateful domains

### Step 4: Test CSRF Endpoint
```bash
curl -X GET "https://commease-be-master-lpv6rd.laravel.cloud/sanctum/csrf-cookie" \
  -H "Origin: https://commease-frontend.vercel.app" \
  -H "Referer: https://commease-frontend.vercel.app" \
  -v
```

Expected response headers:
- `Set-Cookie: XSRF-TOKEN=...`
- `Set-Cookie: commease_session=...`
- `Access-Control-Allow-Origin: https://commease-frontend.vercel.app`
- `Access-Control-Allow-Credentials: true`

## 🎯 Frontend Requirements

Make sure your Vercel frontend has:

```javascript
// Axios configuration
const api = axios.create({
  baseURL: 'https://commease-be-master-lpv6rd.laravel.cloud',
  withCredentials: true, // CRITICAL!
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }
});

// Before any authenticated requests
await api.get('/sanctum/csrf-cookie');
```

## ✅ Expected Results

After implementing these changes:
- ❌ **No more 419 errors**
- ✅ **Cookies will be set and sent properly**
- ✅ **CSRF protection will work**
- ✅ **Authentication will work across domains**

## 🆘 If Issues Persist

1. Check browser dev tools → Network → Response headers
2. Verify cookies are being set with correct attributes
3. Ensure both domains use HTTPS
4. Run `php artisan test:cors-config` to verify configuration

The configuration is now complete and your Vercel URL is properly included!
