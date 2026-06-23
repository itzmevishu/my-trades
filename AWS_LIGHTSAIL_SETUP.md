# AWS Lightsail Deployment Guide - Quick Start
## For BankNifty AI Trading Tool

**Status:** PRIMARY HOSTING SOLUTION (Laravel Cloud doesn't support static IP)  
**Cost:** ₹400/month (FREE for first 3 months)  
**Setup Time:** 45-60 minutes  
**Difficulty:** Easy-Medium (LAMP + manual Laravel setup)

---

## 🎯 Why AWS Lightsail?

**Confirmed:** Laravel Cloud doesn't support static IP addresses.

**AWS Lightsail is actually BETTER for your use case:**
- ✅ 87% cheaper (₹400 vs ₹3,000/month)
- ✅ Static IP included and guaranteed
- ✅ LAMP stack (PHP 8.2 + MySQL + Apache)
- ✅ Manual Composer + Redis setup (10 extra minutes)
- ✅ FREE first 3 months
- ✅ Perfect for 1-trade-per-day system
- ✅ Mumbai data center (low latency to NSE)

---

## 📋 Prerequisites

- AWS account (free to create)
- Your Laravel application on GitHub (or ready to clone)
- Fyers API credentials (new post-April 1 format)
- Claude API key

---

## 🚀 Step-by-Step Deployment

### STEP 1: Create AWS Account (5 minutes)

```bash
1. Go to https://aws.amazon.com/
2. Click "Create an AWS Account"
3. Enter email, password, account name
4. Add payment method (required, but won't be charged during free tier)
5. Verify phone number
6. Choose "Basic Support - Free"
7. Complete!
```

---

### STEP 2: Create Lightsail Instance (10 minutes)

```bash
1. Go to https://lightsail.aws.amazon.com/

2. Click "Create instance"

3. Choose instance location:
   ✅ Region: Asia Pacific (Mumbai) - ap-south-1
   ✅ Availability Zone: ap-south-1a (default is fine)

4. Select platform:
   ✅ Linux/Unix

5. Select a blueprint:
   ✅ Click "Apps + OS" tab
   ✅ Select "LAMP (PHP 8)" - AWS managed LAMP stack
   ✅ This includes: Ubuntu + Apache + MySQL + PHP 8.x
   ⚠️ We'll install Composer + Redis manually (takes 10 extra minutes)
   📁 Web root will be: /var/www/html (not Bitnami paths)

6. Enable Automatic Snapshots:
   ✅ Turn this ON (₹40/month, worth it for backups)

7. Choose your instance plan:
   ✅ Select "$3.50 USD/month" plan
   ✅ 512 MB RAM, 1 vCPU, 20 GB SSD
   ✅ This is PLENTY for 1 trade/day system

8. Name your instance:
   ✅ "banknifty-trading"

9. Click "Create instance"

10. Wait 2-3 minutes for instance to start
    ✅ Status changes to "Running"
```

---

### STEP 3: Get Static IP (5 minutes)

```bash
1. Click on your "banknifty-trading" instance

2. Go to "Networking" tab

3. Click "Create static IP"

4. Configure:
   ✅ Attach to: banknifty-trading
   ✅ Static IP name: banknifty-trading-ip

5. Click "Create"

6. 🎉 Your static IP is assigned!
   Example: 13.232.45.67
   
7. ⚠️ IMPORTANT: Copy this IP address
   You'll need it for:
   - Fyers API Dashboard whitelisting
   - Your .env file (STATIC_IP_ADDRESS)
```

---

### STEP 4: Configure Firewall (2 minutes)

```bash
1. Still in "Networking" tab

2. Under "IPv4 Firewall" section

3. Ensure these ports are open:
   ✅ SSH (22) - Already open
   ✅ HTTP (80) - Already open
   ✅ HTTPS (443) - Already open

4. Optional: Add custom rules
   - MySQL (3306) - Only if you need external DB access
   - Redis (6379) - Keep closed (internal only)

5. Click "Save"
```

---

### STEP 5: SSH Access (3 minutes)

```bash
# Method 1: Browser-based SSH (Easiest)
1. Click your instance name
2. Click "Connect using SSH" button
3. Browser terminal opens - you're in! ✅

# Method 2: SSH from your computer (Optional)
1. Download SSH key from Lightsail console
2. Open Terminal on your Mac
3. Run:
   chmod 400 ~/Downloads/LightsailDefaultKey-ap-south-1.pem
   ssh -i ~/Downloads/LightsailDefaultKey-ap-south-1.pem bitnami@13.232.45.67
```

---

### STEP 6: Install Required Software (10 minutes)

```bash
# You're now SSH'd into your Lightsail instance

# 1. Update package lists
sudo apt update

# 2. Install Composer
cd ~
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Verify Composer
composer --version
# Should show: Composer version 2.x.x ✅

# 3. Install Redis
sudo apt install -y redis-server

# Start Redis and enable on boot
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Verify Redis
redis-cli ping
# Should return: PONG ✅

# 4. Install Git (if not already installed)
sudo apt install -y git

# 5. Install PHP extensions needed by Laravel
# Note: Your system has PHP 8.5, so use php8.5-* packages
sudo apt install -y php8.5-cli php8.5-mbstring php8.5-xml php8.5-dom \
  php8.5-intl php8.5-bcmath php8.5-curl php8.5-zip php8.5-redis php8.5-mysql

# 6. Restart Apache to load new extensions
sudo systemctl restart apache2

# 7. Verify critical extensions are loaded
php -m | grep -E 'intl|dom|mbstring|curl|mysql'
# Should show all 5 extensions ✅
```

---

### STEP 7: Deploy Your Application (15 minutes)

```bash
# 1. Navigate to web root (AWS LAMP uses /var/www/html)
cd /var/www/html

# 2. Clone your repository
sudo git clone https://github.com/itzmevishu/my-trades.git
cd my-trades

# 3. Set ownership (www-data user runs Apache on Ubuntu)
sudo chown -R www-data:www-data /var/www/html/my-trades

# 4. Install dependencies
composer install --optimize-autoloader --no-dev

# 5. Set up environment file
cp .env.example .env
nano .env
```

**Edit .env file (paste these values):**

```env
APP_NAME="BankNifty AI Trading"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkataapplication_password
# Copy the MySQL root password and add to .env

# Re-edit .env
nano .env
# Add MySQL password to DB_PASSWORD
# Save: Ctrl+X, Y, Enter

# 7. Generate application key
php artisan key:generate

# 9. Create database (MariaDB - MySQL compatible)
mysql -u root -p
# If no password set, just press Enter, or use: sudo mysql
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Fyers API (your new credentials)
FYERS_NEW_APP_ID=
FYERS_CLIENT_ID=
FYERS_SECRET_KEY=

# Claude API
CLAUDE_API_KEY=

# SEBI Compliance
SEBI_COMPLIANT_MODE=true
```

**Save and exit:** `Ctrl+X`, then `Y`, then `Enter`

```bash
# 6. For AWS LAMP, MySQL root password is in:
#    Option 1: Check SSH welcome message when you first logged in
#    Option 2: Set your own password (recommended):
sudo mysql
# In MySQL prompt:
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YourStrongPassword123!';
FLUSH PRIVILEGES;
EXIT;

# 7. Update .env with your MySQL password
nano .env
# Set: DB_PASSWORD=YourStrongPassword123!
# Save: Ctrl+X, Y, Enter

# 9. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 10. Create database (MariaDB - MySQL compatible)
sudo mysql
# Or if password is set: mysql -u root -p
```

**In MySQL prompt:**

```sql
CREATE DATABASE my_trades CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

```bash
# 11. Run migrations
php artisan migrate --force

# 12. Seed settings
php artisan db:seed --class=SettingsSeeder

# 14. Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 15. Test the app
php artisan tinker
>>> \DB::connection()->getPdo();
# Should show PDO object - database connected! ✅
>>> exit
```

---

### STEP 8: Configure Web Server (10 minutes)

```bash
# 1. Edit Apache default site configuration
sudo nano /etc/apache2/sites-available/000-default.conf

# 2. Find DocumentRoot line and change it:
# FROM: DocumentRoot /var/www/html
# TO:   DocumentRoot /var/www/html/my-trades/public

# 3. Add Directory block after DocumentRoot (if not present):
<Directory /var/www/html/my-trades/public>
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# 4. Save: Ctrl+X, Y, Enter

# 5. Enable mod_rewrite (required for Laravel)
sudo a2enmod rewrite

# 6. Restart Apache
sudo systemctl restart apache2

# 5. Test in browser
# Go to: http://YOUR_STATIC_IP
# You should see your Laravel welcome page! 🎉
```

---

### STEP 9: Set Up Laravel Scheduler (5 minutes)

```bash
# 1. Edit crontab for www-data user
sudo crontab -u www-data -e

# 2. Add this line at the bottom:
* * * * * cd /var/www/html/my-trades && php artisan schedule:run >> /dev/null 2>&1

# 3. Save: Ctrl+X, Y, Enter

# 4. Verify cron is working
crontab -l
# Should show your cron entry ✅
```

---

### STEP 10: Set Up Queue Workers (5 minutes)

```bash
# 1. Install supervisor
sudo apt install -y supervisor

# 2. Create worker configuration
sudo nano /etc/supervisor/conf.d/laravel-worker.conf

# 3. Paste this:
```

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/my-trades/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/my-trades/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# 4. Save: Ctrl+X, Y, Enter

# 5. Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*

# 6. Check status
sudo supervisorctl status
# Should show: laravel-worker:laravel-worker_00  RUNNING ✅
#              laravel-worker:laravel-worker_01  RUNNING ✅
```

---

### STEP 10: Whitelist IP in Fyers Dashboard (5 minutes)
1
```bash
1. Login to https://fyers.in/web/api-dashboard

2. Go to "API Apps" section

3. Click on your App (or create new post-April 1 App)

4. Find "Whitelisted IPs" section

5. Add your Lightsail static IP:
   13.232.45.67 (your actual IP)

6. Save changes

7. ✅ Your system can now place orders from this IP!
```

---

## 🔒 Security Hardening (Optional but Recommended)

```bash
# 1. Set up firewall
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable

# 2. Disable root SSH login
sudo nano /etc/ssh/sshd_config
# Change: PermitRootLogin no
sudo systemctl restart ssh

# 3. Set up automatic security updates
sudo apt-get install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
# Select "Yes"

# 4. Change MySQL root password
mysql -u root -p
ALTER USER 'root'@'localhost' IDENTIFIED BY 'your_new_strong_password';
FLUSH PRIVILEGES;
EXIT;

# Update .env with new password
nano /opt/bitnami/projects/my-trades/.env
# Update DB_PASSWORD
php artisan config:cache
```

---

## 🔄 Updating Your Application

```bash
# SSH into your instance
cd /var/www/html/my-trades

# Pull latest changes
git pull origin main

# Update dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and rebuild cache
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
sudo supervisorctl restart laravel-worker:*

# Restart Apache
sudo systemctl restart apache2
# Done! ✅
```

---

## 📊 Monitoring & Logs

```bash
# Laravel logs
tail -f /var/www/html/my-trades/storage/logs/laravel.log

# Worker logs
tail -f /var/www/html/my-trades/storage/logs/worker.log

# Apache error logs
sudo tail -f /var/log/apache2/error.log

# Apache access logs
sudo tail -f /var/log/apache2/access.
# Check queue workers
sudo supervisorctl status

# Restart workers if needed
sudo supervisorctl restart laravel-worker:*

# Check Redis
redis-cli ping
# Should return: PONG ✅

# Check MySQL
mysql -u root -p -e "SELECT 1"
# Should return: 1 ✅
```

---

## 💾 Backups

```bash
# Automatic snapshots (you enabled this in Step 2)
# Lightsail takes daily snapshots automatically

# Manual snapshot
1. Go to Lightsail console
2. Click your instance
3. Go to "Snapshots" tab
4. Click "Create snapshot"
5. Name it: "pre-trading-launch-2026-06-23"
6. Done! ✅

# Restore from snapshot
1. Go to "Snapshots" tab
2. Click snapshot
3. Click "Create new instance"
4. Follow prompts
```

---

## ⚡ Performance Optimization

```bash
# 1. Enable OPcache (PHP performance)
sudo nano /opt/bitnami/php/etc/php.ini

# Find [opcache] section and set:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60

# Restart Apache
sudo /opt/bitnami/ctlscript.sh restart apache

# 2. Optimize Redis
redis-cli CONFIG SET maxmemory 256mb
redis-cli CONFIG SET maxmemory-policy allkeys-lru
redis-cli CONFIG REWRITE

# 3. MySQL optimization (already good for small dataset)
# Your 1-trade-per-day system won't need tuning
```

---

## 🆘 Troubleshooting

### Issue: Can't connect to instance
```bash
# Check instance is running
# Lightsail Console → Instance should show "Running"

# Check firewall rules
# Networking tab → Port 22 should be open

# Try browser-based SSH
# Click "Connect using SSH" button
```

### Issue: 500 Error on website
```bashapache2/htdocs/my-trades/storage/logs/laravel.log

# Check Apache logs
sudo tail -f /var/log/apache2/error.log

# Check permissions
sudo chown -R bitnami:daemon /opt/bitnami/apache2/htdocs/my-trades/storage
sudo chmod -R 775 /opt/bitnami/apache2/htdoc
sudo chown -R bitnami:daemon /opt/bitnami/projects/my-trades/storage
sudo chmod -R 775 /opt/bitnami/projects/my-trades/storage

# Clear cache
php artisan config:clear
php artisan cache:clear
```

### Issue: Queue workers not processing
```bash
# Check worker status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Check Redis connection
redis-cli pingapache2/htdocs/my-trades/storage/logs/worker.log
```

### Issue: Database connection failed
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test MySQL connection
mysql -u root -p

# Check .env database settings
cat /var/www/html/my-trades/.env | grep DB_

# Recreate config cache
php artisan config:clear
php artisan config:cache
```

---

## 💰 Cost Breakdown

| Item | Cost | Notes |
|------|------|-------|
| Instance ($3.50/month) | ₹280/month | FREE first 3 months |
| Static IP | FREE | Included |
| Automatic Snapshots | ₹40/month | Daily backups |
| Data Transfer | FREE | 1 TB/month included |
| **Total** | **₹320-400/month** | **First 3 months FREE** |

**Annual cost:** ~₹4,000/year (vs ₹36,000/year for Laravel Cloud)  
**Savings:** ₹32,000/year! 🎉

---

## ✅ Final Checklist

Before going live:
- [ ] Instance running in Mumbai region
- [ ] Static IP attached and noted
- [ ] Laravel app deployed successfully
- [ ] Database created and migrated
- [ ] Settings seeded
- [ ] Web server pointing to /public
- [ ] Cron scheduler running
- [ ] Queue workers running (2 processes)
- [ ] Static IP whitelisted in Fyers
- [ ] API credentials in .env
- [ ] Tested: `curl http://YOUR_IP` returns Laravel page
- [ ] Tested: Database connection working
- [ ] Tested: Redis connection working
- [ ] Automatic snapshots enabled
- [ ] Logs accessible and clean

---

## 🎯 Next Steps

1. ✅ Instance is live with static IP
2. → Update SEBI compliance settings in database
3. → Implement 2FA daily auth flow
4. → Integrate rate limiter with orders
5. → Test end-to-end by March 31
6. → Start Phase 1: Data Pipeline development

---

## 📞 Need Help?

**AWS Lightsail Support:**
- Console: https://lightsail.aws.amazon.com/
- Docs: https://lightsail.aws.amazon.com/ls/docs
- Forum: https://forums.aws.amazon.com/forum.jspa?forumID=231

**Laravel on Lightsail:**
- Bitnami Laravel Stack: https://docs.bitnami.com/aws/apps/laravel/
Apache
sudo systemctl restart apache2

# Restart MySQL
sudo systemctl restart mysql

# Restart Redis
sudo systemctl restart redis-server

# Check all services status
sudo systemctl status apache2
sudo systemctl status mysql
sudo systemctl status redis-server

# View Laravel logs
tail -f /opt/bitnami/apache2/htdocs/my-trades/storage/logs/laravel.log

# Update app (one-line command)
cd /var/www/html/my-trades && git pull && composer install && php artisan migrate --force && php artisan config:cache && sudo systemctl restart apache2
```

---

**You're all set! Your system is now hosted on a static IP for ₹400/month. 🚀**

*Last updated: 2026-06-23*
