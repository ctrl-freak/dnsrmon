<?

if (!isset($_GET['cron'])) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use PurplePixie\PhpDns\DNSAnswer;
use PurplePixie\PhpDns\DNSQuery;
use PurplePixie\PhpDns\DNSTypes;

require_once __DIR__ . '/lib/phpdns/dns.inc.php';

require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

if (file_exists(__DIR__.'/config.php')) {
    include __DIR__ . '/config.php';    
} else {
    die('<strong>Error:</strong> config.php does not exist in '.__DIR__.': Please copy example.config.php to config.php and modify.');
}

date_default_timezone_set($config['timezone']);

$dsn = 'mysql:host='.$config['database']['host'].';dbname='.$config['database']['dbname'].';charset='.$config['database']['charset'];

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
//    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $PDO = new PDO($dsn, $config['database']['user'], $config['database']['password'], $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$DNSServers = $PDO->Query('SELECT * FROM dnsservers WHERE enabled = 1')->fetchAll();

//$DNSServers = [];

foreach ($DNSServers as &$DNSServer) {
    $DNSServer['dnsquery'] = new DNSQuery($DNSServer['address']);
}

var_export($DNSServers); exit;

$DomainRecords = $PDO->Query('SELECT * FROM domains WHERE next_run < NOW() AND enabled = TRUE')->fetchAll();

$ChangeSQL = 'INSERT INTO changes SET event_datetime=NOW(), domain_id=:domain_id, dnsserver_id=:dnsserver_id, new_data=:new_data';
$ChangeStatement = $PDO->prepare($ChangeSQL);

$UpdateSQL = 'UPDATE domains SET data=:data, last_run=NOW(), next_run=:next_run WHERE id=:id';
$UpdateStatement = $PDO->prepare($UpdateSQL);

foreach ($DomainRecords as $DomainRecord) {
    $Changes = '';
    foreach ($DNSServers as $DNSServer) {
        $Result = $DNSServer['dnsquery']->Query($DomainRecord['domain'],$DomainRecord['record']); // do the query 
        
        // Trap Errors

        if ( ($Result===false) || ($DNSServer['dnsquery']->getLasterror() != 0) ) {  // error occured 
            echo $DNSServer['dnsquery']->getLasterror();
            exit();
        } else {
            $Data = [];
            foreach ($Result as $Record) {
                $Data[] = $Record->getData();
            }
            
            sort($Data);
            $Data = json_encode($Data);
            
            if ($Data != $DomainRecord['data']) {
//                mail(
//                    $config['alert']['to'],
//                    '[DNSRMon] Changed '.$DomainRecord['record'].' Record for '.$DomainRecord['domain'],
//                    $DomainRecord['data']."\n\n".$Data
//                );

                $ChangeStatement->Execute([
                    'domain_id' => $DomainRecord['id'],
                    'dnsserver_id' => $DNSServer['id'],
                    'new_data' => $Data
                ]);
                
                $Changes .= changeHTML($DomainRecord, $DNSServer, $DomainRecord['data'], $Data);
            }
            
            $Next_Run = date('Y-m-d H:i:s', strtotime($DomainRecord['run_interval']));

            $UpdateStatement->Execute([
                'data' => $Data,
                'next_run' => $Next_Run,
                'id' => $DomainRecord['id']
            ]);
        }
    }
    
    if ($Changes != '') {
        send($config, $config['alert']['sender'], $config['alert']['recipients'], '[DNSRMon] Changed '.$DomainRecord['record'].' Record for '.$DomainRecord['domain'], emailHTML($Changes));
    }
}



function send($config, $from, $tos, $subject, $body) {
    $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
    try {
        //Server settings
        $mail->SMTPDebug = 0;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = $config['smtp']['host'];  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = $config['smtp']['username'];                 // SMTP username
        $mail->Password = $config['smtp']['password'];                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = $config['smtp']['port'];                                    // TCP port to connect to
    
        //Recipients
        $mail->setFrom($from[0], $from[1]);
        foreach ($tos as $to) {
            $mail->addAddress($to[0], $to[1]);     // Add a recipient
        }

        //Attachments
    //    $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
    //    $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
    
        //Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
    
        $mail->send();
//        echo 'Message has been sent';
    } catch (Exception $e) {
        echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
    }
}





function changeHTML($DomainRecord, $DNSServer, $FromData, $ToData) { 
    ob_start();
?>
        <table class="dns-record-table">
            <tr>
                <th>Domain:</th>
                <td><?=$DomainRecord['domain']?></td>
            </tr>
            <tr>
                <th>Record:</th>
                <td><?=$DomainRecord['record']?></td>
            </tr>
            <tr>
                <th>DNS Server</th>
                <td><?=$DNSServer['label']?> (<?=$DNSServer['address']?>)</td>
            </tr>
            <tr>
                <th>Last Checked:</th>
                <td><?=$DomainRecord['last_run']?></td>
            </tr>
            <tr>
                <th>Old Data:</th>
                <td><?=$FromData?></td>
            </tr>
            <tr>
                <th>New Data:</th>
                <td><?=$ToData?></td>
            </tr>
        </table>

<?
    $return = ob_get_clean();
    return $return;
}


function emailHTML($Body) {
    ob_start();
?>
<!DOCTYPE html>
<html>
    <head>
        <style type="text/css">
            .dns-record-table th {
                text-align: right;
            }
            
            table {
                font-family: Roboto,RobotoDraft,Helvetica,Arial,sans-serif;
            }
        </style>
    </head>
    <body>
        <?=$Body?>
    </body>
</html>
<?
    $return = ob_get_clean();
    return $return;
}