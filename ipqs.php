<?php
/**
 * IPQualityScore API Integration
 * Analyzes customer IP addresses for fraud detection
 * Supports dual API key failover
 */

class IPQS {
    private $apiKeys = [];
    private $baseUrl = 'https://www.ipqualityscore.com/api/json/ip/';

    public function __construct(array $apiKeys) {
        $this->apiKeys = array_filter($apiKeys);
    }

    /**
     * Analyze an IP address for fraud indicators
     * Tries each API key with failover
     */
    public function analyzeIP($ipAddress, $options = []) {
        if ($this->isPrivateIP($ipAddress)) {
            return [
                'success' => true,
                'country_code' => 'LOCAL',
                'city' => 'Local Network',
                'region' => 'N/A',
                'proxy' => false,
                'vpn' => false,
                'tor' => false,
                'fraud_score' => 0,
                'bot_status' => false,
                'recent_abuse' => false,
                'is_local' => true
            ];
        }

        foreach ($this->apiKeys as $key) {
            $result = $this->callAPI($key, $ipAddress, $options);
            if ($result !== null) return $result;
        }

        return null;
    }

    private function callAPI($apiKey, $ipAddress, $options = []) {
        $url = $this->baseUrl . $apiKey . '/' . urlencode($ipAddress);

        $params = ['strictness' => $options['strictness'] ?? 1];
        if (!empty($options['user_agent'])) {
            $params['user_agent'] = $options['user_agent'];
        }
        $url .= '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            error_log("IPQS API Error: $error (HTTP $httpCode) for IP: $ipAddress");
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['success']) || !$data['success']) {
            error_log("IPQS API: Invalid response for IP: $ipAddress");
            return null;
        }

        return $data;
    }

    /**
     * Extract storage-ready data from API response
     */
    public function getStorageData($data) {
        if (!$data) {
            return ['fraud_score' => null, 'fraud_risk_level' => null, 'fraud_flags' => null];
        }

        $score = (int)($data['fraud_score'] ?? 0);
        $riskLevel = self::getRiskLevel($score);

        $flags = [];
        if (!empty($data['vpn'])) $flags[] = 'vpn';
        if (!empty($data['proxy'])) $flags[] = 'proxy';
        if (!empty($data['tor'])) $flags[] = 'tor';
        if (!empty($data['bot_status'])) $flags[] = 'bot';
        if (!empty($data['recent_abuse'])) $flags[] = 'recent_abuse';

        return [
            'fraud_score' => $score,
            'fraud_risk_level' => $riskLevel,
            'fraud_flags' => !empty($flags) ? implode(',', $flags) : null
        ];
    }

    /**
     * Get fraud risk level based on score
     */
    public static function getRiskLevel($score) {
        if ($score >= 90) return 'high';
        if ($score >= 85) return 'risky';
        if ($score >= 75) return 'suspicious';
        return 'low';
    }

    private function isPrivateIP($ip) {
        if ($ip === '::1') return true;

        $privateRanges = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8'];
        $ipLong = ip2long($ip);
        if ($ipLong === false) return false;

        foreach ($privateRanges as $range) {
            list($subnet, $mask) = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $maskLong = ~((1 << (32 - $mask)) - 1);
            if (($ipLong & $maskLong) === ($subnetLong & $maskLong)) return true;
        }

        return false;
    }
}
