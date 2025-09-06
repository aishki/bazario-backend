<?php
class Environment
{
    public static function getDbConfig()
    {
        return [
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
            'dbname' => getenv('DB_NAME'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASSWORD'),
            'sslmode' => getenv('DB_SSLMODE')
        ];
    }
}
