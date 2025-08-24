<?php
class Environment
{
    public static function getDbConfig()
    {
        // Use environment variables in production, fallback to defaults for development
        return [
            'host' => getenv('DB_HOST') ?: 'bazario-db-bazario.d.aivencloud.com',
            'port' => getenv('DB_PORT') ?: '21585',
            'dbname' => getenv('DB_NAME') ?: 'bazario',
            'username' => getenv('DB_USERNAME') ?: 'avnadmin',
            'password' => getenv('DB_PASSWORD') ?: 'AVNS_ngCjxOMMe8j7QR3Nd7w'
        ];
    }

    public static function isProduction()
    {
        return getenv('APP_ENV') === 'production';
    }
}
