<?php
/**
 * Created by IntelliJ IDEA.
 * User: Ralph Oliver Schaumann
 * Date: 10/25/15
 * Time: 11:13 PM
 */

/**
 * @param string $address, the address for which to
 * @param int $index, the index in the array of possible ports
 * @param array<int> $ports
 * @return bool|resource false, iff no valid socket was found,
 *      socket-resource else
 */
function get_socket($address, $index, $ports){
    if(count($ports) >= $index)
        return false;
    $socket = fsockopen($address, $ports[$index], $timeout = .5);
    if(!$socket)
        return get_socket($address,$index + 1, $ports);
    else return $socket;
}

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
    $offset = strpos($rcpt[1],"@") + 1;
    if(!$offset)
        return 2;

    $address = substr($rcpt[1],$offset,strlen($rcpt[1]) - $offset);

    $socket = get_socket($address,0,array(25,465,587));

    if(!$socket){
        $smtp_addresses = NULL;
        getmxrr($address, $smtp_addresses);
        $index = 0;
        while(!$socket && $index < count($smtp_addresses))
            $socket = get_socket($smtp_addresses[$index++],0,array(25,465,587));
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

?>