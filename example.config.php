<?

$config = [
    'timezone' => 'Australia/Perth', // http://php.net/manual/en/timezones.php
    'database' => [
        'host' => 'localhost',
        'port' => '3306',
        'user' => 'dnsrmon',
        'password' => 'mysqlpassword',
        'dbname' => 'dnsrmon',
        'charset' => 'utf8mb4'
    ],
    'alert' => [
        'recipients' => [
            ['email@domain.com', 'Your Name']
//            ,['email@domain.com', 'Additional recipient']
        ],
        'sender' => ['support@domain.com','DNS Record Monitoring']
    ],
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'support@domain.com',
        'password' => 'mailpassword'
    ]
];