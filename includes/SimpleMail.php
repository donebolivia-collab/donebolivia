<?php
class SimpleMail {
    private $host = 'smtp.gmail.com';
    private $port = 465;
    private $username;
    private $password;
    private $debug = false;

    public function __construct($user, $pass) {
        $this->username = $user;
        $this->password = $pass;
    }

    public function send($to, $subject, $body, $fromName = 'Done Bolivia') {
        // Contexto para evitar errores de certificados en local/dev
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = stream_socket_client("ssl://{$this->host}:{$this->port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            error_log("Error Conexión SMTP: $errno - $errstr");
            return false;
        }

        if (!$this->readResponse($socket, "220")) return false;
        
        // EHLO
        $this->sendCommand($socket, "EHLO " . gethostname());
        if (!$this->readResponse($socket, "250")) return false;

        // AUTH LOGIN
        $this->sendCommand($socket, "AUTH LOGIN");
        if (!$this->readResponse($socket, "334")) return false;

        $this->sendCommand($socket, base64_encode($this->username));
        if (!$this->readResponse($socket, "334")) return false;

        $this->sendCommand($socket, base64_encode($this->password));
        if (!$this->readResponse($socket, "235")) return false;

        // MAIL FROM
        $this->sendCommand($socket, "MAIL FROM: <{$this->username}>");
        if (!$this->readResponse($socket, "250")) return false;

        // RCPT TO
        $this->sendCommand($socket, "RCPT TO: <$to>");
        if (!$this->readResponse($socket, "250")) return false;

        // DATA
        $this->sendCommand($socket, "DATA");
        if (!$this->readResponse($socket, "354")) return false;

        // Headers & Body
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: =?UTF-8?B?".base64_encode($fromName)."?= <{$this->username}>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
        $headers .= "Date: " . date("r") . "\r\n";
        
        fputs($socket, "$headers\r\n$body\r\n.\r\n");
        if (!$this->readResponse($socket, "250")) return false;

        // QUIT
        $this->sendCommand($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function sendCommand($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
    }

    private function readResponse($socket, $expected) {
        $response = "";
        while($str = fgets($socket, 515)) {
            $response .= $str;
            if(substr($str, 3, 1) == " ") { break; }
        }
        if ($this->debug) error_log("SMTP Resp: $response");
        // Check code
        return substr($response, 0, 3) == $expected;
    }
}
