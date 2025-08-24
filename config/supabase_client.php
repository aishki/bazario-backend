<?php
class SupabaseClient
{
    private $supabase_url = "https://cwrjwdxzomcwmtnuklmi.supabase.co";
    private $supabase_anon_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImN3cmp3ZHh6b21jd210bnVrbG1pIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTU2NzUzNzcsImV4cCI6MjA3MTI1MTM3N30.I_A9vAGsFByfXzuRusgpF3G_WHKdMfbhU7wnc1AaaxI";
    private $supabase_service_key = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImN3cmp3ZHh6b21jd210bnVrbG1pIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1NTY3NTM3NywiZXhwIjoyMDcxMjUxMzc3fQ.qCpLOVuVQScCz5JjFF3NuNYoBfIpyZa7nEl9VAAx3NA";

    public function __construct($use_service_key = false)
    {
        if ($use_service_key) {
            $this->supabase_service_key = getenv('SUPABASE_SERVICE_KEY') ?: $this->supabase_service_key;
            if (empty($this->supabase_service_key)) {
                throw new Exception("Supabase service key is required for admin operations. Set SUPABASE_SERVICE_KEY environment variable.");
            }
        } else {
            $this->supabase_anon_key = getenv('SUPABASE_ANON_KEY') ?: $this->supabase_anon_key;
            if (empty($this->supabase_anon_key)) {
                throw new Exception("Supabase anon key is required. Set SUPABASE_ANON_KEY environment variable.");
            }
        }
    }

    private function getAuthKey($use_service_key = false)
    {
        return $use_service_key ? $this->supabase_service_key : $this->supabase_anon_key;
    }

    public function query($table, $select = "*", $filters = array(), $use_service_key = false)
    {
        $url = $this->supabase_url . "/rest/v1/" . $table . "?select=" . urlencode($select);

        foreach ($filters as $key => $value) {
            $url .= "&" . urlencode($key) . "=" . urlencode($value);
        }

        $api_key = $this->getAuthKey($use_service_key);
        $headers = array(
            "apikey: " . $api_key,
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        } else {
            throw new Exception("Supabase query failed: " . $response);
        }
    }

    public function insert($table, $data, $use_service_key = false)
    {
        $url = $this->supabase_url . "/rest/v1/" . $table;

        $api_key = $this->getAuthKey($use_service_key);
        $headers = array(
            "apikey: " . $api_key,
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json",
            "Prefer: return=representation"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 201) {
            return json_decode($response, true);
        } else {
            throw new Exception("Supabase insert failed: " . $response);
        }
    }
}
