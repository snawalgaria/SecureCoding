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
 * @return int|string 0 if everything went ok, 1 if error, 2 for invalid arguments, mail log else
 */
function send_mail($from, $rcpt, $subject, $msg) {

    if(count($from) !== 2 || count($rcpt) !== 2)
        return 2;
    $offset = strpos($from[1],"@") + 1;
    if(!$offset)
        return 2;

    $address = "mail.roschaumann.com";

    $socket = fsockopen("tcp://roschaumann.com", 587);

    stream_context_set_option($socket, 'ssl', 'verify_peer', true);
    stream_context_set_option($socket, 'ssl', 'verify_host', true);
    stream_context_set_option($socket, 'ssl', 'allow_self_signed', false);
    stream_context_set_option($socket, 'ssl', 'cafile', __DIR__ . "/project/ca.crt");
//    stream_context_set_option($socket, 'ssl', 'cafile', __DIR__ . "/ca.crt");

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
    if(strlen($received) < 3 || substr($received,0,3) !== "250")
        return $mail_log . "\n";

    $mail_log .=  "STARTTLS \n";
    fwrite($socket,"STARTTLS \r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3)
        return $mail_log . "\n";

    $crypto_enabled = stream_socket_enable_crypto($socket,TRUE,STREAM_CRYPTO_METHOD_SSLv23_CLIENT);


//    if($crypto_enabled !== 1)
//        return 1;

    $mail_log .=  "AUTH LOGIN " . base64_encode("scbanking") . "\n";
    fwrite($socket,"AUTH LOGIN " . base64_encode("scbanking") . "\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3)
        return $mail_log . "\n";

    $mail_log .=  base64_encode("thisisaverysecurepassword") . "\n";
    fwrite($socket,base64_encode("thisisaverysecurepassword") . "\r\n");
    fflush($socket);
    $received = trim(fread($socket, 4096));
    $mail_log .=  $received . "\n";
    if(strlen($received) < 3)
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
    fwrite($socket, "Content-Type: text/html; charset=\"UTF-8\"\n");
    fwrite($socket, "Subject: " . $subject . "\n\n");
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

    fclose($socket);

    return 0;
}

//echo send_mail(
//        array("The SecureBank","scbanking@roschaumann.com"),
//        array("Ralph O. Schaumann","absolute512@gmail.com"),
//        "Your tan numbers have arrived!!!",
//        "msg"
//    ) . "\n";

?>
