<?php

namespace Rosandi\WAHA\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class WahaService
{
    protected $host = null;
    protected $apikey = null;
    protected $user = null;
    protected $password = null;
    protected $session = null;
    protected $to = null;
    protected $message = null;
    protected $replyTo = null;
    protected $sortBy = 'id';
    protected $sortOrder = 'desc';
    protected $limit = 10;
    protected $offset = 0;
    protected $filterFromMe = null;
    protected $filterToTime = null;
    protected $filterFromTime = null;

    public function host($host = null)
    {
        if ($host) {
            $this->host = $host;
        }
        return $this;
    }

    public function apikey($apikey = null)
    {
        if ($apikey) {
            $this->apikey = $apikey;
        }
        return $this;
    }

    public function user($user = null)
    {
        if ($user) {
            $this->user = $user;
        }
        return $this;
    }

    public function password($password = null)
    {
        if ($password) {
            $this->password = $password;
        }
        return $this;
    }

    public function session($session = null)
    {
        if ($session) {
            $this->session = $session;
        }
        return $this;
    }

    public function to($to = null)
    {
        if ($to) {
            $this->to = $to;
        }
        return $this;
    }

    public function message($message = null)
    {
        if ($message) {
            $this->message = $message;
        }
        return $this;
    }
    public function replyTo($replyTo = null)
    {
        if ($replyTo) {
            $this->replyTo = $replyTo;
        }
        return $this;
    }
    public function sortBy($sortBy = 'id')
    {
        if (in_array($sortBy, ['id', 'name', 'conversationTimestamp'])) {
            $this->sortBy = $sortBy;
        }
        return $this;
    }

    public function sortOrder($sortOrder = 'desc')
    {
        if (in_array($sortOrder, ['asc', 'desc'])) {
            $this->sortOrder = $sortOrder;
        }
        return $this;
    }

    public function limit($limit = 10)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    public function offset($offset = 0)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    public function filterFromTime($fromTime = null)
{
    if ($fromTime) {
        // Konversi ke timestamp jika input berupa tanggal atau datetime
        $this->filterFromTime = is_numeric($fromTime) ? $fromTime : strtotime($fromTime);
    }
    return $this;
}

public function filterToTime($toTime = null)
{
    if ($toTime) {
        // Konversi ke timestamp jika input berupa tanggal atau datetime
        $this->filterToTime = is_numeric($toTime) ? $toTime : strtotime($toTime);
    }
    return $this;
}



    // public function getConfig()
    // {
    //     return [
    //         'host' => $this->host ?? config('waha.API_HOST'),
    //         'apikey' => $this->apikey ?? config('waha.API_KEY'),
    //         'user' => $this->user ?? config('waha.BASIC_AUTH_USER'),
    //         'password' => $this->password ?? config('waha.BASIC_AUTH_PASSWORD'),
            
    //     ];
    // }
    public function getConfig()
    {
        return [
            'host' => $this->host ?? config('waha.API_HOST'),
            'apikey' => $this->apikey ?? config('waha.API_KEY'),
            'user' => $this->user ?? config('waha.BASIC_AUTH_USER'),
            'password' => $this->password ?? config('waha.BASIC_AUTH_PASSWORD'),
            
            // Mengembalikan konfigurasi rate limit
            'rate_limit' => [
                'max_attempts' => config('waha.RATE_LIMIT.MAX_ATTEMPTS'),
                'decay_minutes' => config('waha.RATE_LIMIT.DECAY_MINUTES'),
                'max_wait_time' => config('waha.RATE_LIMIT.MAX_WAIT_TIME'),
            ],
        ];
    }

