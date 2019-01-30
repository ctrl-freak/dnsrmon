# dnsrmon
DNS Record Monitor

Monitors changes in DNS records on DNS servers and sends an email when anything is different.

# Requirements

* PHP
* MySQL
* Port 53 open outbound

# Installation

1. `cd /var/www`
1. `git clone https://github.com/ctrl-freak/dnsrmon.git`
2. Import `dnsrmon.sql` to create database and tables
3. Create MySQL user with full access to database
2. `cd dnsrmon`
2. `cp example.config.php config.php`
3. Edit `config.php` with SQL and SMTP details
2. `cd lib/`
3. `git clone https://github.com/purplepixie/phpdns`
4. `git clone https://github.com/PHPMailer/PHPMailer`
5. Set up a cron job:<br />
`crontab -e`<br />
`*/5 *  * * * wget -qO- http://localhost/dnsrmon/?cron --delete-after &> /dev/null`
6. Add a server in the `dnsservers` table:<br />
`INSERT INTO 'dnsservers' ('address', 'label', 'enabled') VALUES ('8.8.8.8', 'Google', 1);`
7. Add DNS records to `domains` table
