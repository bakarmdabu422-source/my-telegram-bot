<?php

// ==========================================
// ১. টেলিগ্রাম থেকে আসা ইনপুট রিসিভ করা
// ==========================================
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

// ==========================================
// ২. কনফিগারেশন এবং ক্রেডেনশিয়ালস
// ==========================================
$admin_id = '8846439874';$bot_token = '8992643639:AAFrniRtPAS6gFzONPm6OIXfAj175hAcApw';

$banned_file = 'banned_users.txt';
$off_status_file = 'bot_off_time.json';$user_file = 'users.txt';

// ==========================================
// ৩. cURL এর মাধ্যমে টেলিগ্রাম এপিআই রিকোয়েস্ট ফাংশন
// ==========================================
function telegramApi($method,$data = []) {
    global $bot_token;
    $url = "https://api.telegram.org/bot" . $bot_token . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// ==========================================
// ৪. মেসেজ প্রসেসিং ($message)
// ==========================================
if (isset($update['message'])) {$message = $update['message'];$chat_id = $message['chat']['id'];$user_id = $message['from']['id'];$text = isset($message['text']) ?$message['text'] : '';

    // --- ইউজার ব্যান আছে কিনা চেক ---
    $banned_users = file_exists($banned_file) ? file($banned_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    if (in_array((string)$user_id,$banned_users)) {
        telegramApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🚫 **আপনাকে এই বট থেকে ব্যান করা হয়েছে!**",
            'parse_mode' => 'Markdown'
        ]);
        exit;
    }

    // --- বট অফ (Maintenance Mode) আছে কিনা চেক ---
    $is_off = false;
    $minutes_left = 0;
    if (file_exists($off_status_file)) {
        $off_data = json_decode(file_get_contents($off_status_file), true);
        if (isset($off_data['off_until']) && time() < $off_data['off_until']) {$is_off = true;
            $minutes_left = ceil(($off_data['off_until'] - time()) / 60);
        }
    }

    if ($is_off && (string)$user_id !== (string)$admin_id) {
        telegramApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🛠 **বট বর্তমানে সাময়িকভাবে বন্ধ আছে!**\n\n⏰ আবার চালু হতে সময় লাগবে: **{$minutes_left} মিনিট**।",
            'parse_mode' => 'Markdown'
        ]);
        exit;
    }

    // --- নতুন ইউজার ডাটাবেজে সেভ করা ---
    $users = file_exists($user_file) ? file($user_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    if (!in_array($chat_id,$users)) {
        file_put_contents($user_file,$chat_id . PHP_EOL, FILE_APPEND);
        $users[] =$chat_id;
    }

    // --- /start কমান্ড ---
    if ($text === '/start') {$reply_keyboard = [
            'keyboard' => [
                [['text' => '📞 Get Number'], ['text' => '✉️ tamp Mail']],
                [['text' => '👑 Admin Panel']]
            ],
            'resize_keyboard' => true
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "👋 **স্বাগতম!**\nআমাদের এই বটটি দিয়ে ওটিপি এনে আর্নিং করুন। নিচের মেনু থেকে অপশন বেছে নিন:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($reply_keyboard)
        ]);
    }

    // --- 📞 Get Number সার্ভিস ---
    elseif ($text === '📞 Get Number') {
        telegramApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📞 **Get Number Service**\n\nএখানে সার্ভিস সিলেক্ট করে নম্বর জেনারেট করতে পারবেন।",
            'parse_mode' => 'Markdown'
        ]);
    }

    // --- ✉️ Temp Mail সার্ভিস ---
    elseif ($text === '✉️ tamp Mail') {
        $mail_buttons = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Open Web', 'url' => 'https://temp-mail.org/'],
                    ['text' => '🤖 Open Telegram', 'url' => 'https://t.me/TempMail_org_bot']
                ]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✉️ **Temp Mail Service**\nঅস্থায়ী ইমেইল ব্যবহারের জন্য নিচের লিংকে ক্লিক করুন:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($mail_buttons)
        ]);
    }

    // --- 👑 Admin Panel ---
    elseif ($text === '👑 Admin Panel' && (string)$chat_id === (string)$admin_id) {
        $total_users = count($users);

        $admin_msg = "👑 **Welcome Admin Panel** 👑\n\n"
                   . "📊 **Total Registered Users:** `{$total_users}`\n\n"
                   . "💡 **অ্যাডমিন কমান্ডসমূহ:**\n"
                   . "📢 ব্রডকাস্ট পাঠাতে: `/broadcast মেসেজ`\n"
                   . "🚫 ইউজার ব্যান করতে: `/ban USER_ID`\n"
                   . "✅ ইউজার আনব্যান করতে: `/unban USER_ID`\n"
                   . "🛑 বট অফ করতে: `/off MINUTES`\n";

        $admin_buttons = [
            'inline_keyboard' => [
                [
                    ['text' => '🟢 বট অন করুন', 'callback_data' => 'admin_bot_on']
                ]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $admin_msg,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($admin_buttons)
        ]);
    }

    // --- /broadcast কমান্ড ---
    elseif (strpos($text, '/broadcast') === 0 && (string)$chat_id === (string)$admin_id) {
        $broadcast_msg = trim(str_replace('/broadcast', '',$text));
        if (!empty($broadcast_msg)) {$count = 0;
            foreach ($users as$u_id) {
                $u_id = trim($u_id);
                if (!empty($u_id)) {$res = telegramApi('sendMessage', [
                        'chat_id' => $u_id,
                        'text' => "📢 **নোটিশ:**\n\n" . $broadcast_msg,
                        'parse_mode' => 'Markdown'
                    ]);
                    if (isset($res['ok']) && $res['ok']) {$count++;
                    }
                }
            }
            telegramApi('sendMessage', [
                'chat_id' => $admin_id,
                'text' => "✅ মোট **{$count}** জন ইউজারকে মেসেজ পাঠানো হয়েছে!",
                'parse_mode' => 'Markdown'
            ]);
        }
    }

    // --- /ban কমান্ড ---
    elseif (strpos($text, '/ban') === 0 && (string)$chat_id === (string)$admin_id) {
        $target_id = trim(str_replace('/ban', '',$text));
        file_put_contents($banned_file,$target_id . PHP_EOL, FILE_APPEND);
        telegramApi('sendMessage', [
            'chat_id' => $admin_id,
            'text' => "✅ ইউজার `{$target_id}` কে ব্যান করা হয়েছে!",
            'parse_mode' => 'Markdown'
        ]);
    }

    // --- /unban কমান্ড ---
    elseif (strpos($text, '/unban') === 0 && (string)$chat_id === (string)$admin_id) {
        $target_id = trim(str_replace('/unban', '',$text));
        if (file_exists($banned_file)) {$b_users = file($banned_file, FILE_IGNORE_NEW_LINES \vert{} FILE_SKIP_EMPTY_LINES);$new_b_users = array_diff($b_users, [$target_id]);
            file_put_contents($banned_file, implode(PHP_EOL,$new_b_users) . PHP_EOL);
        }
        telegramApi('sendMessage', [
            'chat_id' => $admin_id,
            'text' => "✅ ইউজার `{$target_id}` কে আনব্যান করা হয়েছে!",
            'parse_mode' => 'Markdown'
        ]);
    }

    // --- /off কমান্ড ---
    elseif (strpos($text, '/off') === 0 && (string)$chat_id === (string)$admin_id) {
        $mins = (int)trim(str_replace('/off', '',$text));
        $off_until = time() + ($mins * 60);
        file_put_contents($off_status_file, json_encode(['off_until' =>$off_until]));
        telegramApi('sendMessage', [
            'chat_id' => $admin_id,
            'text' => "🛑 বট `{$mins}` মিনিটের জন্য বন্ধ করা হলো!",
            'parse_mode' => 'Markdown'
        ]);
    }
}

// ==========================================
// ৫. ইনলাইন বাটন কলব্যাক হ্যান্ডলার ($callback_query)
// ==========================================
if (isset($update['callback_query'])) {
    $callback_query =$update['callback_query'];
    $callback_id =$callback_query['id'];
    $data =$callback_query['data'];
    $user_id =$callback_query['from']['id'];

    if ($data === 'admin_bot_on' && (string)$user_id === (string)$admin_id) {
        if (file_exists($off_status_file)) {
            unlink($off_status_file);
        }
        telegramApi('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'text' => "🟢 বট সফলভাবে অন করা হয়েছে!",
            'show_alert' => true
        ]);
    } else {
        telegramApi('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'text' => "❌ এটি আপনার জন্য নয়!",
            'show_alert' => true
        ]);
    }
}

http_response_code(200);
?> 