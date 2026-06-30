# 📱 Telegram Trading Notifications Setup Guide

Get instant notifications on your phone for every trade event!

## 🎯 What You'll Get

- ✅ **Pattern Detected** - When a valid pattern is found
- ✅ **Trade Entry** - Instant alert when trade is executed
- ✅ **Trade Exit** - Know immediately when trade closes
- ✅ **Daily Summary** - End-of-day P&L and statistics

---

## 🤖 Step 1: Create Telegram Bot (2 minutes)

### 1.1 Open Telegram and Search for "BotFather"
- Open Telegram app on your phone or desktop
- Search for `@BotFather` (official bot creator)
- Start a chat

### 1.2 Create Your Bot
Send command:
```
/newbot
```

BotFather will ask for:

**Bot Name:** (What users see)
```
My Trading Bot
```

**Bot Username:** (Must end with 'bot')
```
my_banknifty_bot
```

### 1.3 Save Your Bot Token
BotFather will reply with:
```
Done! Your bot token is:
123456789:ABCdefGHIjklMNOpqrsTUVwxyz

Keep your token secure and store it safely!
```

**⚠️ IMPORTANT: Copy this token!** You'll need it in Step 3.

---

## 💬 Step 2: Get Your Chat ID (1 minute)

### 2.1 Start Chat with Your Bot
- Search for your bot username (e.g., `@my_banknifty_bot`)
- Click **START** button
- Send any message (e.g., "Hello")

### 2.2 Get Your Chat ID
Open this URL in your browser (replace YOUR_BOT_TOKEN):
```
https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates
```

Example:
```
https://api.telegram.org/bot123456789:ABCdefGHIjklMNOpqrsTUVwxyz/getUpdates
```

You'll see JSON response:
```json
{
  "ok": true,
  "result": [{
    "message": {
      "chat": {
        "id": 987654321,  ← This is your Chat ID!
        "first_name": "Your Name",
        "type": "private"
      }
    }
  }]
}
```

**Copy the `"id"` number** (e.g., `987654321`)

---

## ⚙️ Step 3: Configure in Admin Panel

### 3.1 Login to Admin Panel
```
http://your-server-ip/admin
```

### 3.2 Go to Settings
Navigate to: **Settings** (in sidebar)

### 3.3 Add These Settings

Click **New Setting** and add each of these:

#### Setting 1: Bot Token
```
Key:   telegram_bot_token
Value: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz
Type:  String
Group: Notifications
```

#### Setting 2: Chat ID
```
Key:   telegram_chat_id
Value: 987654321
Type:  String
Group: Notifications
```

#### Setting 3: Enable Notifications
```
Key:   telegram_notifications_enabled
Value: true
Type:  Boolean
Group: Notifications
```

#### Setting 4: Notify Rejections (Optional)
```
Key:   telegram_notify_rejections
Value: false
Type:  Boolean
Group: Notifications
Description: Send notifications when scans are rejected (can be noisy)
```

---

## ✅ Step 4: Test Notifications

### 4.1 Run Test Command
```bash
cd /var/www/html/my-trades
sudo -u www-data php artisan telegram:test
```

### 4.2 Expected Output
```
📱 Testing Telegram Notification...

✅ Test notification sent successfully!

Check your Telegram app to see the message.
```

### 4.3 Check Your Phone
You should receive a message like:
```
✅ Test Notification

Your BankNifty AI Trading Bot is connected!

You will receive notifications for:
• Pattern detected
• Trade entry
• Trade exit
• Daily summary

Time: Jun 30, 2026 10:30:00
```

---

## 📲 Example Notifications

### Trade Entry Notification
```
📈 TRADE ENTRY

✅ Trade #15 Executed

Pattern: Bullish Pinbar
Direction: bullish
Strike: 58200
Type: CALL

💰 Entry: ₹245.50
🎯 Target: ₹491.00
🛑 Stop Loss: ₹122.75

Quantity: 30 (2 lots)
Max Risk: ₹3,682.50
Expected Profit: ₹7,365.00

Claude Score: ⭐ 7.5/10
Time: 2026-06-30 10:45:02
```

### Trade Exit Notification
```
✅ TRADE EXIT - WIN

Trade #15

Pattern: Bullish Pinbar
Direction: bullish
Strike: 58200

💰 Entry: ₹245.50
🏁 Exit: ₹520.00
Exit Reason: target_hit

💵 Profit: +₹8,235.00
ROI: 2.75%

Entry: 10:45:02
Exit: 12:30:15
Duration: 01:45
```

---

## 🔧 Troubleshooting

### ❌ "Bot token not configured"
- Make sure you copied the FULL token from BotFather
- Include the colon and everything after it
- No spaces before/after

### ❌ "Chat ID not configured"
- Make sure you sent a message to your bot first
- The number should be all digits (e.g., 987654321)
- Not your username or bot username

### ❌ "Failed to send notification"
- Check bot token is correct
- Check chat ID is correct
- Make sure you clicked START in bot chat
- Try deleting and recreating the bot

### ❌ Not receiving notifications
- Check `telegram_notifications_enabled` is `true`
- Run `php artisan telegram:test` to verify
- Check Telegram app notifications are enabled
- Make sure bot isn't blocked

---

## 🎉 You're All Set!

Your trading bot will now send you instant notifications for:
- ✅ Every trade entry
- ✅ Every trade exit
- ✅ Win/loss status
- ✅ P&L amounts
- ✅ Daily summaries

**Important:** Keep your bot token secret! Don't share it or commit it to Git.

---

## 💡 Pro Tips

1. **Mute Group Chats:** Create a group just for trading notifications
2. **Custom Alerts:** Turn off rejection notifications if too noisy
3. **Forward to Team:** Add team members to a group with the bot
4. **Archive Old Chats:** Keep notifications but don't clutter main chat
5. **Pin Important:** Pin critical trades or summaries

Happy Trading! 🚀