    public function sessions()
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];

        // Mengambil data session dari WAHA API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->get("{$apiHost}/api/sessions");

        if ($response->successful()) {
            return $response->json(); // Mengembalikan data session
        }

        return ['error' => 'Failed to fetch sessions', 'message' => $response->body()];
    }

    public function send()
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];
        $message = $this->message;
        $to = $this->to;
        $replyTo = $this->replyTo;
        
        // Menggunakan session yang di-set atau mendapatkan sesi aktif
        $session = $this->session ?? $this->getActiveSession();
        
        if (!$session) {
            return ['error' => 'No active session found'];
        }

        // Data yang akan dikirim dalam request body
        $body = [
            'chatId' => $to,
            'reply_to' => $replyTo,
            'text' => $message,
            'linkPreview' => true,
            'session' => $session,
        ];

        // Mengirimkan request ke API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post("{$apiHost}/api/sendText", $body);  // Mengirimkan body sebagai JSON

        if ($response->successful()) {
            return $response->json();  // Mengembalikan response dalam format JSON
        }

        return ['error' => 'Failed to send message', 'message' => $response->body()];
    }
    public function humanSend()
    {
        // Ambil konfigurasi rate limit menggunakan getConfig()
        $config = $this->getConfig();
        $maxAttempts = $config['rate_limit']['max_attempts'];
        $decayMinutes = $config['rate_limit']['decay_minutes'];
        $maxWaitTime = $config['rate_limit']['max_wait_time'];

        // Pastikan set_time_limit hanya diubah jika maxWaitTime lebih kecil dari max_execution_time PHP
        if ($maxWaitTime > ini_get('max_execution_time')) {
            set_time_limit($maxWaitTime);
        }

        // Gunakan session untuk identifier unik
        $session = $this->session ?? $this->getActiveSession();
        $key = "human_send_{$session}";  // Gunakan session sebagai key unik

        // Tentukan batas waktu total untuk percobaan
        $startTime = time();

        // Loop untuk mencoba mengirim pesan jika rate limit belum tersedia
        while (RateLimiter::remaining($key, $maxAttempts) <= 0) {
            $elapsedTime = time() - $startTime;
            if ($elapsedTime > $maxWaitTime) {
                return ['error' => 'Maximum wait time exceeded. Please try again later.'];
            }

            // Jika sudah mencapai limit, beri delay tambahan
            $retryAfter = RateLimiter::availableIn($key);
            sleep($retryAfter + 20);  // Tambahkan 20 detik setelah delay yang dihitung
        }

        // Rate limiter: Mengurangi jumlah sisa attempts dan mencatat timestamp
        RateLimiter::hit($key, $decayMinutes * 60);  // Reset limit setiap menit

        // Eksekusi startTyping
        $this->startTyping();

        // Delay antara 0.5 hingga 5 detik
        $delay = rand(5, 50) / 10;  // Menghasilkan delay antara 0.5 hingga 5 detik
        sleep($delay);

        // Eksekusi stopTyping
        $this->stopTyping();

        // Kirim pesan
        return $this->send();
    }






    public function startTyping()
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];
        $to = $this->to;
        
        // Menggunakan session yang di-set atau mendapatkan sesi aktif
        $session = $this->session ?? $this->getActiveSession();
        
        if (!$session) {
            return ['error' => 'No active session found'];
        }

        // Data yang akan dikirim dalam request body
        $body = [
            'chatId' => $to,
            'session' => $session,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post("{$apiHost}/api/startTyping", $body);

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to start typing', 'message' => $response->body()];
    }

    public function stopTyping()
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];
        $to = $this->to;
        
        // Menggunakan session yang di-set atau mendapatkan sesi aktif
        $session = $this->session ?? $this->getActiveSession();
        
        if (!$session) {
            return ['error' => 'No active session found'];
        }

        // Data yang akan dikirim dalam request body
        $body = [
            'chatId' => $to,
            'session' => $session,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post("{$apiHost}/api/stopTyping", $body);

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to stop typing', 'message' => $response->body()];
    }

    public function getMessages()
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];

        // Menggunakan session yang di-set atau mendapatkan sesi aktif
        $session = $this->session ?? $this->getActiveSession();

        if (!$session) {
            return ['error' => 'No active session found'];
        }

        // Menyusun URL dengan session yang sudah di-set
        $url = "{$apiHost}/api/{$session}/chats?sortBy={$this->sortBy}&sortOrder={$this->sortOrder}&limit={$this->limit}&offset={$this->offset}";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => 'Failed to fetch messages', 'message' => $response->body()];
    }

    public function getMessageByChatId($chatId)
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];

        // Menggunakan session yang di-set atau mendapatkan sesi aktif
        $session = $this->session ?? $this->getActiveSession();

        if (!$session) {
            return ['error' => 'No active session found'];
        }

        // Menyusun URL dengan session yang sudah di-set dan chatId yang diberikan
        $url = "{$apiHost}/api/{$session}/chats/{$chatId}/messages";

        // Menyusun query parameters
        $params = [
            'downloadMedia' => true,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'filter.timestamp.lte' => $this->filterToTime,
            'filter.timestamp.gte' => $this->filterFromTime,
            'filter.fromMe' => $this->filterFromMe
        ];

        // Mengirimkan request GET ke API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'accept' => '*/*',
        ])->get($url, $params);

        if ($response->successful()) {
            return $response->json(); // Mengembalikan response dalam format JSON
        }

        return ['error' => 'Failed to fetch messages', 'message' => $response->body()];
    }

    public function getActiveSession()
    {
        // Ambil daftar sesi yang tersedia
        $sessions = $this->sessions();

        // Jika sesi ditemukan dan tidak kosong
        if (isset($sessions[0])) {
            // Filter untuk mencari session dengan status "WORKING"
            $activeSession = collect($sessions)->first(function ($session) {
                return $session['status'] === 'WORKING';
            });

            // Utamakan session dengan nama "default"
            if ($activeSession && $activeSession['name'] === 'default') {
                return $activeSession['name'];  // Mengembalikan hanya nama session
            }

            // Jika tidak ditemukan session "default", kembalikan session aktif pertama yang ditemukan
            if ($activeSession) {
                return $activeSession['name'];  // Mengembalikan nama session aktif lainnya
            }
        }

        // Jika tidak ada session aktif ditemukan, kembalikan null atau false
        return null;
    }

    public function getParticipants($groupId)
    {
        $config = $this->getConfig(); // Ambil konfigurasi dari chaining
        $apiHost = $config['host'];
        $apiKey = $config['apikey'];

        // Menggunakan session yang di-set atau mendapatkan sesi aktif
        $session = $this->session ?? $this->getActiveSession();

        if (!$session) {
            return ['error' => 'No active session found'];
        }

        // Menyusun URL untuk mengambil peserta grup
        $url = "{$apiHost}/api/{$session}/groups/{$groupId}/participants";

        // Mengirimkan request GET ke API untuk mendapatkan peserta
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'accept' => '*/*',
        ])->get($url);

        // Cek apakah response sukses
        if ($response->successful()) {
            return $response->json(); // Mengembalikan response dalam format JSON
        }

        // Jika gagal, kembalikan pesan error
        return ['error' => 'Failed to fetch participants', 'message' => $response->body()];
    }



}
