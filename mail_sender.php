<?php
/**
 * Created by IntelliJ IDEA.
 * User: Ralph Oliver Schaumann
 * Date: 10/25/15
 * Time: 11:13 PM
 */

/**
 * Function by which an eMail may be sent.
 * Usage:
 *  send_mail(
 *      array("alice", "alice@example.com"),    //the name, mail address of the sender
 *      array("bob","bob@example.com"),         //the name, mail address of the receiver
 *      "test mail subject",                    //the subject of the mail to send
 *      "test mail body"                        //the body of the mail to send
 *  );
 * NOTE:    - Arguments are not sanitized.
 * @param array(string, string) $from, the name of the sender; the address fo the sender
 * @param array(string, string) $rcpt, he name of the receiver; the address fo the receiver
 * @param subject , thr subject of the message
 *      NOTE: The subject shall not contain the character <lf>, a.k.a '\n'
 *      This is not checked, needs to be done by caller.
 *      Subject-fields containing this character will be ill-formatted.
 * @param string $msg, the message to send
 *      NOTE: The message body shall not contain the char-sequence <cr><lf>, a.k.a '\r''\n'
 *      This is not checked, needs to be done by caller.
 *      Sending message-bodies containing this sequence will result in failure, since it
 *      breaks protocol
 * @return int 0 if everything went ok, 1 if error, 2 for invalid arguments
 */
function send_mail($from, $rcpt, $subject, $msg) {

    if(count($from) !== 2 || count($rcpt) !== 2)
        return 2;
    $offset = strpos($from[1],"@") + 1;
    if(!$offset)
        return 2;

    $address = substr($from[1],$offset,strlen($from[1]) - $offset);

    $smtp_ports = array(465);//array(465,587,25);
    $socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
    $current_port = 0;
    $connection = FALSE;
    while(!$connection && $current_port < count($smtp_ports)){
        echo "trying to connect with " . $address . " on port " . $smtp_ports[$current_port] . "\n";
        $connection = socket_connect($socket,$address,$smtp_ports[$current_port++]);
    }

    if(!$connection){
        $smtp_addresses = array();
        getmxrr($address, $smtp_addresses);
        echo implode($smtp_addresses) . "\n";
        $connection = FALSE;
        for($current_address = 0; $current_address < count($smtp_addresses) && !$connection;++$current_address){
            $current_port = 0;
            while(!$connection && $current_port < count($smtp_ports)){
                echo "trying to connect with " . $smtp_addresses[$current_address] . " on port " . $smtp_ports[$current_port] . "\n";
                $connection = socket_connect($socket,$smtp_addresses[$current_address],$smtp_ports[$current_port++]);
            }
            $address = $smtp_addresses[$current_address];
        }
    }

    if(!$connection){
        return "No port found... giving up";
    }

    $socket = fsockopen($address, $smtp_ports[$current_port - 1]);

    echo "found port " . $smtp_ports[$current_port - 1] . " on " . $address . "\n";

    if(!is_resource($socket))
        return 2;

    //follow the std mail protocol
    $received = trim(fread($socket, 4096));
    $mail_log = $received . "\n";
    if(strlen($received) < 3 || substr($received,0,3) !== "220")
        return $mail_log . "\n";

    $mail_log .=  "HELO " . $address . "\n";
    fwrite($socket,"HELO " . $address . "\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3 || !(substr($received,0,3) === "250" || substr($received,0,3) === "550" || substr($received,0,3) === "220"))
        return $mail_log . "\n";
    if(substr($received,0,3) === "550") {
        $socket = fsockopen($address, $smtp_ports[$current_port - 1]);
        $mail_log .= "EHLO " . $address . "\n";
        fwrite($socket, "EHLO " . $address . "\r\n");
        fflush($socket);
        $received = trim(fread($socket, 4096));
        $mail_log .= $received . "\n";
    }
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return $mail_log . "\n";

    $mail_log .=  "MAIL FROM:" . $from[1] . " \n";
    fwrite($socket,"MAIL FROM:<" . $from[1] . " >\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return $mail_log . "\n";

    $mail_log .=  "RCPT TO:" . $rcpt[1] . "\n";
    fwrite($socket,"RCPT TO:<" . $rcpt[1] . ">\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return $mail_log . "\n";

    $mail_log .=  "DATA\n";
    fwrite($socket,"DATA\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3 || substr($received,0,3) !== "354")
        return $mail_log . "\n";

    fwrite($socket, "From: \"" . $from[0] . "\" <" . $from[1] . ">\n");
    fwrite($socket, "To: \"" . $rcpt[0]. "\" <" . $rcpt[1] . ">\n");
    fwrite($socket, "Date: " . date('D, d F Y h:i:s a O', time()) . "\n");
    fwrite($socket, "Content-Type: text/html; charset=\"UTF-8\"");
    fwrite($socket, "Subject:" . $subject . "\n\n");
    fwrite($socket, $msg . "\n");
    fwrite($socket, "\r\n.\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return $mail_log . "\n";
    fwrite($socket, "QUIT\r\n");
    fflush($socket);
    $mail_log .=  "QUIT\n";

    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3 || substr($received,0,3) !== "221")
        return  $mail_log . "\n";
    //else echo "done\n";

    fclose($socket);

    return 0;
}

echo send_mail(
        array("The SecureBank","<working from address>"),
        array("The SecureBank","<working rcpr address>"),
        "Your tan numbers have arrived!!!",
        "msg"
    ) . "\n";

?>
