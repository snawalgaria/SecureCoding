<?php
/**
 * Created by IntelliJ IDEA.
 * User: Ralph Oliver Schaumann
 * Date: 10/25/15
 * Time: 11:13 PM
 */

/**
 * @param array(string, string) $from, the name of the sender; the address fo the sender
 * @param array(string, string) $rcpt, he name of the receiver; the address fo the receiver
 * @param subject , thr subject of the message
 * @param string $msg, the message to send
 * @param int $port, the std port is 587
 * @return int 0 if everything went ok, 1 if error, 2 for invalid arguments
 */
function send_mail($from, $rcpt, $subject, $msg, $port = 587) {

    if(count($from) !== 2 || count($rcpt) !== 2)
        return 2;
    $offset = strpos($rcpt[1],"@") + 1;
    if(!$offset)
        return 2;

    $address = substr($rcpt[1],$offset,strlen($rcpt[1]) - $offset);

    $socket = fsockopen($address, $port, $timeout = .5);
    if(!$socket) {
        $smtp_addresses = NULL;
        getmxrr($address, $smtp_addresses);
        if(count($smtp_addresses) === 0)
            return 1;
        $address = $smtp_addresses[0];
        $socket = fsockopen($address, $port, $timeout = .5);
        if(!$socket){
            $socket = fsockopen($address, 465, $timeout = .5);
        }
    }

    if(!is_resource($socket))
        return 2;

    //follow the std mail protocol
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "220")
        return 1;

    fwrite($socket,"HELO " . $address . "\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return 1;

    fwrite($socket,"MAIL FROM:<" . $from[1] . " >\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return 1;

    fwrite($socket,"RCPT TO:<" . $rcpt[1] . ">\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return 1;

    fwrite($socket,"DATA\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "354")
        return 1;

    fwrite($socket, "From: \"" . $from[0] . "\" <" . $from[1] . ">\n");
    fwrite($socket, "To: \"" . $rcpt[0]. "\" <" . $rcpt[1] . ">\n");
    fwrite($socket, "Date: " . date('D, d F Y h:i:s a O', time()) . "\n");
    fwrite($socket, "Subject:" . $subject . "\n\n");
    fwrite($socket, $msg . "\n");
    fwrite($socket, "\r\n.\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return 1;
    fwrite($socket, "QUIT\r\n");
    fflush($socket);

    $received = trim(fread($socket, 4096));
    if(strlen($received) < 3 || substr($received,0,3) !== "221")
        return 1;
    //else echo "done\n";

    fclose($socket);

    return 0;
}

send_mail(array("tmaier", "absolute512@gmail.com"), array("absolute512","absolute512@fs.cs.hm.edu"),"test mail","this is a test")

?>