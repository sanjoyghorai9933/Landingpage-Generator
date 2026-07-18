<?php

include 'config_smtp.php';

//Validate captcha
include 'config_smtp.php';

$invalid = '<script>
            alert("Invalid Captcha.");
            setTimeout("window.location=`index.html`",0);
          </script>';
$enc_token = $_POST['token'];
$token = base64_decode($enc_token);
$exp_token = explode('__s__', $token);

if (count($exp_token)==3) {
  if (($exp_token[0] + $exp_token[1]) != $exp_token[2]) {
    echo $invalid;
    exit;
  }
}else{
  echo $invalid;
  exit;
}
//End Validate captcha

require 'SMTPMailer.php';
$mail = new SMTPMailer();


$nameField = $_POST['name'];
$EmailField = $_POST['email'];
$MobileField = $_POST['phone'];
$messageField = $_POST['message'];
$projectField = "{{PROJECT_NAME}}";
$ip = $_SERVER['REMOTE_ADDR']; 

//=====SELLDO======

{{CRM_BLOCK}}





/* This takes the information and lines it up the way you want it to be sent in the email. */
$emailSubject ="Response  : By {{PROJECT_NAME}}" ;

$to = '{{TO_EMAIL}}';
$cc = '{{CC_EMAIL}}';
$bcc = '{{BCC_EMAIL}}';

$body = <<<EOD
<br><hr><br>
<html>
<head>
<style>
table {
    width:100%;
}
table, th, td {
    border: 1px solid #D5D7C8;
    border-collapse: collapse;
}
th, td {
    padding: 5px;
    text-align: center;
}
table#t01 tr:nth-child(even) {
    background-color: #eee;
}
table#t01 tr:nth-child(odd) {
   background-color:#fff;
}
table#t01 th	{
    background-color: #000;
    color: #fff;
}
</style>
</head>
<body>
<table id="t01">
  <tr>
    <th>Name</th>
    <th>Email</th>		
    <th>Phone</th>
    <th>Message</th>
    <th>IP</th>
    <th>Seller.Do</th>
  </tr>
  <tr>
    <td>$nameField</td>
    <td>$EmailField</td>		
    <td>$MobileField </td>
    <td>$messageField </td>
    <td>$ip</td>
    <td>$seller_id</td>
  </tr>
  
  
</table>

</body>
</html>
EOD;


$mail->addTo($to);
if (!empty($cc)) {
    $mail->addTo($cc);
}
if (!empty($bcc)) {
    $mail->addBcc($bcc);
}
$mail->Subject($emailSubject);
$mail->Body($body);
$success = $mail->Send(); // This tells the server what to send.
// echo $success ? "<script>alert('Thank you for contacting us. We will revert shortly.');</script>" : "<script>alert('Mail failed');</script>";
?>
<script type="text/javascript">
setTimeout("window.location='thanks.html'",0);
</script>
