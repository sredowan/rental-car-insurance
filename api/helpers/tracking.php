<?php
// ============================================================
// Rental Sheild — Tracking & Analytics Helper
// Handles Facebook Conversion API (CAPI) events
// ============================================================

require_once __DIR__ . '/../config/config.php';

class Tracking {
    /**
     * Send an event via Facebook Conversions API
     *
     * @param string $eventName   e.g., 'Purchase', 'Lead'
     * @param array  $eventData   Assoc array (email, phone, ip, user_agent, value, currency, order_id)
     * @return bool True if successfully sent/queued
     */
    public static function sendFacebookEvent(string $eventName, array $eventData = []): bool {
        // Only run if configured
        if (!defined('FB_PIXEL_ID') || !defined('FB_CAPI_TOKEN') || empty(FB_PIXEL_ID) || empty(FB_CAPI_TOKEN)) {
            return false;
        }

        $url = "https://graph.facebook.com/v19.0/" . FB_PIXEL_ID . "/events";

        $userData = [
            'client_ip_address' => $eventData['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
            'client_user_agent' => $eventData['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // Hash PII data per FB requirements (SHA256)
        if (!empty($eventData['email'])) {
            $userData['em'] = hash('sha256', strtolower(trim($eventData['email'])));
        }
        if (!empty($eventData['phone'])) {
            // Strip non-numeric for normalized phone hashing
            $ph = preg_replace('/[^0-9]/', '', $eventData['phone']);
            $userData['ph'] = hash('sha256', $ph);
        }

        // Custom data (e.g. value, currency)
        $customData = [];
        if (isset($eventData['value'])) {
            $customData['value'] = (float) $eventData['value'];
        }
        if (isset($eventData['currency'])) {
            $customData['currency'] = $eventData['currency'];
        }
        if (isset($eventData['order_id'])) {
            $customData['order_id'] = $eventData['order_id'];
        }
        
        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'action_source' => 'website',
            'user_data' => $userData,
        ];

        if (!empty($customData)) {
            $event['custom_data'] = $customData;
        }

        $payload = [
            'data' => [$event],
            'access_token' => FB_CAPI_TOKEN
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Very short timeout, so we don't stall user requests just for tracking
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Facebook CAPI Error ($eventName): " . $response);
            return false;
        }

        return true;
    }
}
