<?php

session_cache_limiter('nocache');
header('Expires: ' . gmdate('r', 0));
header('Content-type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'php-mailer/src/Exception.php';
require 'php-mailer/src/PHPMailer.php';
//require 'php-mailer/src/SMTP.php';


// Enter your email address. If you need multiple email recipes simply add a comma: email@domain.com, email2@domain.com
$to = "alfaspot.llc@yahoo.com";

// Add your reCaptcha Secret key if you wish to activate google reCaptcha security
$recaptcha_secret_key = ''; 

// Default message responses
const RESPONSE_MSG = [
    'success' => [
        "message_sent"           => "We have <strong>successfully</strong> received your message. We will get back to you as soon as possible."
    ],
    'form' => [
        "recipient_email"        => "Message not sent! The recipient email address is missing in the config file.",
        "name"                   => "Contact Form",
        "subject"                => "New Message From Website Contact Form"
    ],
    'google' => [
        "recapthca_invalid"     => "reCaptcha is not Valid! Please try again.",
        "recaptcha_secret_key"  => "Google reCaptcha secret key is missing in config file!"
    ],
    'config' => [
        "allow_url_fopen_invalid"     => "PHP: <strong>allow_url_fopen</strong> OR <strong>php_curl</strong> extension must be enabled in your php.ini file in order to use Google reCaptcha."
    ]
];

//This functionality will process post fields without worrying to define them on your html template for your customzied form. 
//Note: autofields will process only post fields that starts with name widget-contact-form OR with custom prefix field name
$form_prefix = isset($_POST["form-prefix"]) ? $_POST["form-prefix"] : "widget-contact-form-";
$form_title	= isset($_POST["form-name"]) ? $_POST["form-name"] : RESPONSE_MSG['form']['name'];
$subject = isset($_POST[$form_prefix."subject"]) ? $_POST[$form_prefix."subject"] : RESPONSE_MSG['form']['subject'];
$email = isset($_POST[$form_prefix."email"]) ? $_POST[$form_prefix."email"] : null;
$phone = isset($_POST[$form_prefix."phone"]) ? $_POST[$form_prefix."phone"] : null;
$message = isset($_POST[$form_prefix."message"]) ? $_POST[$form_prefix."message"] : null;
$name = isset($_POST[$form_prefix."name"]) ? $_POST[$form_prefix."name"] : null;


if( $_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if($email != '') {
        if(empty($to)) {
            $response = array ('response'=>'warning', 'message'=> RESPONSE_MSG['form']['recipient_email']);
            echo json_encode($response);
            die;
        }
            
        //If you don't receive the email, enable and configure these parameters below: 
        //$mail->SMTPOptions = array('ssl' => array('verify_peer' => false,'verify_peer_name' => false,'allow_self_signed' => true));
        //$mail->IsSMTP();
        //$mail->Host = 'mail.yourserver.com';                  // Specify main and backup SMTP servers, example: smtp1.example.com;smtp2.example.com
        //$mail->SMTPAuth = true;
        //$mail->Port = 587;                                    // TCP port to connect to  587 or 465
        //$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
        //$mail->Username = 'SMTP username';                    // SMTP username
        //$mail->Password = 'SMTP password';                    // SMTP password

        $mail = new PHPMailer;
        $mail->IsHTML(true);                                    
        $mail->CharSet = 'UTF-8';
        $mail->From = $email;
        $mail->FromName = $name;
        if(strpos($to, ',') !== false){
            $email_addresses = explode(',', $to);
            foreach($email_addresses as $email_address) {
                $mail->AddAddress(trim($email_address));
            }
        }
        else {$mail->AddAddress($to);}
        $mail->AddReplyTo($email, $name);
        $mail->Subject = $subject; 

       // Check if google captch is present
       if(isset($_POST['g-recaptcha-response'])) {

            if(empty($recaptcha_secret_key)) {
                $response = array ('response'=>'error', 'message'=> RESPONSE_MSG['google']['recaptcha_secret_key']);
                echo json_encode($response);
                die;
            }

            if(ini_get('allow_url_fopen')) { 
                //Try option: 1 - File get contents
                $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$recaptcha_secret_key.'&response='.$_POST['g-recaptcha-response']);
                $response_data = json_decode($response);
            } else if(extension_loaded('curl')) {
                //Try option: 2 - cUrl
                $ch = curl_init();
                curl_setopt_array($ch,[CURLOPT_URL=>'https://www.google.com/recaptcha/api/siteverify',CURLOPT_POST =>true,CURLOPT_POSTFIELDS=>['secret'=> $recaptcha_secret_key,'response'=>$_POST['g-recaptcha-response'],'remoteip'=>$_SERVER['REMOTE_ADDR']],CURLOPT_RETURNTRANSFER => true]);
                $response = curl_exec($ch); 
                curl_close($ch); 
                $response_data = json_decode($response); 
            } else {
                $response = array ('response'=>'error', 'message'=> RESPONSE_MSG['config']['allow_url_fopen_invalid']);
                echo json_encode($response);
                die;
            }

            //Return error message if not validated
            if ($response_data->success !== true ) {
                $response = array ('response'=>'error', 'message'=> RESPONSE_MSG['google']['recapthca_invalid']);
                echo json_encode($response);
                die;
            }
        }

        //Remove unused fields
        foreach (array("form-prefix", "subject", "g-recaptcha", "g-recaptcha-response") as $fld) {
            unset($_POST[$form_prefix . $fld]);
        }
        unset($_POST['g-recaptcha-response']);
        //Format eMail Template 
        
        //$mail_template = file_get_contents("emailtemplate.html");
        // Construct the email message
        $mail_template = "Enquiry Details From Website\n";
        $mail_template .= str_repeat("-", strlen("Enquiry Details From Website")) . "\n"; // Underline with dashes

        $mail_template .= "Name: $name\n";
        $mail_template .= "Phone: $phone\n";
        $mail_template .= "Email: $email\n";
        $mail_template .= "Message: $message\n\n";
        $mail_template .= "Thank you.";
        
        $mail->Body = $mail_template; 
        // Add MIME headers
        //$mail->addCustomHeader('MIME-Version: 1.0');
        //$mail->addCustomHeader('Content-Type: text/html; charset=UTF-8');

        // Check if any file is attached
        $attachments = [];
        if (!empty($_FILES[$form_prefix.'attachment'])) {
            $result = array();
            foreach ($_FILES[$form_prefix.'attachment'] as $key => $value) {
                for ($i = 0; $i < count($value); $i++) {
                    $result[$i][$key] = $value[$i];
                }
            }
            foreach ( $result as $key => $attachment) {
                 $mail->addAttachment($attachment['tmp_name'],$attachment['name']); 
            }
        }

        if(!$mail->Send()) {
            $response = array ('response'=>'error', 'message'=> $mail->ErrorInfo);  
        }else {                  
            $response = array ('response'=>'success', 'message'=> RESPONSE_MSG['success']['message_sent']);  
        }
        echo json_encode($response);
    } else {
        $response = array ('response'=>'error');     
        echo json_encode($response);
    }
}
?>